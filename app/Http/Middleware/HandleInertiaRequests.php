<?php

namespace App\Http\Middleware;

use App\Enum\TimelinePostType;
use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'version' => config('app.version'),
            'auth' => [
                'user' => $request->user(),
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

    /**
     * @return array{search: string, tag: string, type: string, author: string}
     */
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

    /**
     * @return array<string, array{name: string, username: string, photo: string}>
     */
    private function family(): array
    {
        return User::query()
            ->orderBy(column: "name")
            ->limit(value: 5)
            ->get()
            ->mapWithKeys(callback: function (User $user): array {
                $firstName = explode(separator: " ", string: $user->name)[0] ?? "Family Member";

                return [
                    $firstName => [
                        "name" => $user->name,
                        "username" => "@".$user->username,
                        "photo" => $user->photo ?? "",
                    ],
                ];
            })
            ->toArray();
    }
}
