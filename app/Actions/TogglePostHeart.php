<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\ToggleHeartResultDTO;
use App\Models\Heart;
use App\Models\ActivityNotification;
use App\Models\Post;
use App\Models\User;
use App\Services\Messaging\ActivityPush;
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

            if ($lockedPost->author_id !== null && $lockedPost->author_id !== $user->id) {
                $activity = ActivityNotification::query()->create([
                    'user_id' => $lockedPost->author_id,
                    'heart_id' => $heart->id,
                ]);
                app(ActivityPush::class)->prepare($activity);
            }

            return new ToggleHeartResultDTO(hearted: true, heartId: $heart->id);
        });
    }
}
