<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HeartController extends Controller
{
    public function toggle(Request $request, Post $post): RedirectResponse
    {
        $heart = $post->hearts()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($heart) {
            $heart->delete();

            return back();
        }

        $post->hearts()->create([
            'user_id' => $request->user()->id,
        ]);

        return back();
    }
}
