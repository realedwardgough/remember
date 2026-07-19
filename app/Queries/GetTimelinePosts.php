<?php

declare(strict_types=1);

namespace App\Queries;

use App\DTOs\TimelineFiltersDTO;
use App\Http\Resources\TimelineResource;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GetTimelinePosts
{
    /** @return array<string, mixed> */
    public function handle(User $viewer, TimelineFiltersDTO $filters, int $page): array
    {
        return Cache::tags(['posts'])->remember(
            key: 'timeline-'.md5(serialize(['filters' => $filters->toArray(), 'page' => $page, 'viewer' => $viewer->id])),
            ttl: 300,
            callback: fn (): array => $this->query($viewer, $filters, $page),
        );
    }

    /** @return array<string, mixed> */
    private function query(User $viewer, TimelineFiltersDTO $filters, int $page): array
    {
        $resourceRequest = Request::create('/');
        $resourceRequest->setUserResolver(static fn (): User => $viewer);

        return Post::query()
            ->with([
                'author', 'media', 'tags',
                'comments' => fn ($query) => $query->with('author')->withCount('hearts')->withExists([
                    'hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query->where('user_id', $viewer->id),
                ]),
            ])
            ->withCount('hearts')
            ->withExists(['hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query->where('user_id', $viewer->id)])
            ->visibleTo($viewer)
            ->when($filters->search !== '', fn (Builder $query): Builder => $query->where(
                fn (Builder $query) => $query->where('title', 'like', "%{$filters->search}%")->orWhere('content', 'like', "%{$filters->search}%"),
            ))
            ->when($filters->tag !== '', fn (Builder $query): Builder => $query->whereHas('tags', fn (Builder $query): Builder => $query->where('name', $filters->tag)))
            ->when($filters->author !== '', fn (Builder $query): Builder => $query->whereHas('author', fn (Builder $query): Builder => $query->where('username', $filters->author)))
            ->when($filters->type !== '', fn (Builder $query): Builder => $query->where('post_type', $filters->type))
            ->latest('published_at')->latest('id')
            ->paginate(perPage: 20, page: $page)
            ->appends($filters->toArray())
            ->through(fn (Post $post): array => TimelineResource::make($post)->toArray($resourceRequest))
            ->toArray();
    }
}
