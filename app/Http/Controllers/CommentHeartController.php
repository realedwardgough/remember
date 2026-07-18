<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentHeartController extends Controller
{
    public function toggle(Request $request, Comment $comment): RedirectResponse
    {
        $heart = $comment->hearts()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($heart) {
            $heart->delete();

            return back();
        }

        $comment->hearts()->create([
            'user_id' => $request->user()->id,
        ]);

        return back();
    }
}
