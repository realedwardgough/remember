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

class PostController extends Controller
{
    public function store(StorePostRequest $request, CreatePost $createPost): RedirectResponse
    {
        $createPost->handle(
            author: $request->user(),
            attributes: $request->validated(),
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

    public function update(UpdatePostRequest $request, Post $post, UpdatePost $updatePost): RedirectResponse
    {
        Gate::authorize('update', $post);

        $updatePost->handle($post, $request->validated());

        return redirect()->route('home');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()->route('home');
    }
}
