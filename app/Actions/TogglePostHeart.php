<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ToggleHeartResultDTO;
use App\Models\Heart;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TogglePostHeart
{
    /** @throws AuthorizationException
     * @throws \Throwable
     */
    public function handle(Post $post, User $user): ToggleHeartResultDTO
    {
        return DB::transaction(function () use ($post, $user): ToggleHeartResultDTO {
            $lockedPost = Post::query()->lockForUpdate()->findOrFail($post->id);

            if (! $lockedPost->allowsInteractions()) {
                throw new AuthorizationException('Private letters cannot receive hearts.');
            }

            $heart = Heart::query()
                ->whereBelongsTo($lockedPost)
                ->whereBelongsTo($user)
                ->first();

            if ($heart !== null) {
                $heart->delete();

                return new ToggleHeartResultDTO(hearted: false, heartId: null);
            }

            $heart = $lockedPost->hearts()->create(['user_id' => $user->id]);

            return new ToggleHeartResultDTO(hearted: true, heartId: $heart->id);
        });
    }
}
