<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\SendMessage;
use App\Actions\Messaging\StartConversation;
use App\Models\User;
use App\Queries\GetUnreadMessageCount;
use App\Services\Messaging\ReadReceiptToken;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessagesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function message_pages_require_login_and_participant_access(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($first, $second->id);

        $this->get(route('messages.index'))->assertRedirect(route('login'));
        $this->get(route('messages.show', $conversation))->assertRedirect(route('login'));
        $this->actingAs($first)->get(route('messages.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Messages')->where('conversationId', null)->where('unreadMessagesCount', 0));
        $this->get(route('messages.show', $conversation))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Messages')->where('conversationId', $conversation->id));
        $this->actingAs(User::factory()->create())->get(route('messages.show', $conversation))->assertForbidden();
    }

    #[Test]
    public function shared_and_api_unread_totals_only_count_incoming_unread_messages_for_the_viewer(): void
    {
        $viewer = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($viewer, $first->id);
        $other = app(StartConversation::class)->handle($viewer, $second->id);
        $unrelated = app(StartConversation::class)->handle($first, $second->id);
        $send = app(SendMessage::class);
        $one = $send->handle($first, $conversation, 'First incoming', (string) Str::uuid());
        $send->handle($first, $conversation, 'Second incoming', (string) Str::uuid());
        $send->handle($viewer, $conversation, 'Outgoing', (string) Str::uuid());
        $send->handle($second, $other, 'Third incoming', (string) Str::uuid());
        $send->handle($first, $unrelated, 'Not for the viewer', (string) Str::uuid());

        $count = app(GetUnreadMessageCount::class);
        $this->assertSame(3, $count->handle($viewer));
        $this->actingAs($viewer)->getJson(route('messaging.index'))->assertJsonPath('unread_count', 3);
        $token = app(ReadReceiptToken::class)->issue($viewer, $conversation, $one->id);
        app(MarkConversationRead::class)->handle($viewer, $conversation, $token);
        $this->assertSame(2, $count->handle($viewer));
        $this->get(route('messages.index'))->assertInertia(fn (Assert $page) => $page->where('unreadMessagesCount', 2));
        $first->delete();
        // Deleting an account preserves unread conversation history as well as the messages themselves.
        $this->assertSame(2, $count->handle($viewer));
    }
}
