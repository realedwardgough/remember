<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Timeline;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'version' => config('app.version'),
            'timeline' => fn (): array => $this->timeline(),
            'auth' => ['user' => $request->user()],
            'remember' => [
                'emailNotificationsEnabled' => (bool) config('remember.email_notifications'),
            ],
            'flash' => [
                'inviteUrl' => fn (): ?string => session('inviteUrl'),
                'inviteNotificationSent' => fn (): ?bool => session('inviteNotificationSent'),
            ],
            'filters' => fn (): array => $this->filters($request),
            'postTypes' => fn (): array => array_map(
                static fn (TimelinePostType $type): string => $type->value,
                TimelinePostType::cases(),
            ),
            'tags' => fn (): array => $request->user()
                ? Tag::query()
                    ->whereHas(
                        relation: 'posts',
                        callback: fn (Builder $query): Builder => $query->visibleTo($request->user()),
                    )
                    ->orderBy('name')
                    ->pluck('name')
                    ->all()
                : [],
            'family' => fn (): array => $request->user() ? $this->family() : [],
            'family_count' => fn (): int => $request->user() ? User::query()->count() : 0,
            'media_count' => fn (): int => $request->user()
                ? Media::query()
                    ->whereHas(
                        relation: 'post',
                        callback: fn (Builder $query): Builder => $query->visibleTo($request->user()),
                    )
                    ->count()
                : 0,
            'memories_count' => fn (): int => $request->user() ? Post::query()->visibleTo($request->user())->count() : 0,
        ];
    }

    /** @return array{name: string, description: ?string} */
    private function timeline(): array
    {
        $timeline = Timeline::query()->first();

        return [
            'name' => $timeline?->name ?? (string) config('app.name'),
            'description' => $timeline?->description,
        ];
    }

    /** @return array{search: string, tag: string, type: string, author: string} */
    private function filters(Request $request): array
    {
        $type = $request->string('type')->trim()->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'tag' => $request->string('tag')->trim()->lower()->toString(),
            'type' => TimelinePostType::tryFrom($type)?->value ?? '',
            'author' => $request->string('author')->trim()->lower()->toString(),
        ];
    }

    /** @return array<string, array{name: string, username: string, photo: string}> */
    private function family(): array
    {
        return User::query()
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->mapWithKeys(function (User $user): array {
                $firstName = str($user->name)->before(' ')->value() ?: 'Family Member';

                return [
                    $firstName => [
                        'name' => $user->name,
                        'username' => '@'.$user->username,
                        'photo' => $user->photo ?? '',
                    ],
                ];
            })
            ->toArray();
    }
}
