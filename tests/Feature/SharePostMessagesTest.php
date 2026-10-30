<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Message;
use App\Models\MessagePostShare;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SharePostMessagesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function share(User $sender, User $recipient, Post $post, string $body = '', ?string $requestId = null): int
    {
        return $this->actingAs($sender)->postJson(route('messaging.share-post', $post->id), [
            'recipient_id' => $recipient->id, 'body' => $body, 'client_request_id' => $requestId ?? (string) Str::uuid(),
        ])->assertCreated()->json('data.id');
    }

    #[Test]
    public function members_can_share_another_authors_post_with_an_optional_note_and_open_the_source(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->memory()->create(['title' => 'A family picnic', 'content' => 'Original story']);
        $messageId = $this->share($sender, $recipient, $post, 'Thought you would like this 💕');
        $message = Message::findOrFail($messageId);
        $this->actingAs($recipient)->getJson(route('messaging.show', $message->conversation_id))
            ->assertOk()->assertJsonPath('data.0.body', 'Thought you would like this 💕')
            ->assertJsonPath('data.0.shared_post.status', 'available')
            ->assertJsonPath('data.0.shared_post.title', 'A family picnic')
            ->assertJsonPath('data.0.shared_post.url', route('messages.shared-post', [$message->conversation_id, $message->id]));
        $this->get(route('messages.shared-post', [$message->conversation_id, $message->id]))->assertRedirect(route('posts.show', $post));
        $this->get(route('posts.show', $post))->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('posts/Show')->where('post.id', $post->id)
                ->where('post.title', 'A family picnic')->where('post.content', 'Original story')->where('post.canShare', true)->where('post.canEdit', false),
        );
        $this->assertDatabaseHas('message_post_shares', ['message_id' => $messageId, 'post_id' => $post->id, 'original_post_id' => $post->id]);
    }

    #[Test]
    public function sharing_requires_login_a_real_recipient_and_another_user(): void
    {
        $sender = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $this->postJson(route('messaging.share-post', $post), [])->assertUnauthorized();
        $this->get(route('posts.show', $post))->assertRedirect(route('login'));
        foreach ([$sender->id, 999999] as $recipient) {
            $this->actingAs($sender)->postJson(route('messaging.share-post', $post), [
                'recipient_id' => $recipient, 'client_request_id' => (string) Str::uuid(),
            ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
        }
        $this->assertDatabaseEmpty('conversations');
        $this->assertDatabaseEmpty('messages');
    }

    #[Test]
    public function private_letters_cannot_be_shared_even_by_the_author_and_failures_leave_no_chat(): void
    {
        $author = User::factory()->create();
        $recipient = User::factory()->create();
        $letter = Post::factory()->for($author, 'author')->create(['post_type' => TimelinePostType::LETTER, 'title' => 'Secret letter']);
        foreach ([$author, User::factory()->create()] as $sender) {
            $this->actingAs($sender)->postJson(route('messaging.share-post', $letter), [
                'recipient_id' => $recipient->id, 'client_request_id' => (string) Str::uuid(),
            ])->assertForbidden()->assertDontSee('Secret letter');
        }
        $this->assertDatabaseEmpty('conversations');
        $this->assertDatabaseEmpty('messages');
        $this->assertDatabaseEmpty('message_post_shares');
        $this->actingAs($recipient)->get(route('posts.show', $letter))->assertForbidden()->assertDontSee('Secret letter');
        $this->actingAs($author)->get(route('posts.show', $letter))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('post.canShare', false));
    }

    #[Test]
    public function sharing_reuses_the_direct_chat_and_retries_do_not_duplicate_messages(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $requestId = (string) Str::uuid();
        $id = $this->share($sender, $recipient, $post, 'Look at this', $requestId);
        $this->postJson(route('messaging.share-post', $post), [
            'recipient_id' => $recipient->id, 'body' => 'Look at this', 'client_request_id' => $requestId,
        ])->assertOk()->assertJsonPath('data.id', $id);
        $this->share($recipient, $sender, $post);
        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('messages', 2);
        $this->assertDatabaseCount('message_post_shares', 2);
        $other = Post::factory()->memory()->create();
        $this->actingAs($sender)->postJson(route('messaging.share-post', $other), [
            'recipient_id' => $recipient->id, 'body' => 'Look at this', 'client_request_id' => $requestId,
        ])->assertUnprocessable()->assertJsonValidationErrors('client_request_id');
    }

    #[Test]
    public function deleted_posts_have_only_an_unavailable_card_and_can_acknowledge_the_original_send(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->memory()->create(['title' => 'Removed title', 'content' => 'Removed content']);
        $requestId = (string) Str::uuid();
        $id = $this->share($sender, $recipient, $post, requestId: $requestId);
        $message = Message::findOrFail($id);
        $post->delete();
        $this->actingAs($recipient)->getJson(route('messaging.show', $message->conversation_id))
            ->assertJsonPath('data.0.shared_post', ['status' => 'unavailable', 'label' => 'Post unavailable'])
            ->assertDontSee('Removed title')->assertDontSee('Removed content');
        $this->getJson(route('messaging.index'))->assertJsonPath('data.0.latest_message.shared_post.status', 'unavailable')->assertDontSee('Removed title');
        $this->get(route('posts.show', $post))->assertNotFound();
        $this->get(route('messages.shared-post', [$message->conversation_id, $id]))->assertNotFound();
        $post->forceDelete();
        $this->assertNull(MessagePostShare::query()->sole()->post_id);
        $this->actingAs($sender)->postJson(route('messaging.share-post', $post->id), [
            'recipient_id' => $recipient->id, 'client_request_id' => $requestId,
        ])->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.shared_post.status', 'unavailable');
        $this->postJson(route('messaging.share-post', $post->id), [
            'recipient_id' => $recipient->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function current_post_data_is_used_and_a_post_changed_to_a_letter_exposes_no_cached_fields(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->for($sender, 'author')->memory()->create(['title' => 'Original title']);
        $id = $this->share($sender, $recipient, $post);
        $conversation = Message::findOrFail($id)->conversation_id;
        $post->update(['title' => 'Current title']);
        $this->actingAs($recipient)->getJson(route('messaging.message', [$conversation, $id]))
            ->assertJsonPath('data.shared_post.title', 'Current title')->assertDontSee('Original title');
        $post->update(['post_type' => TimelinePostType::LETTER, 'title' => 'Now private', 'content' => 'Private body']);
        foreach ([$sender, $recipient] as $viewer) {
            $this->actingAs($viewer)->getJson(route('messaging.message', [$conversation, $id]))
                ->assertJsonPath('data.shared_post', ['status' => 'unavailable', 'label' => 'Post unavailable'])
                ->assertDontSee('Now private')->assertDontSee('Current title')->assertDontSee('Private body');
        }
        $this->actingAs($recipient)->get(route('posts.show', $post))->assertForbidden();
        $this->get(route('messages.shared-post', [$conversation, $id]))->assertNotFound();
    }

    #[Test]
    public function recipient_authorization_is_checked_when_sending_and_when_rendering_existing_shares(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $id = $this->share($sender, $recipient, $post);
        $policy = new class ($recipient->id) extends PostPolicy {
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
        $this->actingAs($sender)->postJson(route('messaging.share-post', $post), [
            'recipient_id' => $recipient->id, 'client_request_id' => (string) Str::uuid(),
        ])->assertForbidden();
        $message = Message::findOrFail($id);
        foreach ([$sender, $recipient] as $viewer) {
            $this->actingAs($viewer)->getJson(route('messaging.message', [$message->conversation_id, $id]))
                ->assertJsonPath('data.shared_post.status', 'unavailable')->assertJsonMissingPath('data.shared_post.title');
            $this->get(route('messages.shared-post', [$message->conversation_id, $id]))->assertNotFound();
        }
        $this->assertDatabaseCount('messages', 1);
    }

    #[Test]
    public function outsiders_cannot_retrieve_a_shared_message_even_when_the_source_post_is_visible(): void
    {
        $sender = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $id = $this->share($sender, User::factory()->create(), $post);
        $message = Message::findOrFail($id);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('posts.show', $post))->assertOk();
        $this->getJson(route('messaging.message', [$message->conversation_id, $id]))->assertForbidden();
        $this->get(route('messages.shared-post', [$message->conversation_id, $id]))->assertForbidden();
    }

    #[Test]
    public function source_media_stays_in_permanent_storage_and_is_not_copied_into_chat_photos(): void
    {
        Storage::fake('gcs');
        Storage::fake('message_gcs');
        $post = Post::factory()->memory()->create();
        Media::factory()->for($post)->create(['disk' => 'gcs', 'path' => 'posts/permanent.webp', 'mime_type' => 'image/webp']);
        Storage::disk('gcs')->put('posts/permanent.webp', 'permanent image bytes');
        $this->share(User::factory()->create(), User::factory()->create(), $post);
        $this->assertDatabaseEmpty('message_attachments');
        $this->assertSame([], Storage::disk('message_gcs')->allFiles());
        Storage::disk('gcs')->assertExists('posts/permanent.webp');
    }

    #[Test]
    public function shared_posts_remain_usable_by_a_surviving_authorized_member_after_sender_deletion(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $post = Post::factory()->memory()->create();
        $id = $this->share($sender, $recipient, $post);
        $message = Message::findOrFail($id);
        $sender->delete();
        $this->actingAs($recipient)->getJson(route('messaging.message', [$message->conversation_id, $id]))
            ->assertJsonPath('data.sender.name', 'Deleted User')->assertJsonPath('data.shared_post.status', 'available');
    }
}
