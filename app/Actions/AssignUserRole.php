<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enum\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AssignUserRole
{
    public function handle(User $user, UserRole $role): User
    {
        return DB::transaction(function () use ($user, $role): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($lockedUser->hasRole(UserRole::ADMIN->value) && $role !== UserRole::ADMIN && $this->adminCount() === 1) {
                throw ValidationException::withMessages(['role' => 'The timeline must always have at least one administrator.']);
            }

            Role::findOrCreate($role->value, 'web');
            $lockedUser->syncRoles([$role->value]);

            return $lockedUser->load('roles');
        });
    }

    private function adminCount(): int
    {
        return User::query()->role(UserRole::ADMIN->value)->lockForUpdate()->count();
    }
}
