<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\TimelinePostType;
use App\Services\Posts\FiltersService;
use App\Services\Posts\PostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request, PostService $postService, FiltersService $filtersService): Response
    {
        $filters = $filtersService->set($request);

        return Inertia::render(component: 'Home', props: [
            'timelinePosts' => Inertia::scroll(
                value: fn (): array => Cache::tags(['posts'])->remember(
                    key: 'timeline-'.md5(serialize([
                        'filters' => $filters,
                        'page' => $request->integer(key: 'page', default: 1),
                        'viewer' => $request->user()->id,
                    ])),
                    ttl: 300,
                    callback: static fn (): array => $postService->getTimelinePosts($request)
                ),
                metadata: fn (array $timelinePosts): ScrollMetadata => new ScrollMetadata(
                    pageName: 'page',
                    previousPage: $timelinePosts['current_page'] > 1 ? $timelinePosts['current_page'] - 1 : null,
                    nextPage: $timelinePosts['next_page_url'] !== null ? $timelinePosts['current_page'] + 1 : null,
                    currentPage: $timelinePosts['current_page'],
                )
            ),
            'filters' => $filters,
            'postTypes' => array_map(
                callback: static fn (TimelinePostType $type): string => $type->value,
                array: TimelinePostType::cases(),
            ),
        ]);
    }
}
