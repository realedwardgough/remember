<?php

declare(strict_types=1);

namespace App\Services\Posts;

use App\Http\Resources\TimelineResource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PostService
{
    public function __construct(private readonly FiltersService $filtersService)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getTimelinePosts(Request $request): array
    {
        $filters = $this->filtersService->set($request);

        return Post::query()
            ->with(relations: [
                'author',
                'media',
                'tags',
                'comments' => fn ($query) => $query
                    ->with('author')
                    ->withCount('hearts')
                    ->withExists([
                        'hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query
                            ->where(column: 'user_id', operator: '=', value: $request->user()->id),
                    ]),
            ])
            ->withCount(relations: 'hearts')
            ->withExists(relation: [
                'hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query
                    ->where(column: 'user_id', operator: '=', value: $request->user()->id),
            ])
            ->visibleTo($request->user())
            ->when(value: $filters['search'] !== '', callback: fn (Builder $query): Builder => $query
                ->where(function (Builder $query) use ($filters): void {
                    $query
                        ->where(column: 'title', operator: 'like', value: "%{$filters['search']}%")
                        ->orWhere(column: 'content', operator: 'like', value: "%{$filters['search']}%");
                }))
            ->when(value: $filters['tag'] !== '', callback: function (Builder $query) use ($filters): Builder {
                return $query->whereHas(relation: 'tags', callback: function (Builder $query) use ($filters): Builder {
                    return $query->where('name', $filters['tag']);
                });
            })
            ->when(value: $filters['author'] !== '', callback: function (Builder $query) use ($filters): Builder {
                return $query->whereHas(relation: 'author', callback: function (Builder $query) use ($filters): Builder {
                    return $query->where(column: 'username', operator: '=', value: $filters['author']);
                });
            })
            ->when(value: $filters['type'] !== '', callback: function (Builder $query) use ($filters): Builder {
                return $query->where(column: 'post_type', operator: '=', value: $filters['type']);
            })
            ->latest(column: 'published_at')
            ->latest(column: 'id')
            ->paginate(perPage: 20)
            ->withQueryString()
            ->through(callback: function (Post $post) use ($request): array {
                return TimelineResource::make($post)->toArray($request);
            })
            ->toArray();
    }
}
