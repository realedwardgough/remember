<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queries\GetGalleryImages;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function __construct(private readonly GetGalleryImages $galleryImages)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Gallery', [
            'images' => Inertia::scroll(fn () => $this->galleryImages->handle($request->user(), max(1, $request->integer('page', 1))))->matchOn('data.id'),
        ]);
    }
}
