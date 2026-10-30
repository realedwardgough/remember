<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Events\Messaging\MessagingChanged;
use App\Events\Messaging\TypingChanged;
use App\Jobs\DisconnectMessagingUser;
use App\Models\User;
use App\Services\Messaging\MessagingConnections;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class MessagingRealtimeTest extends TestCase
{
    use DatabaseMigrations;

    private function configureBroadcasting(): void
    {
        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-public-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-only-signing-secret');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app');
        require base_path('routes/channels.php');
    }

    #[Test]
    public function presence_channels_authorize_only_the_user_or_conversation_participants(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $outsider = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($first, $second->id);
        $this->configureBroadcasting();
        $channel = 'presence-messaging.conversations.'.$conversation->id;
        $data = ['socket_id' => '123.456', 'channel_name' => $channel];

        $this->postJson('/broadcasting/auth', $data)->assertForbidden();
        $response = $this->actingAs($first)->postJson('/broadcasting/auth', $data)->assertOk();
        $channelData = $response->json('channel_data');
        $this->assertSame(['user_id' => (string) $first->id, 'user_info' => ['id' => $first->id]], json_decode($channelData, true));
        $response->assertJsonPath('auth', 'test-public-key:'.hash_hmac('sha256', '123.456:'.$channel.':'.$channelData, 'test-only-signing-secret'));
        $response->assertDontSee($first->name)->assertDontSee($first->email);
        $this->actingAs($outsider)->postJson('/broadcasting/auth', $data)->assertForbidden();
        $this->actingAs($first)->postJson('/broadcasting/auth', [
            'socket_id' => '123.456', 'channel_name' => 'presence-messaging.users.'.$second->id,
        ])->assertForbidden();
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456', 'channel_name' => 'presence-messaging.users.'.$first->id,
        ])->assertOk();
    }

    #[Test]
    public function messages_dispatch_once_after_the_outer_transaction_commits_and_never_on_rollback(): void
    {
        $sender = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($sender, User::factory()->create()->id);
        Event::fake([MessagingChanged::class]);
        $requestId = (string) Str::uuid();
        DB::beginTransaction();
        $message = app(SendMessage::class)->handle($sender, $conversation, 'Persist before broadcasting', $requestId);
        Event::assertNotDispatched(MessagingChanged::class);
        DB::commit();
        Event::assertDispatched(MessagingChanged::class, fn (MessagingChanged $event): bool => $event->messageId === $message->id);
        app(SendMessage::class)->handle($sender, $conversation, 'Persist before broadcasting', $requestId);
        Event::assertDispatchedTimes(MessagingChanged::class, 1);

        Event::fake([MessagingChanged::class]);
        DB::beginTransaction();
        app(SendMessage::class)->handle($sender, $conversation, 'Will roll back', (string) Str::uuid());
        DB::rollBack();
        Event::assertNotDispatched(MessagingChanged::class);
        $this->assertDatabaseMissing('messages', ['body' => 'Will roll back']);
    }

    #[Test]
    public function a_broadcast_failure_does_not_undo_a_saved_message(): void
    {
        $sender = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($sender, User::factory()->create()->id);
        Event::listen(MessagingChanged::class, function (): void {
            throw new RuntimeException('Simulated broadcaster failure');
        });
        $message = app(SendMessage::class)->handle($sender, $conversation, 'Still saved', (string) Str::uuid());
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'body' => 'Still saved']);
    }

    #[Test]
    public function typing_is_authenticated_ephemeral_and_cannot_spoof_another_participant(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($sender, $recipient->id);
        $source = (string) Str::uuid();
        Event::fake([TypingChanged::class]);
        $this->postJson(route('messaging.typing', $conversation), ['typing' => true, 'source_id' => $source])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('messaging.typing', $conversation), [
            'typing' => true, 'source_id' => $source,
        ])->assertForbidden();
        $this->actingAs($sender)->postJson(route('messaging.typing', $conversation), [
            'typing' => true, 'source_id' => $source, 'participant_id' => $conversation->participantFor($recipient)->id,
        ])->assertUnprocessable();
        foreach ([true, false] as $typing) {
            $this->postJson(route('messaging.typing', $conversation), ['typing' => $typing, 'source_id' => $source])->assertNoContent();
        }
        Event::assertDispatched(TypingChanged::class, fn (TypingChanged $event): bool => $event->participantId === $conversation->participantFor($sender)->id && $event->sourceId === $source && $event->typing);
        Event::assertDispatchedTimes(TypingChanged::class, 2);
        $this->assertDatabaseEmpty('messages');
        $recipient->delete();
        $this->postJson(route('messaging.typing', $conversation), ['typing' => true, 'source_id' => $source])->assertForbidden();
    }

    #[Test]
    public function events_contain_identifiers_only_and_exclude_deleted_users_from_delivery(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($first, $second->id);
        $event = new MessagingChanged($conversation->id, 123);
        $this->assertSame(['conversation_id' => $conversation->id, 'message_id' => 123], $event->broadcastWith());
        $this->assertCount(3, $event->broadcastOn());
        $onlySelf = new MessagingChanged($conversation->id, onlyUserId: $first->id);
        $this->assertSame(['presence-messaging.users.'.$first->id], array_map(strval(...), $onlySelf->broadcastOn()));
        $typing = new TypingChanged($conversation->id, $conversation->participantFor($first)->id, true, (string) Str::uuid());
        $this->assertSame(['conversation_id', 'participant_id', 'typing', 'source_id'], array_keys($typing->broadcastWith()));
        $second->delete();
        $this->assertSame(['presence-messaging.users.'.$first->id], array_map(strval(...), $event->broadcastOn()));
        $this->assertSame([], $typing->broadcastOn());
        $first->delete();
        $this->assertSame([], $event->broadcastOn());
    }

    #[Test]
    public function logout_and_deletion_request_socket_revocation(): void
    {
        $user = User::factory()->create();
        $connections = Mockery::mock(MessagingConnections::class);
        $connections->shouldReceive('disconnect')->with($user->id)->twice();
        $this->app->instance(MessagingConnections::class, $connections);
        event(new Logout('web', $user));
        $user->delete();
    }

    #[Test]
    public function server_side_socket_revocation_uses_the_verified_user_identity(): void
    {
        $this->configureBroadcasting();
        $pusher = Mockery::mock();
        $pusher->shouldReceive('terminateUserConnections')->once()->with('42');
        $broadcaster = Mockery::mock();
        $broadcaster->shouldReceive('getPusher')->once()->andReturn($pusher);
        Broadcast::shouldReceive('connection')->once()->with('reverb')->andReturn($broadcaster);
        (new DisconnectMessagingUser(42))->handle();
    }

    #[Test]
    public function session_probe_requires_authentication_and_returns_only_identity_and_unread_count(): void
    {
        $this->getJson(route('messaging.session'))->assertUnauthorized();
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('messaging.session'))->assertOk()->assertExactJson(['user_id' => $user->id, 'unread_count' => 0]);
    }
}
