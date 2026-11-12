<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\MediaResource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PostMediaController extends Controller
{
    public function __invoke(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('view', $post);
        $validated = $request->validate(['type' => ['required', 'in:image,video']]);
        $media = $post->media()->where('mime_type', 'like', $validated['type'].'/%')->get();

        return response()->json(['data' => MediaResource::collection($media)->resolve($request)])
            ->header('Cache-Control', 'private, no-store');
    }
}
