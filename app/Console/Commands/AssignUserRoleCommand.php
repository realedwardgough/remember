<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\AssignUserRole;
use App\Enum\UserRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Assign an application role to an existing user')]
#[Signature('users:role {user : Username or email address} {role=admin : Role to assign (admin or user)}')]
class AssignUserRoleCommand extends Command
{
    public function __construct(private readonly AssignUserRole $assignRole)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userIdentifier = (string) $this->argument('user');
        $role = UserRole::tryFrom((string) $this->argument('role'));
        $user = User::query()->where('username', $userIdentifier)->orWhere('email', $userIdentifier)->first();

        if ($user === null) {
            $this->error('No user was found with that username or email address.');

            return self::FAILURE;
        }

        if ($role === null) {
            $this->error('Role must be either admin or user.');

            return self::FAILURE;
        }

        $this->assignRole->handle($user, $role);
        $this->info("Assigned the {$role->value} role to {$user->username}.");

        return self::SUCCESS;
    }
}
