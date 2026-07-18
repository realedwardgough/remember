<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $post->comments()->create([
            'author_id' => $request->user()->id,
            'content' => $request->validated('content'),
        ]);

        return back();
    }
}
