<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Actions\Messaging\StartGroupConversation;
use App\Events\Messaging\MessagingChanged;
use App\Events\Messaging\TypingChanged;
use App\Jobs\SendMessagePush;
use App\Models\Conversation;
use App\Models\MessageAttachment;
use App\Models\MessagePushDelivery;
use App\Models\Post;
use App\Models\PushSubscription;
use App\Models\User;
use App\Policies\PostPolicy;
use App\Services\Messaging\PushSettings;
use App\Services\Messaging\PushTransport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupMessagingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function group(array $users): Conversation
    {
        return app(StartGroupConversation::class)->handle($users[0], 'Family plans', array_map(fn (User $user): int => $user->id, array_slice($users, 1)), (string) Str::uuid());
    }

    private function send(User $user, Conversation $group, string $body = 'Hello everyone'): int
    {
        return $this->actingAs($user)->postJson(route('messaging.send', $group), [
            'body' => $body, 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
    }

    #[Test]
    public function members_can_create_named_groups_and_retries_do_not_duplicate_them(): void
    {
        $users = User::factory()->count(3)->create();
        $direct = app(StartConversation::class)->handle($users[0], $users[1]->id);
        $body = ['name' => 'Family plans', 'recipient_ids' => [$users[1]->id, $users[2]->id], 'client_request_id' => (string) Str::uuid()];
        $response = $this->actingAs($users[0])->postJson(route('messaging.store'), $body)
            ->assertCreated()->assertJsonPath('data.is_group', true)->assertJsonPath('data.name', 'Family plans')
            ->assertJsonCount(3, 'data.participants')->assertJsonPath('data.can_send', true)
            ->assertJsonCount(2, 'data.read_receipts')->assertDontSee($users[1]->email);
        $id = $response->json('data.id');
        $this->postJson(route('messaging.store'), [...$body, 'recipient_ids' => array_reverse($body['recipient_ids'])])
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson(route('messaging.store'), [...$body, 'name' => 'Different group'])->assertUnprocessable();
        $this->assertDatabaseCount('conversations', 2);
        $this->assertSame($direct->id, app(StartConversation::class)->handle($users[1], $users[0]->id)->id);

        foreach ($users as $user) {
            $this->actingAs($user)->getJson(route('messaging.index').'?groups_only=1')
                ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        }
    }

    #[Test]
    public function group_creation_rejects_invalid_names_self_duplicates_missing_users_and_invalid_sizes(): void
    {
        $users = User::factory()->count(4)->create();
        $this->actingAs($users[0]);
        $valid = ['name' => 'Family', 'recipient_ids' => [$users[1]->id, $users[2]->id], 'client_request_id' => (string) Str::uuid()];
        foreach ([
            ['name' => ' '], ['name' => str_repeat('a', 81)], ['recipient_ids' => [$users[1]->id]],
            ['recipient_ids' => [$users[1]->id, $users[1]->id]], ['recipient_ids' => [$users[0]->id, $users[1]->id]],
            ['recipient_ids' => [$users[1]->id, 999999]], ['recipient_ids' => range(100, 119)],
            ['recipient_id' => $users[3]->id], ['client_request_id' => 'invalid'],
        ] as $invalid) {
            $this->postJson(route('messaging.store'), [...$valid, ...$invalid])->assertUnprocessable();
        }
        $this->assertDatabaseCount('conversations', 0);
    }

    #[Test]
    public function every_member_can_send_and_read_but_outsiders_cannot_access_a_group(): void
    {
        $users = User::factory()->count(3)->create();
        $group = $this->group($users->all());
        foreach ($users as $index => $user) {
            $this->send($user, $group, 'Message '.$index);
        }
        foreach ($users as $user) {
            $history = $this->actingAs($user)->getJson(route('messaging.show', $group))->assertOk()->assertJsonCount(3, 'data');
            $this->assertCount(1, array_filter($history->json('data'), fn (array $message): bool => $message['sender']['is_self']));
            $this->getJson(route('messaging.index'))->assertJsonPath('data.0.unread_count', 2);
        }
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson(route('messaging.index'))->assertJsonCount(0, 'data');
        $this->getJson(route('messaging.show', $group))->assertForbidden();
        $this->get(route('messages.show', $group))->assertForbidden();
        $this->postJson(route('messaging.send', $group), ['body' => 'Intrusion', 'client_request_id' => (string) Str::uuid()])->assertForbidden();
        $this->putJson(route('messaging.read', $group), ['read_token' => 'invalid'])->assertForbidden();
        $this->postJson(route('messaging.typing', $group), ['typing' => true, 'source_id' => (string) Str::uuid()])->assertForbidden();
        $this->getJson(route('messaging.typing-status', $group))->assertForbidden();
    }

    #[Test]
    public function read_positions_belong_to_each_recipient_and_incremental_updates_include_them(): void
    {
        [$sender, $first, $second] = User::factory()->count(3)->create()->all();
        $group = $this->group([$sender, $first, $second]);
        $firstMessage = $this->send($sender, $group);
        $firstToken = $this->actingAs($first)->getJson(route('messaging.show', $group))->json('read_token');
        $secondMessage = $this->send($sender, $group, 'Later message');
        $secondToken = $this->actingAs($second)->getJson(route('messaging.show', $group))->json('read_token');
        $this->actingAs($first)->putJson(route('messaging.read', $group), ['read_token' => $firstToken])->assertNoContent();
        $this->actingAs($second)->putJson(route('messaging.read', $group), ['read_token' => $secondToken])->assertNoContent();
        $history = $this->actingAs($sender)->getJson(route('messaging.show', $group).'?after_id='.$secondMessage)
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('conversation.recipient_last_read_message_id', null);
        $receipts = collect($history->json('conversation.read_receipts'))->keyBy('participant_id');
        $this->assertSame($firstMessage, $receipts[$group->participantFor($first)->id]['last_read_message_id']);
        $this->assertSame($secondMessage, $receipts[$group->participantFor($second)->id]['last_read_message_id']);
    }

    #[Test]
    public function typing_and_live_events_include_all_active_members_and_exclude_outsiders(): void
    {
        [$first, $second, $third] = User::factory()->count(3)->create()->all();
        $group = $this->group([$first, $second, $third]);
        foreach ([$first, $third] as $user) {
            $this->actingAs($user)->postJson(route('messaging.typing', $group), ['typing' => true, 'source_id' => (string) Str::uuid()])->assertNoContent();
        }
        $response = $this->actingAs($second)->getJson(route('messaging.typing-status', $group))->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing([$group->participantFor($first)->id, $group->participantFor($third)->id], array_column($response->json('data'), 'participant_id'));
        $event = new MessagingChanged($group->id);
        $this->assertCount(4, $event->broadcastOn());
        foreach ([$first, $second, $third] as $user) {
            $this->assertContains('presence-messaging.users.'.$user->id, array_map(strval(...), $event->broadcastOn()));
        }
        $this->assertCount(1, (new TypingChanged($group->id, $group->participantFor($first)->id, true, (string) Str::uuid()))->broadcastOn());
    }

    #[Test]
    public function group_photos_are_private_until_sent_and_available_only_to_members_after_sending(): void
    {
        [$sender, $second, $third] = User::factory()->count(3)->create()->all();
        $group = $this->group([$sender, $second, $third]);
        config(['filesystems.disks.message_gcs.bucket' => 'group-photos', 'filesystems.disks.gcs.bucket' => 'timeline-photos']);
        Storage::fake('message_gcs');
        $id = $this->actingAs($sender)->postJson(route('messaging.attachments.store', $group), [
            'image' => UploadedFile::fake()->image('group.jpg', 640, 480), 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
        $attachment = MessageAttachment::findOrFail($id);
        $this->actingAs($third)->get(route('messaging.attachments.show', $attachment))->assertNotFound();
        $this->actingAs($sender)->postJson(route('messaging.send', $group), [
            'attachment_ids' => [$id], 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated();
        foreach ([$second, $third] as $member) {
            $this->actingAs($member)->get(route('messaging.attachments.show', $attachment))->assertOk();
        }
        $this->actingAs(User::factory()->create())->get(route('messaging.attachments.show', $attachment))->assertForbidden();
    }

    #[Test]
    public function timeline_posts_can_be_shared_to_a_group_and_private_letters_cannot(): void
    {
        [$sender, $second, $third] = User::factory()->count(3)->create()->all();
        $group = $this->group([$sender, $second, $third]);
        $post = Post::factory()->memory()->create();
        $body = ['conversation_id' => $group->id, 'client_request_id' => (string) Str::uuid(), 'body' => 'For everyone'];
        $id = $this->actingAs($sender)->postJson(route('messaging.share-post', $post), $body)->assertCreated()->json('data.id');
        $this->postJson(route('messaging.share-post', $post), $body)->assertOk()->assertJsonPath('data.id', $id);
        foreach ([$second, $third] as $user) {
            $this->actingAs($user)->getJson(route('messaging.show', $group))->assertJsonPath('data.0.shared_post.status', 'available');
            $this->get(route('messages.shared-post', [$group, $id]))->assertRedirect(route('posts.show', $post));
        }
        $letter = Post::factory()->letter()->for($sender, 'author')->create();
        $this->actingAs($sender)->postJson(route('messaging.share-post', $letter), [
            'conversation_id' => $group->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson(route('messaging.share-post', $post), [
            'conversation_id' => $group->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function sharing_checks_every_group_member_including_the_last_recipient(): void
    {
        [$sender, $second, $third] = User::factory()->count(3)->create()->all();
        $group = $this->group([$sender, $second, $third]);
        $post = Post::factory()->memory()->create();
        $id = $this->actingAs($sender)->postJson(route('messaging.share-post', $post), [
            'conversation_id' => $group->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertCreated()->json('data.id');

        $policy = new class ($third->id) extends PostPolicy {
            public function __construct(private int $deniedId)
            {
            }

            public function view(User $user, Post $post): bool
            {
                return $user->id !== $this->deniedId && parent::view($user, $post);
            }
        };
        $this->app->instance($policy::class, $policy);
        Gate::policy(Post::class, $policy::class);
        $this->postJson(route('messaging.share-post', $post), [
            'conversation_id' => $group->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertForbidden();
        foreach ([$sender, $second, $third] as $user) {
            $this->actingAs($user)->getJson(route('messaging.message', [$group, $id]))
                ->assertOk()->assertJsonPath('data.shared_post.status', 'unavailable')
                ->assertJsonMissingPath('data.shared_post.title');
            $this->get(route('messages.shared-post', [$group, $id]))->assertNotFound();
        }
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function a_deleted_member_does_not_prevent_remaining_members_from_chatting(): void
    {
        [$creator, $second, $third] = User::factory()->count(3)->create()->all();
        $group = $this->group([$creator, $second, $third]);
        $this->send($creator, $group);
        $creator->delete();
        $this->send($third, $group, 'Still here');
        $history = $this->actingAs($second)->getJson(route('messaging.show', $group))
            ->assertOk()->assertJsonPath('conversation.can_send', true)
            ->assertJsonPath('data.0.sender.name', 'Deleted User')->assertJsonCount(1, 'conversation.read_receipts');
        $history->assertDontSee($creator->name);
        $this->assertCount(3, (new MessagingChanged($group->id))->broadcastOn());
        $third->delete();
        $this->actingAs($second)->getJson(route('messaging.show', $group))->assertOk()->assertJsonPath('conversation.can_send', false);
        $this->postJson(route('messaging.send', $group), ['body' => 'Alone', 'client_request_id' => (string) Str::uuid()])->assertForbidden();
    }

    #[Test]
    public function notifications_fan_out_to_opted_in_members_and_each_read_receipt_suppresses_only_its_own_delivery(): void
    {
        [$sender, $first, $second, $paused] = User::factory()->count(4)->create()->all();
        $group = $this->group([$sender, $first, $second, $paused]);
        $keys = VAPID::createVapidKeys();
        config(['queue.default' => 'database', 'remember.push.enabled' => true,
            'remember.push.public_key' => $keys['publicKey'], 'remember.push.private_key' => $keys['privateKey'],
            'remember.push.subject' => 'mailto:groups@example.test']);
        Queue::fake();
        foreach ([$sender, $first, $second, $paused] as $user) {
            $user->settings()->set('notifications.message_alerts', $user->id !== $paused->id);
            PushSubscription::query()->create([
                'user_id' => $user->id, 'device_hash' => hash('sha256', 'device-'.$user->id),
                'endpoint_hash' => hash('sha256', 'endpoint-'.$user->id), 'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.$user->id,
                'public_key' => 'fixture', 'auth_token' => 'fixture',
            ]);
        }
        $this->send($sender, $group);
        $deliveries = MessagePushDelivery::query()->get();
        $this->assertCount(2, $deliveries);
        $token = $this->actingAs($first)->getJson(route('messaging.show', $group))->json('read_token');
        $this->putJson(route('messaging.read', $group), ['read_token' => $token])->assertNoContent();
        $transport = Mockery::mock(PushTransport::class);
        $transport->shouldReceive('send')->once()->withArgs(fn (PushSubscription $device, array $payload): bool => $device->user_id === $second->id && str_contains($payload['title'], 'Family plans'))->andReturn(201);
        $this->travel(6)->seconds();
        foreach ($deliveries as $delivery) {
            (new SendMessagePush($delivery->id))->handle($transport, app(PushSettings::class));
        }
        $this->assertSame(1, MessagePushDelivery::query()->where('state', 'sent')->count());
        $this->assertSame(1, MessagePushDelivery::query()->where('state', 'skipped')->count());
    }
}
