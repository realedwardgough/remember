<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateTimeline;
use App\Http\Requests\UpdateTimelineRequest;
use App\Queries\GetPrimaryTimeline;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TimelineController extends Controller
{
    public function __construct(
        private readonly UpdateTimeline $updateTimeline,
        private readonly GetPrimaryTimeline $primaryTimeline,
    ) {
    }

    public function update(UpdateTimelineRequest $request): RedirectResponse
    {
        $this->updateTimeline->handle($this->primaryTimeline->handle(), $request->toDTO());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Timeline details updated.',
        ])->back();
    }
}
