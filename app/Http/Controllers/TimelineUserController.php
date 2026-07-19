<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RemoveTimelineUser;
use App\Http\Requests\RemoveTimelineUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TimelineUserController extends Controller
{
    public function __construct(private readonly RemoveTimelineUser $removeUser)
    {
    }

    public function destroy(RemoveTimelineUserRequest $request, User $user): RedirectResponse
    {
        $this->removeUser->handle($request->user(), $user);

        return back();
    }
}
