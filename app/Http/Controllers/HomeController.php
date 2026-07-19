<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTOs\TimelineFiltersDTO;
use App\Enum\TimelinePostType;
use App\Queries\GetTimelinePosts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\ScrollMetadata;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(private readonly GetTimelinePosts $timelinePosts)
    {
    }

    public function index(Request $request): Response
    {
        $filters = TimelineFiltersDTO::fromArray($request->only(['search', 'tag', 'type', 'author']));

        return Inertia::render(component: 'Home', props: [
            'timelinePosts' => Inertia::scroll(
                value: fn (): array => $this->timelinePosts->handle($request->user(), $filters, $request->integer('page', 1)),
                metadata: fn (array $posts): ScrollMetadata => new ScrollMetadata(
                    pageName: 'page',
                    previousPage: $posts['current_page'] > 1 ? $posts['current_page'] - 1 : null,
                    nextPage: $posts['next_page_url'] !== null ? $posts['current_page'] + 1 : null,
                    currentPage: $posts['current_page'],
                ),
            ),
            'filters' => $filters->toArray(),
            'postTypes' => array_map(static fn (TimelinePostType $type): string => $type->value, TimelinePostType::cases()),
        ]);
    }
}
