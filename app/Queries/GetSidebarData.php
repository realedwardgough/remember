<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class GetSidebarData
{
    /** @return array<string, mixed> */
    public function handle(User $viewer): array
    {
        return Cache::tags(['sidebar'])->remember('sidebar-v1-'.$viewer->id, 60, fn (): array => [
            'tags' => Tag::query()->whereHas('posts', fn (Builder $query): Builder => $query->visibleTo($viewer))
                ->orderBy('name')->pluck('name')->all(),
            'family' => User::query()->orderBy('name')->limit(5)->get()->mapWithKeys(function (User $user): array {
                $firstName = str($user->name)->before(' ')->value() ?: 'Family Member';
                return [$firstName => [
                    'name' => $user->name,
                    'username' => '@'.$user->username,
                    'photo' => $user->photo ?? '',
                ]];
            })->all(),
            'family_count' => User::query()->count(),
            'media_count' => Media::query()->whereHas('post', fn (Builder $query): Builder => $query->visibleTo($viewer))->count(),
            'memories_count' => Post::query()->visibleTo($viewer)->count(),
        ]);
    }
}
