<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\CreateComment;
use App\DTOs\CreateCommentDTO;
use App\Enum\TimelinePostType;
use App\Models\ActivityNotification;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityNotificationsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function commentOn(Post $post, ?User $commenter = null): ActivityNotification
    {
        $commenter ??= User::factory()->create();
        $comment = app(CreateComment::class)->handle($post, new CreateCommentDTO($commenter->id, 'A lovely memory.'));

        return ActivityNotification::query()->where('comment_id', $comment->id)->sole();
    }

    #[Test]
    public function comments_notify_only_the_post_author_without_push_enabled(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();

        $this->actingAs($commenter)->post(route('posts.comments.store', $post), ['content' => 'Hello!'])
            ->assertRedirect();

        $this->assertDatabaseHas('activity_notifications', ['user_id' => $owner->id, 'read_at' => null]);
        $this->assertDatabaseCount('activity_notifications', 1);
        $this->actingAs($owner)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 1)->where('unreadMessagesCount', 0));
        $this->actingAs($commenter)->get(route('home'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 0));
    }

    #[Test]
    public function self_comments_and_rejected_comments_do_not_notify(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $this->actingAs($owner)->post(route('posts.comments.store', $post), ['content' => 'My own comment.'])->assertRedirect();
        $this->post(route('posts.comments.store', $post), ['content' => ''])->assertSessionHasErrors('content');
        $letter = Post::factory()->letter()->for($owner, 'author')->create();
        $this->post(route('posts.comments.store', $letter), ['content' => 'Not allowed.'])->assertForbidden();

        $this->assertDatabaseEmpty('activity_notifications');
    }

    #[Test]
    public function listing_is_newest_first_paginated_and_does_not_mark_items_read(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $commenter = User::factory()->create(['name' => 'Sam']);
        for ($i = 0; $i < 21; $i++) {
            $latest = $this->commentOn($post, $commenter);
        }
        $this->commentOn(Post::factory()->memory()->create());

        $this->actingAs($owner)->get(route('activity.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Notifications')
                ->has('activityNotifications.data', 20)
                ->where('activityNotifications.data.0.id', $latest->id)
                ->where('activityNotifications.data.0.author', 'Sam')
                ->where('activityNotifications.data.0.preview', 'A lovely memory.')
                ->where('activityNotifications.data.0.read', false)
                ->where('unreadNotificationsCount', 21));
        $this->get(route('activity.index', ['page' => 2]))->assertInertia(fn (Assert $page) => $page
            ->has('activityNotifications.data', 1)->where('unreadNotificationsCount', 21));
        $this->assertSame(0, ActivityNotification::query()->whereNotNull('read_at')->count());
    }

    #[Test]
    public function opening_an_item_marks_only_that_item_read_and_links_to_the_comment(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $activity = $this->commentOn($post);
        $other = $this->commentOn($post);

        $this->actingAs($owner)->post(route('activity.open', $activity->id))
            ->assertRedirect(route('posts.show', $post).'#comment-'.$activity->comment_id);
        $readAt = $activity->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->assertNull($other->fresh()->read_at);
        $this->travel(1)->minutes();
        $this->post(route('activity.open', $activity->id))->assertRedirect();
        $this->assertTrue($activity->fresh()->read_at->equalTo($readAt));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('unreadNotificationsCount', 1));
        $this->get(route('posts.show', $post))->assertInertia(fn (Assert $page) => $page
            ->has('post.comments', 2));
    }

    #[Test]
    public function mark_all_as_read_is_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $post = Post::factory()->memory()->for($owner, 'author')->create();
        $first = $this->commentOn($post);
        $second = $this->commentOn($post);
        $someoneElse = $this->commentOn(Post::factory()->memory()->create());

        $this->actingAs($owner)->from(route('activity.index'))->put(route('activity.read-all'))
            ->assertRedirect(route('activity.index'));
        $this->assertNotNull($first->fresh()->read_at);
        $this->assertNotNull($second->fresh()->read_at);
        $this->assertNull($someoneElse->fresh()->read_at);
        $this->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page
            ->where('unreadNotificationsCount', 0)->where('activityNotifications.data.0.read', true));
    }

    #[Test]
    public function other_users_cannot_open_or_mark_a_notification_read(): void
    {
        $activity = $this->commentOn(Post::factory()->memory()->create());
        $this->actingAs(User::factory()->create())->post(route('activity.open', $activity->id))->assertNotFound();
        $this->assertNull($activity->fresh()->read_at);
    }

    #[Test]
    public function deleted_posts_and_private_letters_are_excluded_from_the_list_and_count(): void
    {
        $owner = User::factory()->create();
        $deleted = Post::factory()->memory()->for($owner, 'author')->create();
        $activity = $this->commentOn($deleted);
        $deleted->delete();
        $private = Post::factory()->memory()->for($owner, 'author')->create();
        $privateActivity = $this->commentOn($private);
        $private->update(['post_type' => TimelinePostType::LETTER]);

        $this->actingAs($owner)->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page
            ->has('activityNotifications.data', 0)->where('unreadNotificationsCount', 0));
        $this->post(route('activity.open', $activity->id))->assertNotFound();
        $this->post(route('activity.open', $privateActivity->id))->assertNotFound();
    }

    #[Test]
    public function deleted_comments_remove_the_notification_and_deleted_commenters_have_a_fallback(): void
    {
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $activity = $this->commentOn(Post::factory()->memory()->for($owner, 'author')->create(), $commenter);
        $commenter->delete();

        $this->actingAs($owner)->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page
            ->where('activityNotifications.data.0.author', 'Family')->where('unreadNotificationsCount', 1));
        $activity->comment->delete();
        $this->assertDatabaseEmpty('activity_notifications');
        $this->get(route('activity.index'))->assertInertia(fn (Assert $page) => $page->where('unreadNotificationsCount', 0));
    }

    #[Test]
    public function guests_cannot_access_notifications(): void
    {
        $this->get(route('activity.index'))->assertRedirect(route('login'));
        $this->post(route('activity.open', 1))->assertRedirect(route('login'));
        $this->put(route('activity.read-all'))->assertRedirect(route('login'));
    }
}
