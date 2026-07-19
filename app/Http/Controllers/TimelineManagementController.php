<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\UserRole;
use App\Models\RegistrationInvite;
use App\Models\User;
use App\Queries\GetPrimaryTimeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TimelineManagementController extends Controller
{
    public function __construct(private readonly GetPrimaryTimeline $primaryTimeline)
    {
    }
    public function __invoke(Request $request): Response
    {
        $timeline = $this->primaryTimeline->handle();
        Gate::authorize('manage', $timeline);

        return Inertia::render('Timeline/Manage', [
            'managedTimeline' => ['name' => $timeline->name, 'description' => $timeline->description],
            'users' => User::query()->with('roles')->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->roles->first()?->name ?? UserRole::USER->value,
                'isCurrentUser' => $user->is($request->user()),
            ]),
            'roles' => array_map(static fn (UserRole $role): string => $role->value, UserRole::cases()),
            'pendingInvites' => RegistrationInvite::query()->whereNull('accepted_at')->latest()->get()->map(fn (RegistrationInvite $invite): array => [
                'id' => $invite->id,
                'username' => $invite->username,
                'created_at' => $invite->created_at,
                'url' => $invite->token === null ? null : URL::route('register.invite', ['token' => $invite->token]),
            ]),
        ]);
    }
}
