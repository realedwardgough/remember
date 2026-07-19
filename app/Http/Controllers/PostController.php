<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePost;
use App\Actions\UpdatePost;
use App\Enum\TimelinePostType;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PostController extends Controller
{
    public function __construct(
        private readonly CreatePost $createPost,
        private readonly UpdatePost $updatePost,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->createPost->handle(
            author: $request->user(),
            data: $request->toDTO(),
            mediaFiles: $request->file('media', []),
        );

        return redirect()->route('home');
    }

    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        return Inertia::render('posts/Edit', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'content' => $post->content ?? '',
                'postType' => $post->post_type->value,
                'publishedAt' => $post->published_at->toDateString(),
            ],
            'postTypes' => array_map(
                static fn (TimelinePostType $type): string => $type->value,
                TimelinePostType::cases(),
            ),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        Gate::authorize('update', $post);

        $this->updatePost->handle($post, $request->toDTO());

        return redirect()->route('home');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()->route('home');
    }
}
