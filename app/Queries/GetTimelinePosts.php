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
        $key = 'timeline-v3-'.md5(serialize(['filters' => $filters->toArray(), 'page' => $page, 'viewer' => $viewer->id]));
        $pagination = Cache::tags(['posts'])->remember(
            key: $key.'-ids',
            ttl: 300,
            callback: fn (): array => $this->page($viewer, $filters, $page),
        );
        $ids = $pagination['data'];

        return Cache::tags(['posts', ...array_map(static fn (int $id): string => 'timeline-post-'.$id, $ids)])->remember(
            key: $key.'-content',
            ttl: 300,
            callback: fn (): array => [...$pagination, 'data' => $this->posts($viewer, $ids)],
        );
    }

    /** @return array<string, mixed> */
    private function page(User $viewer, TimelineFiltersDTO $filters, int $page): array
    {
        return Post::query()
            ->select('posts.id')
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
            ->through(static fn (Post $post): int => $post->id)
            ->toArray();
    }

    /** @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    private function posts(User $viewer, array $ids): array
    {
        $resourceRequest = Request::create('/');
        $resourceRequest->setUserResolver(static fn (): User => $viewer);

        $posts = Post::query()
            ->with([
                'author', 'media', 'tags',
            ])
            ->withCount(['hearts', 'comments'])
            ->withExists(['hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query->where('user_id', $viewer->id)])
            ->visibleTo($viewer)
            ->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->filter(fn (int $id): bool => $posts->has($id))
            ->map(fn (int $id): array => TimelineResource::make($posts[$id])->toArray($resourceRequest))
            ->values()->all();
    }
}
