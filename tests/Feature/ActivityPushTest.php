<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateComment;
use App\Actions\TogglePostHeart;
use App\DTOs\CreateCommentDTO;
use App\Enum\TimelinePostType;
use App\Jobs\SendMessagePush;
use App\Models\ActivityNotification;
use App\Models\MessagePushDelivery;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Messaging\ActivityPush;
use App\Services\Messaging\PushSettings;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityPushTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $keys = VAPID::createVapidKeys();
        config(['queue.default' => 'database', 'remember.push.enabled' => true,
            'remember.push.subject' => 'mailto:activity@example.test',
            'remember.push.public_key' => $keys['publicKey'], 'remember.push.private_key' => $keys['privateKey']]);
        Queue::fake();
    }

    private function device(User $user, string $name = 'phone'): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id, 'device_hash' => hash('sha256', $name.$user->id),
            'endpoint_hash' => hash('sha256', 'endpoint-'.$name.$user->id),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$name.$user->id,
            'public_key' => 'fixture-public-key', 'auth_token' => 'fixture-auth-token',
        ]);
    }

    private function interact(Post $post, User $actor, bool $like = false): ActivityNotification
    {
        if ($like) {
            app(TogglePostHeart::class)->handle($post, $actor);
        } else {
            app(CreateComment::class)->handle($post, new CreateCommentDTO($actor->id, 'Lovely!'));
        }
        return ActivityNotification::query()->latest('id')->firstOrFail();
    }

    private function work(MessagePushDelivery $delivery, bool $expected, int $status = 201): array
    {
        $this->travelTo($delivery->next_attempt_at->addSecond());
        $payload = [];
        $transport = Mockery::mock(PushTransport::class);
        if ($expected) {
            $transport->shouldReceive('send')->once()->andReturnUsing(function ($device, $data) use (&$payload, $status): int {
                $payload = $data;
                return $status;
            });
        } else {
            $transport->shouldNotReceive('send');
        }
        (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        return $payload;
    }

    #[Test]
    public function likes_appear_in_the_list_and_count_and_open_the_post(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create(['name' => 'Sam']);
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $activity = $this->interact($post, $actor, true);
        $this->actingAs($owner)->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 1)->where('activityNotifications.data.0.kind', 'like')
            ->where('activityNotifications.data.0.author', 'Sam')->where('activityNotifications.data.0.preview', null));
        $this->post(route('activity.open', $activity->id))->assertRedirect(route('posts.show', $post));
        $this->assertNotNull($activity->fresh()->read_at);
        $this->actingAs($actor)->post(route('activity.open', $activity->id))->assertNotFound();
    }

    #[Test]
    public function comments_and_likes_send_previews_only_to_the_owners_enabled_devices(): void
    {
        foreach ([false, true] as $like) {
            $owner = User::factory()->create();
            $owner->settings()->set('notifications.activity_alerts', true);
            $this->device($owner);
            $this->device($owner, 'tablet');
            $actor = User::factory()->create(['name' => 'Alex']);
            $this->device($actor);
            $post = Post::factory()->memory()->for($owner, 'author')->create(['title' => 'First steps']);
            $activity = $this->interact($post, $actor, $like);
            app(ActivityPush::class)->prepare($activity);
            $deliveries = MessagePushDelivery::query()->where('activity_notification_id', $activity->id)->get();
            $this->assertCount(2, $deliveries);
            foreach ($deliveries as $delivery) {
                $this->assertSame($owner->id, PushSubscription::findOrFail($delivery->push_subscription_id)->user_id);
                $payload = $this->work($delivery, true);
                $this->assertSame($like ? 'Alex liked your post' : 'Alex commented on your post', $payload['title']);
                $this->assertSame('First steps', $payload['body']);
                $this->assertSame('/notifications', $payload['url']);
                $this->assertSame('remember-activity-'.$activity->id, $payload['tag']);
                $this->assertSame('sent', $delivery->fresh()->state);
            }
        }
    }

    #[Test]
    public function own_interactions_and_opted_out_accounts_never_queue_activity_pushes(): void
    {
        $owner = User::factory()->create();
        $this->device($owner);
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $actor = User::factory()->create();
        $this->interact($post, $actor);
        $this->interact($post, $actor, true);
        app(CreateComment::class)->handle($post, new CreateCommentDTO($owner->id, 'Own comment'));
        app(TogglePostHeart::class)->handle($post, $owner);
        $this->assertDatabaseCount('activity_notifications', 2);
        $this->assertDatabaseEmpty('message_push_deliveries');
    }

    #[Test]
    public function unliking_removes_activity_and_its_pending_delivery(): void
    {
        $owner = User::factory()->create();
        $owner->settings()->set('notifications.activity_alerts', true);
        $this->device($owner);
        $actor = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $activity = $this->interact($post, $actor, true);
        $delivery = MessagePushDelivery::query()->sole();
        app(TogglePostHeart::class)->handle($post, $actor);
        $this->assertDatabaseMissing('activity_notifications', ['id' => $activity->id]);
        $this->assertDatabaseMissing('message_push_deliveries', ['id' => $delivery->id]);
        $this->work($delivery, false);
        $this->actingAs($owner)->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 0)->has('activityNotifications.data', 0));
    }

    #[Test]
    public function reading_opting_out_deletion_and_private_posts_suppress_pending_alerts(): void
    {
        foreach (['read', 'opt-out', 'post-deleted', 'private', 'actor-deleted', 'device-expired'] as $reason) {
            $owner = User::factory()->create();
            $owner->settings()->set('notifications.activity_alerts', true);
            $device = $this->device($owner);
            $actor = User::factory()->create();
            $post = Post::factory()->memory()->for($owner, 'author')->create();
            $activity = $this->interact($post, $actor);
            $delivery = MessagePushDelivery::query()->where('activity_notification_id', $activity->id)->sole();
            match ($reason) {
                'read' => $activity->update(['read_at' => now()]),
                'opt-out' => $owner->settings()->set('notifications.activity_alerts', false),
                'post-deleted' => $post->delete(),
                'private' => $post->update(['post_type' => TimelinePostType::LETTER]),
                'actor-deleted' => $actor->delete(),
                'device-expired' => $device->update(['expires_at' => now()]),
            };
            $this->work($delivery, false);
            $this->assertSame('skipped', $delivery->fresh()->state, $reason);
        }
    }

    #[Test]
    public function transient_activity_delivery_failures_retry_and_expired_subscriptions_are_removed(): void
    {
        $owner = User::factory()->create();
        $owner->settings()->set('notifications.activity_alerts', true);
        $device = $this->device($owner);
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $this->interact($post, User::factory()->create());
        $delivery = MessagePushDelivery::query()->sole();
        $this->work($delivery, true, 503);
        $this->assertSame('pending', $delivery->fresh()->state);
        $this->assertSame(1, $delivery->fresh()->attempts);
        $this->work($delivery->fresh(), true, 410);
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $device->id]);
    }

    #[Test]
    public function activity_outbox_and_enqueueing_are_transactional(): void
    {
        $owner = User::factory()->create();
        $owner->settings()->set('notifications.activity_alerts', true);
        $this->device($owner);
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $actor = User::factory()->create();
        DB::beginTransaction();
        $this->interact($post, $actor);
        Queue::assertNotPushed(SendMessagePush::class);
        DB::rollBack();
        $this->assertDatabaseEmpty('activity_notifications');
        $this->assertDatabaseEmpty('message_push_deliveries');
        DB::beginTransaction();
        $this->interact($post, $actor, true);
        Queue::assertNotPushed(SendMessagePush::class);
        DB::commit();
        Queue::assertPushed(SendMessagePush::class, 1);
    }

    #[Test]
    public function activity_push_preference_is_independent_from_messages_and_new_posts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('notifications.show'))->assertJsonPath('activity_alerts', false);
        $this->putJson(route('notifications.update'), ['activity_alerts' => true])->assertOk()
            ->assertJsonPath('activity_alerts', true)->assertJsonPath('post_alerts', false)->assertJsonPath('message_alerts', false);
        $this->putJson(route('notifications.update'), ['post_alerts' => true])->assertJsonPath('activity_alerts', true);
        $this->putJson(route('notifications.update'), ['activity_alerts' => false])->assertJsonPath('post_alerts', true);
        $this->putJson(route('notifications.update'), ['activity_alerts' => 'invalid'])->assertUnprocessable();
        $this->putJson(route('notifications.update'), [])->assertUnprocessable();
    }
}
