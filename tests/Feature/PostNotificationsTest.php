<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Jobs\SendMessagePush;
use App\Models\MessagePushDelivery;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\PostPush;
use App\Services\Messaging\PushPreview;
use App\Services\Messaging\PushSettings;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = VAPID::createVapidKeys();
        config(['queue.default' => 'database', 'remember.push.enabled' => true,
            'remember.push.subject' => 'mailto:posts@example.test', 'remember.push.public_key' => $keys['publicKey'], 'remember.push.private_key' => $keys['privateKey']]);
        Queue::fake();
    }

    private function subscribe(User $user, bool $posts = true): PushSubscription
    {
        NotificationPreference::query()->create(['user_id' => $user->id, 'message_alerts' => false, 'post_alerts' => $posts]);
        return PushSubscription::query()->create([
            'user_id' => $user->id, 'device_hash' => hash('sha256', 'device-'.$user->id),
            'endpoint_hash' => hash('sha256', 'endpoint-'.$user->id), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$user->id,
            'public_key' => 'fixture-public-key', 'auth_token' => 'fixture-auth-token',
        ]);
    }

    private function work(MessagePushDelivery $delivery, bool $expected): array
    {
        $this->travelTo($delivery->next_attempt_at->addSecond());
        $payload = [];
        $transport = Mockery::mock(PushTransport::class);
        if ($expected) {
            $transport->shouldReceive('send')->once()->andReturnUsing(function ($device, $data) use (&$payload): int {
                $payload = $data;
                return 201;
            });
        } else {
            $transport->shouldNotReceive('send');
        }
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        return $payload;
    }

    #[Test]
    public function new_posts_target_opted_in_visible_readers_but_not_the_author_and_never_notify_for_edits(): void
    {
        $author = User::factory()->create(['name' => 'Jamie']);
        $reader = User::factory()->create();
        $this->subscribe($author);
        $this->subscribe($reader);
        $this->subscribe(User::factory()->create(), false);
        $post = Post::factory()->for($author, 'author')->memory()->create(['title' => 'First steps 👣']);
        $delivery = MessagePushDelivery::query()->sole();
        $this->assertSame('post', $delivery->kind);
        $this->assertSame($post->id, $delivery->post_id);
        $this->assertSame($reader->id, PushSubscription::findOrFail($delivery->push_subscription_id)->user_id);
        $post->update(['title' => 'Updated first steps 👣']);
        app(PostPush::class)->prepare($post);
        $this->assertDatabaseCount('message_push_deliveries', 1);
        $payload = $this->work($delivery, true);
        $this->assertSame('Jamie posted in Remember', $payload['title']);
        $this->assertSame('Updated first steps 👣', $payload['body']);
        $this->assertSame('/posts/'.$post->id, $payload['url']);
        $this->assertSame('post', $payload['kind']);
        $this->assertSame('sent', $delivery->fresh()->state);
        $this->actingAs($reader)->get($payload['url'])->assertOk();
    }

    #[Test]
    public function letters_are_never_sent_to_other_users_and_visibility_is_rechecked_before_delivery(): void
    {
        $author = User::factory()->create();
        $reader = User::factory()->create();
        $this->subscribe($reader);
        Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::LETTER]);
        $this->assertDatabaseCount('message_push_deliveries', 0);
        $post = Post::factory()->for($author, 'author')->memory()->create();
        $delivery = MessagePushDelivery::query()->sole();
        $post->update(['post_type' => TimelinePostType::LETTER]);
        $this->work($delivery, false);
        $this->assertSame('skipped', $delivery->fresh()->state);
        $this->actingAs($reader)->get(route('posts.show', $post))->assertForbidden();
    }

    #[Test]
    public function deleted_posts_and_authors_do_not_become_test_notifications(): void
    {
        $author = User::factory()->create();
        $this->subscribe(User::factory()->create());
        $post = Post::factory()->for($author, 'author')->memory()->create();
        $delivery = MessagePushDelivery::query()->sole();
        $post->forceDelete();
        $this->assertNull($delivery->fresh()->post_id);
        $this->assertSame('post', $delivery->fresh()->kind);
        $this->work($delivery, false);
        $this->assertSame('skipped', $delivery->fresh()->state);
        Post::factory()->for($author, 'author')->memory()->create();
        $next = MessagePushDelivery::query()->latest('id')->firstOrFail();
        $author->delete();
        $this->work($next, false);
        $this->assertSame('skipped', $next->fresh()->state);
    }

    #[Test]
    public function opting_out_or_removing_the_device_before_delivery_stops_post_alerts(): void
    {
        $reader = User::factory()->create();
        $device = $this->subscribe($reader);
        Post::factory()->memory()->create();
        $delivery = MessagePushDelivery::query()->sole();
        NotificationPreference::query()->where('user_id', $reader->id)->update(['post_alerts' => false]);
        $this->work($delivery, false);
        $this->assertSame('skipped', $delivery->fresh()->state);
        NotificationPreference::query()->where('user_id', $reader->id)->update(['post_alerts' => true]);
        Post::factory()->memory()->create();
        $next = MessagePushDelivery::query()->latest('id')->firstOrFail();
        $device->delete();
        $this->work($next, false);
        $this->assertDatabaseMissing('message_push_deliveries', ['id' => $next->id]);
    }

    #[Test]
    public function post_delivery_is_enqueued_only_after_commit_and_rollback_creates_no_alert(): void
    {
        $this->subscribe(User::factory()->create());
        DB::beginTransaction();
        $post = Post::factory()->memory()->create();
        Queue::assertNotPushed(SendMessagePush::class);
        DB::commit();
        Queue::assertPushed(SendMessagePush::class, 1);
        DB::beginTransaction();
        Post::factory()->memory()->create();
        DB::rollBack();
        $this->assertDatabaseCount('message_push_deliveries', 1);
        Queue::assertPushed(SendMessagePush::class, 1);
        $this->assertSame($post->id, MessagePushDelivery::query()->sole()->post_id);
    }

    #[Test]
    public function account_post_and_message_preferences_can_be_changed_independently(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('notifications.show'))->assertJsonPath('post_alerts', false)->assertJsonPath('message_alerts', false);
        $this->putJson(route('notifications.update'), ['post_alerts' => true])->assertOk()->assertJsonPath('post_alerts', true)->assertJsonPath('message_alerts', false);
        $this->putJson(route('notifications.update'), ['message_alerts' => true])->assertOk()->assertJsonPath('post_alerts', true)->assertJsonPath('message_alerts', true);
        $this->putJson(route('notifications.update'), ['post_alerts' => false])->assertJsonPath('post_alerts', false)->assertJsonPath('message_alerts', true);
        $this->putJson(route('notifications.update'), [])->assertUnprocessable();
        $this->putJson(route('notifications.update'), ['post_alerts' => 'invalid'])->assertUnprocessable();
        $this->assertDatabaseCount('push_subscriptions', 0);
    }

    #[Test]
    public function message_previews_use_current_sender_names_and_cover_photos_shared_posts_and_deleted_users(): void
    {
        $sender = User::factory()->create(['name' => 'Alex']);
        $recipient = User::factory()->create();
        $conversation = app(\App\Actions\Messaging\StartConversation::class)->handle($sender, $recipient->id);
        $post = Post::factory()->memory()->create();
        $message = app(\App\Actions\Messaging\SendMessage::class)->handle($sender, $conversation, '', (string) \Illuminate\Support\Str::uuid(), postId: $post->id);
        $this->assertSame('Shared a timeline post', app(PushPreview::class)->message($message)['body']);
        $message->attachments()->create([
            'conversation_id' => $conversation->id, 'uploaded_by_participant_id' => $conversation->participantFor($sender)->id,
            'client_request_id' => (string) \Illuminate\Support\Str::uuid(), 'source_hash' => str_repeat('a', 64),
            'disk' => 'message_gcs', 'path' => 'preview-photo.webp', 'thumbnail_path' => 'preview-thumb.webp',
            'width' => 10, 'height' => 10, 'size' => 10, 'retention_hours' => 48,
            'uploaded_at' => now(), 'expires_at' => now()->addHours(48), 'ready_at' => now(),
        ]);
        $this->assertSame('Sent a photo', app(PushPreview::class)->message($message)['body']);
        $message->update(['body' => str_repeat('Hello 🌸 ', 100)]);
        $this->assertLessThanOrEqual(243, mb_strlen(app(PushPreview::class)->message($message)['body']));
        $sender->update(['name' => 'Updated Alex']);
        $this->assertSame('Updated Alex · Remember', app(PushPreview::class)->message($message->fresh())['title']);
        $sender->delete();
        $this->assertSame('Deleted User · Remember', app(PushPreview::class)->message($message->fresh())['title']);
    }
}
