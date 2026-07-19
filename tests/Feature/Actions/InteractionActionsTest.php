<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\CreateComment;
use App\Actions\ToggleCommentHeart;
use App\Actions\TogglePostHeart;
use App\DTOs\CreateCommentDTO;
use App\DTOs\ToggleHeartResultDTO;
use App\Enum\TimelinePostType;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InteractionActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_comment_returns_the_created_comment(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->create(['post_type' => TimelinePostType::MEMORY]);

        $comment = (new CreateComment)->handle(
            $post,
            new CreateCommentDTO(authorId: $author->id, content: 'A lovely memory.'),
        );

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertTrue($comment->post->is($post));
        $this->assertTrue($comment->author->is($author));
        $this->assertSame('A lovely memory.', $comment->content);
    }

    public function test_post_heart_action_returns_typed_results_for_both_states(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->create(['post_type' => TimelinePostType::MEMORY]);
        $action = new TogglePostHeart;

        $added = $action->handle($post, $user);
        $removed = $action->handle($post, $user);

        $this->assertInstanceOf(ToggleHeartResultDTO::class, $added);
        $this->assertTrue($added->hearted);
        $this->assertNotNull($added->heartId);
        $this->assertInstanceOf(ToggleHeartResultDTO::class, $removed);
        $this->assertFalse($removed->hearted);
        $this->assertNull($removed->heartId);
    }

    public function test_comment_heart_action_returns_typed_results_for_both_states(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->for(Post::factory()->state(['post_type' => TimelinePostType::MEMORY]))->create();
        $action = new ToggleCommentHeart;

        $added = $action->handle($comment, $user);
        $removed = $action->handle($comment, $user);

        $this->assertInstanceOf(ToggleHeartResultDTO::class, $added);
        $this->assertTrue($added->hearted);
        $this->assertInstanceOf(ToggleHeartResultDTO::class, $removed);
        $this->assertFalse($removed->hearted);
    }

    public function test_private_letters_reject_interaction_actions(): void
    {
        $user = User::factory()->create();
        $letter = Post::factory()->create(['post_type' => TimelinePostType::LETTER]);

        try {
            (new CreateComment)->handle(
                $letter,
                new CreateCommentDTO(authorId: $user->id, content: 'Not allowed.'),
            );
            $this->fail('Creating a letter comment should throw an authorization exception.');
        } catch (AuthorizationException) {
            $this->assertDatabaseEmpty('comments');
        }

        $this->expectException(AuthorizationException::class);

        (new TogglePostHeart)->handle($letter, $user);
    }

    public function test_comments_belonging_to_private_letters_cannot_be_hearted(): void
    {
        $user = User::factory()->create();
        $letter = Post::factory()->create(['post_type' => TimelinePostType::LETTER]);
        $comment = Comment::factory()->for($letter)->create();

        $this->expectException(AuthorizationException::class);

        (new ToggleCommentHeart)->handle($comment, $user);
    }
}
