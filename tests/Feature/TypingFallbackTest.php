<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Messaging\StartConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TypingFallbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function typing_works_without_websockets_is_participant_only_and_expires_without_history(): void
    {
        config(['broadcasting.default' => 'null']);
        Queue::fake();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($first, $second->id);
        $source = (string) Str::uuid();
        $this->actingAs($first)->postJson(route('messaging.typing', $conversation), ['typing' => true, 'source_id' => $source])->assertNoContent();
        $this->getJson(route('messaging.typing-status', $conversation))->assertJsonCount(0, 'data');
        $this->actingAs($second)->getJson(route('messaging.typing-status', $conversation))->assertOk()
            ->assertJsonPath('data.0.participant_id', $conversation->participantFor($first)->id)
            ->assertJsonPath('data.0.source_id', $source)->assertJsonPath('data.0.typing', true)
            ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($first->email);
        $this->actingAs(User::factory()->create())->getJson(route('messaging.typing-status', $conversation))->assertForbidden();
        $this->travel(7)->seconds();
        $this->actingAs($second)->getJson(route('messaging.typing-status', $conversation))->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('message_push_deliveries', 0);
    }

    #[Test]
    public function stopping_one_tab_keeps_other_tabs_and_deletion_clears_visible_typing(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($first, $second->id);
        $one = (string) Str::uuid();
        $two = (string) Str::uuid();
        $this->actingAs($first);
        foreach ([$one, $two] as $source) {
            $this->postJson(route('messaging.typing', $conversation), ['typing' => true, 'source_id' => $source])->assertNoContent();
        }
        $this->postJson(route('messaging.typing', $conversation), ['typing' => false, 'source_id' => $one])->assertNoContent();
        $this->actingAs($second)->getJson(route('messaging.typing-status', $conversation))->assertJsonCount(1, 'data')->assertJsonPath('data.0.source_id', $two);
        $first->delete();
        $this->getJson(route('messaging.typing-status', $conversation))->assertJsonCount(0, 'data');
    }
    #[Test]
    public function typing_rate_limits_do_not_consume_message_sending_or_status_limits(): void
    {
        config(['broadcasting.default' => 'null']);
        $sender = User::factory()->create();
        $conversation = app(StartConversation::class)->handle($sender, User::factory()->create()->id);
        $this->actingAs($sender);
        $body = ['typing' => true, 'source_id' => (string) Str::uuid()];
        for ($i = 0; $i < 90; $i++) {
            $this->postJson(route('messaging.typing', $conversation), $body)->assertNoContent();
        }
        $this->postJson(route('messaging.typing', $conversation), $body)->assertTooManyRequests();
        $this->getJson(route('messaging.typing-status', $conversation))->assertOk();
        $this->postJson(route('messaging.send', $conversation), ['body' => 'Sending still works', 'client_request_id' => (string) Str::uuid()])->assertCreated();
    }

}
