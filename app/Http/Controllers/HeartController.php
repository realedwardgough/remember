<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TogglePostHeart;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class HeartController extends Controller
{
    public function __construct(private readonly TogglePostHeart $togglePostHeart)
    {
    }

    /**
     * @throws Throwable
     */
    public function toggle(Request $request, Post $post): RedirectResponse
    {
        $this->togglePostHeart->handle($post, $request->user());

        return back();
    }
}
