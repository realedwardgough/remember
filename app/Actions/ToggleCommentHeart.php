<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ToggleHeartResultDTO;
use App\Models\Comment;
use App\Models\CommentHeart;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ToggleCommentHeart
{
    /** @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Comment $comment, User $user): ToggleHeartResultDTO
    {
        return DB::transaction(function () use ($comment, $user): ToggleHeartResultDTO {
            $lockedComment = Comment::query()
                ->with('post')
                ->lockForUpdate()
                ->findOrFail($comment->id);

            if (! $lockedComment->post->allowsInteractions()) {
                throw new AuthorizationException('Comments on private letters cannot receive hearts.');
            }

            $heart = CommentHeart::query()
                ->whereBelongsTo($lockedComment)
                ->whereBelongsTo($user)
                ->first();

            if ($heart !== null) {
                $heart->delete();

                return new ToggleHeartResultDTO(hearted: false, heartId: null);
            }

            $heart = $lockedComment->hearts()->create(['user_id' => $user->id]);

            return new ToggleHeartResultDTO(hearted: true, heartId: $heart->id);
        });
    }
}
