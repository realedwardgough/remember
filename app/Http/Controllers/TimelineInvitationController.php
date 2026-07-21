<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateRegistrationInvite;
use App\Actions\RefreshRegistrationInvite;
use App\Http\Requests\RefreshTimelineInvitationRequest;
use App\Http\Requests\SendRegistrationInviteNotificationRequest;
use App\Http\Requests\StoreTimelineInvitationRequest;
use App\Models\RegistrationInvite;
use App\Notifications\RegistrationInviteNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

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

    public function notify(SendRegistrationInviteNotificationRequest $request, RegistrationInvite $registrationInvite): RedirectResponse
    {
        if ($registrationInvite->isAccepted() || $registrationInvite->token === null) {
            throw ValidationException::withMessages([
                'email' => 'This invitation is no longer available to send.',
            ]);
        }

        Notification::route('mail', $request->validated('email'))->notify(
            new RegistrationInviteNotification(
                username: $registrationInvite->username,
                inviteUrl: URL::route('register.invite', ['token' => $registrationInvite->token]),
            ),
        );

        return back()->with('inviteNotificationSent', true);
    }
}
