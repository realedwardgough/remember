<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\Messaging\MessageImageSettings;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageImagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('filesystems.disks.message_gcs.bucket', 'temporary-message-photos');
        config()->set('filesystems.disks.gcs.bucket', 'permanent-timeline-photos');
        Storage::fake('message_gcs');
        Storage::fake('gcs');
    }

    private function conversation(User $sender, ?User $recipient = null): Conversation
    {
        return app(StartConversation::class)->handle($sender, ($recipient ?? User::factory()->create())->id);
    }

    private function upload(User $sender, Conversation $conversation, ?UploadedFile $file = null, ?string $requestId = null): MessageAttachment
    {
        $id = $this->actingAs($sender)->postJson(route('messaging.attachments.store', $conversation), [
            'image' => $file ?? UploadedFile::fake()->image('photo.jpg', 1200, 800),
            'client_request_id' => $requestId ?? (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.status', 'available')->json('data.id');

        return MessageAttachment::findOrFail($id);
    }

    private function attach(User $sender, Conversation $conversation, array $ids, string $body = '', ?string $requestId = null): int
    {
        return $this->actingAs($sender)->postJson(route('messaging.send', $conversation), [
            'body' => $body, 'attachment_ids' => $ids, 'client_request_id' => $requestId ?? (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
    }

    #[Test]
    public function photos_use_separate_private_storage_and_preserve_retention_for_existing_uploads(): void
    {
        $this->travelTo(now()->startOfSecond());
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $attachment = $this->upload($sender, $conversation);
        $this->assertSame(48, $attachment->retention_hours);
        $this->assertTrue($attachment->expires_at->equalTo($attachment->uploaded_at->addHours(48)));
        $this->assertSame('message_gcs', $attachment->disk);
        $this->assertSame(1200, $attachment->width);
        Storage::disk('message_gcs')->assertExists([$attachment->path, $attachment->thumbnail_path]);
        $this->assertSame([], Storage::disk('gcs')->allFiles());
        $this->assertSame('image/webp', getimagesizefromstring(Storage::disk('message_gcs')->get($attachment->path))['mime']);
        $thumbnail = getimagesizefromstring(Storage::disk('message_gcs')->get($attachment->thumbnail_path));
        $this->assertLessThanOrEqual(480, max($thumbnail[0], $thumbnail[1]));

        config()->set('remember.message_images.retention_hours', 72);
        $new = $this->upload($sender, $conversation);
        $this->assertSame(72, $new->retention_hours);
        $this->assertTrue($new->expires_at->equalTo($new->uploaded_at->addHours(72)));
        $this->assertSame(48, $attachment->fresh()->retention_hours);
    }

    #[Test]
    public function only_participants_can_read_sent_photos_and_staged_photos_are_sender_only(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = $this->conversation($sender, $recipient);
        $attachment = $this->upload($sender, $conversation);
        $this->get(route('messaging.attachments.show', $attachment))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->actingAs($recipient)->get(route('messaging.attachments.show', $attachment))->assertNotFound();
        $messageId = $this->attach($sender, $conversation, [$attachment->id]);
        $response = $this->actingAs($recipient)->get(route('messaging.attachments.show', $attachment))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson(route('messaging.show', $conversation))->assertJsonPath('data.0.attachments.0.id', $attachment->id);
        $this->actingAs(User::factory()->create())->get(route('messaging.attachments.show', $attachment))->assertForbidden();
        $this->getJson(route('messaging.message', [$conversation, $messageId]))->assertForbidden();
    }

    #[Test]
    public function sends_support_image_only_messages_and_retries_even_after_removal(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $first = $this->upload($sender, $conversation);
        $second = $this->upload($sender, $conversation);
        $requestId = (string) Str::uuid();
        $messageId = $this->attach($sender, $conversation, [$second->id, $first->id], requestId: $requestId);
        $this->assertSame('', Message::findOrFail($messageId)->body);
        $this->travel(49)->hours();
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        $this->postJson(route('messaging.send', $conversation), [
            'body' => '', 'attachment_ids' => [$first->id, $second->id], 'client_request_id' => $requestId,
        ])->assertOk()->assertJsonPath('data.id', $messageId);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('message_attachments', 2);
    }

    #[Test]
    public function expiry_denies_access_before_cleanup_and_then_shows_the_exact_removal_placeholder(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $attachment = $this->upload($sender, $conversation);
        $messageId = $this->attach($sender, $conversation, [$attachment->id], 'Keep this text');
        $this->travelTo($attachment->expires_at);
        $this->get(route('messaging.attachments.show', $attachment))->assertGone();
        $this->getJson(route('messaging.message', [$conversation, $messageId]))
            ->assertJsonPath('data.attachments.0.status', 'expired')->assertJsonPath('data.attachments.0.url', null);
        Storage::disk('message_gcs')->assertExists($attachment->path);
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        Storage::disk('message_gcs')->assertMissing([$attachment->path, $attachment->thumbnail_path]);
        $this->getJson(route('messaging.message', [$conversation, $messageId]))
            ->assertJsonPath('data.body', 'Keep this text')
            ->assertJsonPath('data.attachments.0.status', 'removed')
            ->assertJsonPath('data.attachments.0.placeholder', 'This image is older than 48 hours and has been removed.');
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        $this->assertDatabaseCount('message_attachments', 1);
    }

    #[Test]
    public function custom_retention_is_reflected_in_removed_image_text(): void
    {
        config()->set('remember.message_images.retention_hours', 1);
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $attachment = $this->upload($sender, $conversation);
        $messageId = $this->attach($sender, $conversation, [$attachment->id]);
        $this->travel(2)->hours();
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        $this->getJson(route('messaging.message', [$conversation, $messageId]))
            ->assertJsonPath('data.attachments.0.placeholder', 'This image is older than 1 hour and has been removed.');
    }

    #[Test]
    public function upload_retries_reuse_objects_and_reject_conflicting_content(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $requestId = (string) Str::uuid();
        $file = UploadedFile::fake()->image('same.jpg', 100, 80);
        $attachment = $this->upload($sender, $conversation, $file, $requestId);
        $same = $this->upload($sender, $conversation, $file, strtoupper($requestId));
        $this->assertSame($attachment->id, $same->id);
        $this->assertCount(2, Storage::disk('message_gcs')->allFiles());
        $this->postJson(route('messaging.attachments.store', $conversation), [
            'image' => UploadedFile::fake()->image('different.jpg', 80, 100), 'client_request_id' => $requestId,
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    #[Test]
    public function photos_cannot_be_stolen_reused_or_attached_to_another_conversation(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = $this->conversation($sender, $recipient);
        $attachment = $this->upload($sender, $conversation);
        $other = $this->conversation($sender);
        foreach ([[$recipient, $conversation], [$sender, $other]] as [$actor, $chat]) {
            $this->actingAs($actor)->postJson(route('messaging.send', $chat), [
                'body' => '', 'attachment_ids' => [$attachment->id], 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('attachment_ids');
        }
        $this->attach($sender, $conversation, [$attachment->id]);
        $this->postJson(route('messaging.send', $conversation), [
            'attachment_ids' => [$attachment->id], 'client_request_id' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function invalid_formats_dimensions_and_oversize_files_are_rejected(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        foreach ([
            UploadedFile::fake()->createWithContent('fake.jpg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->create('big.jpg', 10241, 'image/jpeg'),
            UploadedFile::fake()->image('wide.png', 8193, 1),
        ] as $file) {
            $this->actingAs($sender)->postJson(route('messaging.attachments.store', $conversation), [
                'image' => $file, 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertDatabaseEmpty('message_attachments');
        $this->assertSame([], Storage::disk('message_gcs')->allFiles());
    }

    #[Test]
    public function invalid_retention_or_a_shared_bucket_never_falls_back_to_timeline_storage(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        foreach ([0, -1, 'invalid', 87601] as $hours) {
            config()->set('remember.message_images.retention_hours', $hours);
            $this->assertNull(app(MessageImageSettings::class)->retentionHours());
            $this->actingAs($sender)->postJson(route('messaging.attachments.store', $conversation), [
                'image' => UploadedFile::fake()->image('photo.jpg'), 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable();
        }
        config()->set('remember.message_images.retention_hours', 48);
        config()->set('filesystems.disks.message_gcs.bucket', 'permanent-timeline-photos');
        $this->assertFalse(app(MessageImageSettings::class)->available());
        $this->postJson(route('messaging.attachments.store', $conversation), [
            'image' => UploadedFile::fake()->image('photo.jpg'), 'client_request_id' => (string) Str::uuid(),
        ])->assertUnprocessable();
        $this->assertSame([], Storage::disk('gcs')->allFiles());
        $this->assertDatabaseEmpty('message_attachments');
    }

    #[Test]
    public function abandoned_uploads_are_cleaned_without_touching_live_message_or_timeline_photos(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $orphan = $this->upload($sender, $conversation);
        $live = $this->upload($sender, $conversation);
        $this->attach($sender, $conversation, [$live->id]);
        Storage::disk('gcs')->put('posts/1/permanent.webp', 'permanent timeline photo');
        $this->travel(61)->minutes();
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        Storage::disk('message_gcs')->assertMissing([$orphan->path, $orphan->thumbnail_path]);
        Storage::disk('message_gcs')->assertExists([$live->path, $live->thumbnail_path]);
        Storage::disk('gcs')->assertExists('posts/1/permanent.webp');
    }

    #[Test]
    public function partial_upload_failure_retains_object_names_for_cleanup_and_safe_retry(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $disk = Storage::disk('message_gcs');
        $failure = Mockery::mock(FilesystemAdapter::class);
        $failure->shouldReceive('put')->once()->andReturnUsing(fn ($path, $contents, $options) => $disk->put($path, $contents, $options));
        $failure->shouldReceive('put')->once()->andThrow(new \RuntimeException('Thumbnail storage failed'));
        Storage::set('message_gcs', $failure);
        $requestId = (string) Str::uuid();
        $file = UploadedFile::fake()->image('photo.jpg');
        $this->actingAs($sender)->postJson(route('messaging.attachments.store', $conversation), [
            'image' => $file, 'client_request_id' => $requestId,
        ])->assertUnprocessable();
        $attachment = MessageAttachment::query()->sole();
        $this->assertNull($attachment->ready_at);
        $disk->assertExists($attachment->path);
        Storage::set('message_gcs', $disk);
        $recovered = $this->upload($sender, $conversation, $file, $requestId);
        $this->assertSame($attachment->id, $recovered->id);
        $this->assertCount(2, $disk->allFiles());
    }

    #[Test]
    public function image_uploads_require_live_participants_and_message_reads_cannot_cross_conversations(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = $this->conversation($sender, $recipient);
        $data = ['image' => UploadedFile::fake()->image('photo.jpg'), 'client_request_id' => (string) Str::uuid()];
        $this->postJson(route('messaging.attachments.store', $conversation), $data)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('messaging.attachments.store', $conversation), $data)->assertForbidden();
        $attachment = $this->upload($sender, $conversation);
        $messageId = $this->attach($sender, $conversation, [$attachment->id]);
        $other = $this->conversation($sender);
        $this->getJson(route('messaging.message', [$other, $messageId]))->assertNotFound();
        $recipient->delete();
        $this->postJson(route('messaging.attachments.store', $conversation), $data)->assertForbidden();
        $this->get(route('messaging.attachments.show', $attachment))->assertOk();
    }

    #[Test]
    public function photos_are_reencoded_without_source_metadata(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $source = UploadedFile::fake()->image('gps-photo.jpg', 100, 80);
        $bytes = file_get_contents($source->getRealPath());
        $comment = 'Private GPS metadata: 51.5,-0.1';
        $bytes = substr($bytes, 0, 2)."\xff\xfe".pack('n', strlen($comment) + 2).$comment.substr($bytes, 2);
        $attachment = $this->upload($sender, $conversation, UploadedFile::fake()->createWithContent('gps-photo.jpg', $bytes));
        foreach ([$attachment->path, $attachment->thumbnail_path] as $path) {
            $encoded = Storage::disk('message_gcs')->get($path);
            $this->assertStringNotContainsString($comment, $encoded);
            $this->assertSame('image/webp', getimagesizefromstring($encoded)['mime']);
        }
    }

    #[Test]
    public function messages_accept_four_photos_but_reject_duplicates_and_conflicting_send_retries(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $ids = [];
        for ($index = 0; $index < 5; $index++) {
            $ids[] = $this->upload($sender, $conversation)->id;
        }
        foreach ([$ids, [$ids[0], $ids[0]]] as $invalid) {
            $this->postJson(route('messaging.send', $conversation), [
                'attachment_ids' => $invalid, 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable();
        }
        $requestId = (string) Str::uuid();
        $this->attach($sender, $conversation, array_slice($ids, 0, 4), requestId: $requestId);
        $this->postJson(route('messaging.send', $conversation), [
            'attachment_ids' => [$ids[0]], 'client_request_id' => $requestId,
        ])->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function incomplete_uploads_are_cleaned_after_fifteen_minutes_and_expired_uploads_cannot_be_sent(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $attachment = $this->upload($sender, $conversation);
        $attachment->update(['ready_at' => null]);
        Storage::disk('message_gcs')->delete($attachment->thumbnail_path);
        $this->travel(16)->minutes();
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        Storage::disk('message_gcs')->assertMissing([$attachment->path, $attachment->thumbnail_path]);
        $this->postJson(route('messaging.send', $conversation), [
            'attachment_ids' => [$attachment->id], 'client_request_id' => (string) Str::uuid(),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment_ids');
        $this->assertDatabaseEmpty('messages');
    }

    #[Test]
    public function lifecycle_preview_respects_new_configuration_and_existing_longer_retention(): void
    {
        config()->set('remember.message_images.retention_hours', 72);
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $this->upload($sender, $conversation);
        config()->set('remember.message_images.retention_hours', 24);
        $this->assertSame(0, Artisan::call('messages:image-lifecycle'));
        $policy = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(3, $policy['rule'][0]['condition']['age']);
        config()->set('remember.message_images.retention_hours', 96);
        $this->assertSame(0, Artisan::call('messages:image-lifecycle'));
        $policy = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(4, $policy['rule'][0]['condition']['age']);
        config()->set('remember.message_images.retention_hours', 'invalid');
        $this->artisan('messages:image-lifecycle')->assertFailed();
    }

    #[Test]
    public function failed_deletion_is_retried_without_claiming_the_photo_has_been_removed(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender);
        $attachment = $this->upload($sender, $conversation);
        $messageId = $this->attach($sender, $conversation, [$attachment->id]);
        $disk = Storage::disk('message_gcs');
        $failure = Mockery::mock(FilesystemAdapter::class);
        $failure->shouldReceive('delete')->once()->andReturn(false);
        Storage::set('message_gcs', $failure);
        $this->travel(49)->hours();
        $this->artisan('messages:cleanup-images')->assertFailed();
        $this->assertNull($attachment->fresh()->files_removed_at);
        $this->getJson(route('messaging.message', [$conversation, $messageId]))->assertJsonPath('data.attachments.0.status', 'expired');
        Storage::set('message_gcs', $disk);
        $this->artisan('messages:cleanup-images')->assertSuccessful();
        $this->assertNotNull($attachment->fresh()->files_removed_at);
    }
}
