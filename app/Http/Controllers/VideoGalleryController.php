<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\GetGalleryVideos;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VideoGalleryController extends Controller
{
    public function index(Request $request, GetGalleryVideos $videos): Response
    {
        return Inertia::render('Videos', [
            'videos' => Inertia::scroll(fn () => $videos->handle($request->user(), max(1, $request->integer('page', 1))))->matchOn('data.id'),
        ]);
    }
}
