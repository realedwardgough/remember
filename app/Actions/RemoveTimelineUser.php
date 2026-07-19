<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enum\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTimelineUser
{
    public function handle(User $actor, User $user): void
    {
        DB::transaction(function () use ($actor, $user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($actor->is($lockedUser)) {
                throw ValidationException::withMessages(['user' => 'You cannot remove your own account.']);
            }

            if ($lockedUser->hasRole(UserRole::ADMIN->value) && User::query()->role(UserRole::ADMIN->value)->lockForUpdate()->count() === 1) {
                throw ValidationException::withMessages(['user' => 'The timeline must always have at least one administrator.']);
            }

            $lockedUser->delete();
        });
    }
}
