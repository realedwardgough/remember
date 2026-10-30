<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Actions\RemoveTimelineUser;
use App\Enum\UserRole;
use App\Models\Conversation;
use App\Models\ConversationMembership;
use App\Models\Message;
use App\Models\MessagingParticipant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function conversation(User $first, User $second): Conversation
    {
        return app(StartConversation::class)->handle($first, $second->id);
    }

    private function send(User $sender, Conversation $conversation, string $body = 'Hello 👋🏽', ?string $requestId = null): int
    {
        return $this->actingAs($sender)->postJson(route('messaging.send', $conversation), [
            'body' => $body,
            'client_request_id' => $requestId ?? (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
    }

    #[Test]
    public function messaging_endpoints_require_authentication(): void
    {
        $conversation = $this->conversation(User::factory()->create(), User::factory()->create());

        $this->getJson(route('messaging.contacts'))->assertUnauthorized();
        $this->getJson(route('messaging.index'))->assertUnauthorized();
        $this->postJson(route('messaging.store'))->assertUnauthorized();
        $this->getJson(route('messaging.show', $conversation))->assertUnauthorized();
        $this->postJson(route('messaging.send', $conversation))->assertUnauthorized();
        $this->putJson(route('messaging.read', $conversation))->assertUnauthorized();
    }

    #[Test]
    public function contacts_exclude_self_and_do_not_expose_email_or_credentials(): void
    {
        $viewer = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($viewer)->getJson(route('messaging.contacts'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $other->id);

        $this->assertSame(['id', 'name', 'username'], array_keys($response->json('data.0')));
        $response->assertDontSee($other->email);
        $this->getJson(route('messaging.index'))->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseEmpty('messaging_participants');
    }

    #[Test]
    public function inbox_and_contacts_paginate_and_reject_malformed_cursors(): void
    {
        $viewer = User::factory()->create();
        $others = User::factory()->count(51)->create();
        foreach ($others->take(26) as $other) {
            $this->conversation($viewer, $other);
        }
        $this->actingAs($viewer);
        foreach (['messaging.contacts' => 50, 'messaging.index' => 25] as $route => $pageSize) {
            $first = $this->getJson(route($route))->assertOk()->assertJsonCount($pageSize, 'data');
            $this->assertStringContainsString('no-store', $first->headers->get('Cache-Control'));
            $second = $this->getJson(route($route).'?cursor='.urlencode($first->json('next_cursor')))
                ->assertOk()->assertJsonCount(1, 'data');
            $this->assertEmpty(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));

            foreach (['not-a-cursor', base64_encode('{"_pointsToNextItems":true}'), ['invalid']] as $cursor) {
                $this->getJson(route($route).'?'.http_build_query(['cursor' => $cursor]))
                    ->assertUnprocessable()->assertJsonValidationErrors('cursor');
            }
        }
    }

    #[Test]
    public function starting_from_either_direction_returns_one_conversation_and_two_memberships(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $id = $this->actingAs($first)->postJson(route('messaging.store'), ['recipient_id' => $second->id])
            ->assertCreated()->assertJsonPath('data.can_send', true)->json('data.id');

        $this->actingAs($second)->postJson(route('messaging.store'), ['recipient_id' => $first->id])
            ->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messaging_participants', 2);
        $this->assertDatabaseCount('conversation_memberships', 2);
        $conversation = Conversation::findOrFail($id);
        $this->assertLessThan($conversation->second_participant_id, $conversation->first_participant_id);
    }

    #[Test]
    public function database_uniqueness_prevents_a_duplicate_pair(): void
    {
        $conversation = $this->conversation(User::factory()->create(), User::factory()->create());
        $this->expectException(UniqueConstraintViolationException::class);
        Conversation::query()->create($conversation->only(['first_participant_id', 'second_participant_id']));
    }

    #[Test]
    public function invalid_and_self_recipients_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('messaging.store'), ['recipient_id' => $user->id])
            ->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
        $this->postJson(route('messaging.store'), ['recipient_id' => 99999])
            ->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
        $this->postJson(route('messaging.store'), [])->assertUnprocessable();
        $this->assertDatabaseEmpty('conversations');
    }

    #[Test]
    public function participants_can_send_unicode_messages_and_retry_without_duplicates(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = $this->conversation($first, $second);
        $requestId = (string) Str::uuid();
        $body = 'Hello 👨‍👩‍👧‍👦 🇬🇧 👍🏽 <script>alert("x")</script>';
        $id = $this->send($first, $conversation, $body, $requestId);

        $this->postJson(route('messaging.send', $conversation), [
            'body' => $body, 'client_request_id' => strtoupper($requestId),
        ])->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', ['body' => $body]);

        $this->actingAs($second)->getJson(route('messaging.show', $conversation))
            ->assertOk()->assertJsonPath('data.0.body', $body)
            ->assertJsonPath('data.0.sender.name', $first->name)
            ->assertJsonPath('data.0.client_request_id', null);
        $this->getJson(route('messaging.index'))->assertOk()->assertJsonPath('data.0.unread_count', 1);
        $this->actingAs($first)->getJson(route('messaging.index'))->assertJsonPath('data.0.unread_count', 0);
    }

    #[Test]
    public function reusing_a_request_id_for_different_content_or_conversation_is_rejected(): void
    {
        $sender = User::factory()->create();
        $first = $this->conversation($sender, User::factory()->create());
        $second = $this->conversation($sender, User::factory()->create());
        $requestId = (string) Str::uuid();
        $this->send($sender, $first, 'Original', $requestId);

        foreach ([[$first, 'Changed'], [$second, 'Original']] as [$conversation, $body]) {
            $this->postJson(route('messaging.send', $conversation), [
                'body' => $body, 'client_request_id' => $requestId,
            ])->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
        }
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function outsiders_including_administrators_cannot_read_send_or_mark_read(): void
    {
        $first = User::factory()->create();
        $conversation = $this->conversation($first, User::factory()->create());
        $this->send($first, $conversation, 'Private chat');
        $this->seed(RoleSeeder::class);
        $outsider = User::factory()->create();
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);

        foreach ([$outsider, $admin] as $viewer) {
            $this->actingAs($viewer)->getJson(route('messaging.index'))->assertOk()->assertJsonCount(0, 'data');
            $this->getJson(route('messaging.show', $conversation))->assertForbidden();
            $this->postJson(route('messaging.send', $conversation), [
                'body' => 'Intrusion', 'client_request_id' => (string) Str::uuid(),
            ])->assertForbidden();
            $this->putJson(route('messaging.read', $conversation), ['read_token' => 'invalid'])->assertForbidden();
        }
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function sends_validate_content_and_reject_spoofed_sender_or_unimplemented_attachments(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender, User::factory()->create());
        $this->actingAs($sender);

        foreach (['', '   ', str_repeat('x', 10001)] as $body) {
            $this->postJson(route('messaging.send', $conversation), [
                'body' => $body, 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('body');
        }
        foreach (['sender_id' => 100, 'sender_participant_id' => 100, 'conversation_id' => 100, 'attachments' => ['image'], 'post_id' => 1] as $key => $value) {
            $this->postJson(route('messaging.send', $conversation), [
                'body' => 'Hello', 'client_request_id' => (string) Str::uuid(), $key => $value,
            ])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->postJson(route('messaging.send', $conversation), ['body' => 'Hello', 'client_request_id' => 'not-a-uuid'])
            ->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
        $this->assertDatabaseEmpty('messages');
    }

    #[Test]
    public function history_is_paginated_and_new_messages_can_be_retrieved_by_cursor(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversation = $this->conversation($sender, $recipient);
        $senderId = $conversation->participantFor($sender)->id;
        $ids = [];
        for ($index = 0; $index < 55; $index++) {
            $ids[] = Message::query()->create([
                'conversation_id' => $conversation->id, 'sender_participant_id' => $senderId,
                'body' => 'Message '.$index, 'client_request_id' => (string) Str::uuid(),
            ])->id;
        }

        $response = $this->actingAs($recipient)->getJson(route('messaging.show', $conversation))
            ->assertOk()->assertJsonCount(50, 'data')->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0.id', $ids[5])->assertJsonPath('data.49.id', $ids[54]);
        $this->getJson(route('messaging.show', $conversation).'?before_id='.$response->json('next_before_id'))
            ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('has_more', false)->assertJsonPath('data.0.id', $ids[0]);
        $this->getJson(route('messaging.show', $conversation).'?after_id='.$ids[52])
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $ids[53]);
        $this->getJson(route('messaging.show', $conversation).'?before_id=5&after_id=1')->assertUnprocessable();
        $this->assertNull(ConversationMembership::where('participant_id', $conversation->participantFor($recipient)->id)->value('last_read_message_id'));
    }

    #[Test]
    public function read_positions_are_explicit_monotonic_and_do_not_include_later_messages(): void
    {
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $conversation = $this->conversation($sender, $reader);
        $first = $this->send($sender, $conversation);
        $oldToken = $this->actingAs($reader)->getJson(route('messaging.show', $conversation))->json('read_token');
        $second = $this->send($sender, $conversation, 'Second');
        $newToken = $this->actingAs($reader)->getJson(route('messaging.show', $conversation))->json('read_token');
        $this->send($sender, $conversation, 'Not loaded');
        $this->actingAs($reader)->putJson(route('messaging.read', $conversation), ['read_token' => $oldToken])->assertNoContent();
        $this->assertDatabaseHas('conversation_memberships', [
            'participant_id' => $conversation->participantFor($reader)->id, 'last_read_message_id' => $first,
        ]);
        $this->putJson(route('messaging.read', $conversation), ['read_token' => $newToken])->assertNoContent();
        $this->putJson(route('messaging.read', $conversation), ['read_token' => $oldToken])->assertNoContent();
        $this->assertDatabaseHas('conversation_memberships', [
            'participant_id' => $conversation->participantFor($reader)->id, 'last_read_message_id' => $second,
        ]);
        $this->getJson(route('messaging.index'))->assertJsonPath('data.0.unread_count', 1);
    }

    #[Test]
    public function read_tokens_cannot_be_forged_reused_by_other_users_or_other_conversations(): void
    {
        $sender = User::factory()->create();
        $reader = User::factory()->create();
        $conversation = $this->conversation($sender, $reader);
        $this->send($sender, $conversation);
        $token = $this->actingAs($reader)->getJson(route('messaging.show', $conversation))->json('read_token');
        $other = $this->conversation($reader, User::factory()->create());

        $this->putJson(route('messaging.read', $other), ['read_token' => $token])->assertUnprocessable();
        $this->putJson(route('messaging.read', $conversation), ['read_token' => 'forged'])->assertUnprocessable();
        $this->actingAs($sender)->putJson(route('messaging.read', $conversation), ['read_token' => $token])->assertUnprocessable();
        $this->travel(25)->hours();
        $this->actingAs($reader)->putJson(route('messaging.read', $conversation), ['read_token' => $token])->assertUnprocessable();
        $this->assertSame(0, ConversationMembership::whereNotNull('last_read_message_id')->count());
    }

    #[Test]
    public function deleted_users_history_survives_without_identity_snapshots_or_new_sends(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::ADMIN->value);
        $remaining = User::factory()->create();
        $deleted = User::factory()->create(['name' => 'Former Name', 'username' => 'former-user']);
        $conversation = $this->conversation($remaining, $deleted);
        $requestId = (string) Str::uuid();
        $this->send($remaining, $conversation, 'My message', $requestId);
        $this->send($deleted, $conversation, 'Their message');
        $participantId = $conversation->participantFor($deleted)->id;
        DB::table('sessions')->insert([
            'id' => 'deleted-user-session', 'user_id' => $deleted->id, 'payload' => '', 'last_activity' => time(),
        ]);

        app(RemoveTimelineUser::class)->handle($admin, $deleted);
        $this->assertDatabaseHas('messaging_participants', ['id' => $participantId, 'user_id' => null]);
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('conversation_memberships', 2);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user-session']);

        $this->actingAs($remaining)->getJson(route('messaging.show', $conversation))
            ->assertOk()->assertJsonPath('conversation.can_send', false)
            ->assertJsonPath('data.1.sender.name', 'Deleted User')
            ->assertJsonPath('data.1.sender.username', null)
            ->assertDontSee('Former Name')->assertDontSee('former-user')->assertDontSee($deleted->email);
        $this->getJson(route('messaging.index'))->assertOk()
            ->assertJsonPath('data.0.latest_message.sender.name', 'Deleted User')
            ->assertDontSee('Former Name')->assertDontSee('former-user');
        $this->postJson(route('messaging.send', $conversation), [
            'body' => 'No longer allowed', 'client_request_id' => (string) Str::uuid(),
        ])->assertForbidden();
        // A retry acknowledges an already-saved message without creating a new send.
        $this->postJson(route('messaging.send', $conversation), [
            'body' => 'My message', 'client_request_id' => $requestId,
        ])->assertOk();
        $replacement = User::factory()->create(['name' => 'Former Name', 'username' => 'former-user', 'email' => $deleted->email]);
        $this->actingAs($replacement)->getJson(route('messaging.show', $conversation))->assertForbidden();
        $this->getJson(route('messaging.index'))->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('messages', 2);
    }

    #[Test]
    public function history_is_preserved_even_when_both_users_are_deleted(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = $this->conversation($first, $second);
        $this->send($first, $conversation);
        $first->delete();
        $second->delete();
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('conversation_memberships', 2);
        $this->assertSame(2, MessagingParticipant::whereNull('user_id')->count());
    }

    #[Test]
    public function message_sends_are_rate_limited(): void
    {
        $sender = User::factory()->create();
        $conversation = $this->conversation($sender, User::factory()->create());
        $this->actingAs($sender);
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('messaging.send', $conversation), [])->assertUnprocessable();
        }
        $this->postJson(route('messaging.send', $conversation), [])->assertTooManyRequests();
    }
}
