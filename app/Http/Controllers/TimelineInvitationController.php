<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRegistrationInvite;
use App\Actions\RefreshRegistrationInvite;
use App\Http\Requests\RefreshTimelineInvitationRequest;
use App\Http\Requests\StoreTimelineInvitationRequest;
use App\Models\RegistrationInvite;
use Illuminate\Http\RedirectResponse;

class TimelineInvitationController extends Controller
{
    public function __construct(
        private readonly CreateRegistrationInvite $createInvite,
        private readonly RefreshRegistrationInvite $refreshInvite,
    ) {
    }

    public function store(StoreTimelineInvitationRequest $request): RedirectResponse
    {
        $result = $this->createInvite->handle($request->validated('username'));

        return back()->with('inviteUrl', $result->url);
    }
    public function update(RefreshTimelineInvitationRequest $request, RegistrationInvite $registrationInvite): RedirectResponse
    {
        $result = $this->refreshInvite->handle($registrationInvite);

        return back()->with('inviteUrl', $result->url);
    }

}
