<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssignUserRole;
use App\Http\Requests\UpdateTimelineUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class TimelineUserRoleController extends Controller
{
    public function __construct(private readonly AssignUserRole $assignRole)
    {
    }

    public function update(UpdateTimelineUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->assignRole->handle($user, $request->role());

        return back();
    }
}
