<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateComment;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Post;
use App\Http\Resources\CommentResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function index(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);
        abort_unless($post->allowsInteractions(), 403);
        $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'focus_id' => ['sometimes', 'integer', 'min:1'],
        ]);
        $page = $request->integer('page', 1);
        if ($request->filled('focus_id') && ! $request->has('page')) {
            $position = $post->comments()->where('id', '<=', $request->integer('focus_id'))->count();
            $page = max(1, (int) ceil($position / 20));
        }
        $comments = $post->comments()->reorder('id')
            ->with('author')->withCount('hearts')
            ->withExists(['hearts as hearted_by_viewer' => fn (Builder $query): Builder => $query->where('user_id', $request->user()->id)])
            ->paginate(perPage: 20, page: $page);

        return response()->json([
            'data' => CommentResource::collection($comments->getCollection())->resolve($request),
            'total' => $comments->total(),
            'previous_page' => $page > 1 ? $page - 1 : null,
            'next_page' => $comments->hasMorePages() ? $page + 1 : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function __construct(private readonly CreateComment $createComment)
    {
    }

    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $this->createComment->handle($post, $request->toDTO());

        return back();
    }
}
