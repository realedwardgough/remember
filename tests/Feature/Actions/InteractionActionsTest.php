<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use PHPUnit\Framework\Attributes\Test;
use App\Actions\CreateComment;
use App\Actions\ToggleCommentHeart;
use App\Actions\TogglePostHeart;
use App\DTOs\CreateCommentDTO;
use App\DTOs\ToggleHeartResultDTO;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InteractionActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[Test]
    public function create_comment_returns_the_created_comment(): void
    {
        $author = User::factory()->create();
        $post = Post::factory()->memory()->create();

        $comment = (new CreateComment)->handle(
            $post,
            new CreateCommentDTO(authorId: $author->id, content: 'A lovely memory.'),
        );

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertTrue($comment->post->is($post));
        $this->assertTrue($comment->author->is($author));
        $this->assertSame('A lovely memory.', $comment->content);
    }

    #[Test]
    public function post_heart_action_returns_typed_results_for_both_states(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->memory()->create();
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

    #[Test]
    public function comment_heart_action_returns_typed_results_for_both_states(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->for(Post::factory()->memory())->create();
        $action = new ToggleCommentHeart;

        $added = $action->handle($comment, $user);
        $removed = $action->handle($comment, $user);

        $this->assertInstanceOf(ToggleHeartResultDTO::class, $added);
        $this->assertTrue($added->hearted);
        $this->assertInstanceOf(ToggleHeartResultDTO::class, $removed);
        $this->assertFalse($removed->hearted);
    }

    #[Test]
    public function private_letters_reject_interaction_actions(): void
    {
        $user = User::factory()->create();
        $letter = Post::factory()->letter()->create();

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

    #[Test]
    public function comments_belonging_to_private_letters_cannot_be_hearted(): void
    {
        $user = User::factory()->create();
        $letter = Post::factory()->letter()->create();
        $comment = Comment::factory()->for($letter)->create();

        $this->expectException(AuthorizationException::class);

        (new ToggleCommentHeart)->handle($comment, $user);
    }
}
