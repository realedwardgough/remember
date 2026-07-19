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
            'images' => $this->galleryImages->handle($request->user()),
        ]);
    }
}
