<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ToggleCommentHeart;
use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class CommentHeartController extends Controller
{
    public function __construct(private readonly ToggleCommentHeart $toggleCommentHeart)
    {
    }

    /**
     * @throws Throwable
     */
    public function toggle(Request $request, Comment $comment): RedirectResponse
    {
        $this->toggleCommentHeart->handle($comment, $request->user());

        return back();
    }
}
