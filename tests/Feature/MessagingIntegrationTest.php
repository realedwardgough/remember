<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Enum\TimelinePostType;
use App\Models\Conversation;
use App\Models\Media;
use App\Models\MessageAttachment;
use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    private User $sender;
    private User $recipient;
    private Conversation $conversation;
    private array $delivered = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $keys = VAPID::createVapidKeys();
        config([
            'queue.default' => 'database', 'broadcasting.default' => 'null',
            'remember.push.enabled' => true, 'remember.push.public_key' => $keys['publicKey'],
            'remember.push.private_key' => $keys['privateKey'], 'remember.push.subject' => 'mailto:integration@example.test',
            'filesystems.disks.message_gcs.bucket' => 'integration-temporary-photos',
            'filesystems.disks.gcs.bucket' => 'integration-permanent-photos',
        ]);
        Storage::fake('message_gcs');
        Storage::fake('gcs');
        $this->sender = User::factory()->create(['name' => 'Original Sender']);
        $this->recipient = User::factory()->create();
        $this->conversation = app(StartConversation::class)->handle($this->sender, $this->recipient->id);
        NotificationPreference::query()->create(['user_id' => $this->recipient->id, 'message_alerts' => true]);
        PushSubscription::query()->create([
            'user_id' => $this->recipient->id, 'device_hash' => hash('sha256', 'integration-device'),
            'endpoint_hash' => hash('sha256', 'integration-endpoint'), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/integration',
            'public_key' => $keys['publicKey'], 'auth_token' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ]);
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->andReturnUsing(function (PushSubscription $device, array $payload): int {
            $this->delivered[] = ['user_id' => $device->user_id, 'payload' => $payload];
            return 201;
        });
        $this->instance(PushTransport::class, $transport);
    }

    private function upload(): MessageAttachment
    {
        $id = $this->actingAs($this->sender)->postJson(route('messaging.attachments.store', $this->conversation), [
            'image' => UploadedFile::fake()->image('family.jpg', 160, 120), 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        return MessageAttachment::query()->findOrFail($id);
    }

    private function drainQueue(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 30])->assertSuccessful();
        $this->assertDatabaseCount('failed_jobs', 0);
    }

    #[Test]
    public function a_combined_message_survives_retries_source_removal_user_deletion_and_photo_expiry(): void
    {
        $post = Post::factory()->memory()->create(['title' => 'Shared family memory']);
        $media = Media::factory()->for($post)->create(['disk' => 'gcs', 'path' => 'posts/permanent.jpg']);
        Storage::disk('gcs')->put($media->path, 'permanent timeline photo');
        $image = $this->upload();
        $body = 'A lovely memory 👨‍👩‍👧‍👦 🇬🇧 👍🏽';
        $payload = ['body' => $body, 'attachment_ids' => [$image->id], 'shared_post_id' => $post->id, 'client_request_id' => (string) Str::uuid()];
        $messageId = $this->postJson(route('messaging.send', $this->conversation), $payload)
            ->assertCreated()->assertJsonPath('data.body', $body)->assertJsonPath('data.shared_post.status', 'available')->json('data.id');
        $this->postJson(route('messaging.send', $this->conversation), $payload)->assertOk()->assertJsonPath('data.id', $messageId);
        $this->assertDatabaseCount('message_push_deliveries', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->actingAs($this->recipient)->getJson(route('messaging.index'))->assertJsonPath('unread_count', 1);
        $this->get(route('messaging.attachments.show', $image))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->get(route('messages.shared-post', [$this->conversation, $messageId]))->assertRedirect(route('posts.show', $post));

        // A real serialized database job runs through the worker; only the network transport is faked.
        $queued = DB::table('jobs')->pluck('payload')->map(fn (string $payload): string => json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['data']['command'])->implode("\n");
        $this->assertStringNotContainsString($body, $queued);
        $this->assertStringNotContainsString($image->path, $queued);
        $this->travel(6)->seconds();
        $this->drainQueue();
        $this->assertSame('sent', MessagePushDelivery::query()->sole()->state);
        $this->assertSame([['user_id' => $this->recipient->id, 'payload' => [
            'title' => 'Remember', 'body' => 'You have a new message in Remember.',
            'url' => '/messages/'.$this->conversation->id, 'tag' => 'remember-conversation-'.$this->conversation->id,
        ]]], $this->delivered);

        $post->delete();
        $this->sender->delete();
        $this->getJson(route('messaging.show', $this->conversation))
            ->assertJsonPath('conversation.can_send', false)->assertJsonPath('data.0.sender.name', 'Deleted User')
            ->assertJsonPath('data.0.shared_post.status', 'unavailable')->assertDontSee('Original Sender')->assertDontSee('Shared family memory');
        $this->get(route('messages.shared-post', [$this->conversation, $messageId]))->assertNotFound();
        $this->postJson(route('messaging.send', $this->conversation), ['body' => 'Cannot reply', 'client_request_id' => (string) Str::uuid()])->assertForbidden();
        $this->travelTo($image->expires_at);
        $this->get(route('messaging.attachments.show', $image))->assertGone();
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        $this->getJson(route('messaging.message', [$this->conversation, $messageId]))
            ->assertJsonPath('data.body', $body)->assertJsonPath('data.sender.name', 'Deleted User')
            ->assertJsonPath('data.attachments.0.placeholder', 'This image is older than 48 hours and has been removed.');
        Storage::disk('message_gcs')->assertMissing([$image->path, $image->thumbnail_path]);
        Storage::disk('gcs')->assertExists($media->path);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('message_post_shares', 1);
    }

    #[Test]
    public function reading_a_combined_message_before_the_worker_runs_suppresses_push_and_clears_the_badge(): void
    {
        $image = $this->upload();
        $post = Post::factory()->memory()->create();
        $this->postJson(route('messaging.send', $this->conversation), [
            'body' => 'Read while the app is open 💕', 'attachment_ids' => [$image->id], 'shared_post_id' => $post->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated();
        $history = $this->actingAs($this->recipient)->getJson(route('messaging.show', $this->conversation))->assertOk();
        $this->putJson(route('messaging.read', $this->conversation), ['read_token' => $history->json('read_token')])->assertNoContent();
        $this->getJson(route('messaging.index'))->assertJsonPath('unread_count', 0);
        $this->travel(6)->seconds();
        $this->drainQueue();
        $this->assertSame('skipped', MessagePushDelivery::query()->sole()->state);
        $this->assertSame([], $this->delivered);
        $this->assertTrue($image->fresh()->available());
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function a_rejected_private_post_does_not_attach_the_photo_or_enqueue_an_alert(): void
    {
        $image = $this->upload();
        $letter = Post::factory()->for($this->sender, 'author')->create(['post_type' => TimelinePostType::LETTER]);
        $requestId = (string) Str::uuid();
        $this->postJson(route('messaging.send', $this->conversation), [
            'body' => 'Private letter with photo', 'attachment_ids' => [$image->id], 'shared_post_id' => $letter->id, 'client_request_id' => $requestId,
        ])->assertForbidden();
        $this->assertNull($image->fresh()->message_id);
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('message_push_deliveries', 0);
        $this->postJson(route('messaging.send', $this->conversation), [
            'body' => 'Photo without the private letter', 'attachment_ids' => [$image->id], 'client_request_id' => $requestId,
        ])->assertCreated();
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('message_push_deliveries', 1);
        $this->actingAs(User::factory()->create())->getJson(route('messaging.show', $this->conversation))->assertForbidden();
        $this->get(route('messaging.attachments.show', $image))->assertForbidden();
    }

    #[Test]
    public function unsafe_storage_changes_stop_cleanup_without_affecting_timeline_media_or_claiming_removal(): void
    {
        $image = $this->upload();
        $messageId = $this->postJson(route('messaging.send', $this->conversation), [
            'body' => 'Keep the history', 'attachment_ids' => [$image->id], 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        Storage::disk('gcs')->put('posts/permanent.jpg', 'permanent timeline photo');
        $this->travelTo($image->expires_at);
        config(['filesystems.disks.message_gcs.bucket' => config('filesystems.disks.gcs.bucket')]);
        $this->artisan('messages:cleanup-images')->assertFailed();
        $this->assertNull($image->fresh()->files_removed_at);
        Storage::disk('message_gcs')->assertExists([$image->path, $image->thumbnail_path]);
        Storage::disk('gcs')->assertExists('posts/permanent.jpg');
        $this->get(route('messaging.attachments.show', $image))->assertGone();
        $this->getJson(route('messaging.message', [$this->conversation, $messageId]))
            ->assertJsonPath('data.attachments.0.status', 'expired');
        config(['filesystems.disks.message_gcs.bucket' => 'integration-temporary-photos', 'filesystems.disks.message_gcs.visibility' => 'public']);
        $this->artisan('messages:cleanup-images')->assertFailed();
        $this->assertNull($image->fresh()->files_removed_at);
        config(['filesystems.disks.message_gcs.visibility' => 'private']);
        // Cleanup must still work when only the setting for NEW uploads is invalid.
        config(['remember.message_images.retention_hours' => 'invalid']);
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        Storage::disk('message_gcs')->assertMissing([$image->path, $image->thumbnail_path]);
        Storage::disk('gcs')->assertExists('posts/permanent.jpg');
        $this->getJson(route('messaging.message', [$this->conversation, $messageId]))
            ->assertJsonPath('data.attachments.0.placeholder', 'This image is older than 48 hours and has been removed.');
    }
}
