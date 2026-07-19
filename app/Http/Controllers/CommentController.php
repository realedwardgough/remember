<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateComment;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;

class CommentController extends Controller
{
    public function __construct(private readonly CreateComment $createComment)
    {
    }

    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        $this->createComment->handle($post, $request->toDTO());

        return back();
    }
}
