<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enum\UserRole;
use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
            'invitation_token' => ['required', 'string'],
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $invite = RegistrationInvite::query()
                ->where('token_hash', RegistrationInvite::hashToken((string) $input['invitation_token']))
                ->lockForUpdate()
                ->first();

            if ($invite === null || $invite->isAccepted()) {
                throw ValidationException::withMessages([
                    'invitation_token' => __('This registration invite is no longer valid.'),
                ]);
            }

            Validator::make(['username' => $invite->username], [
                'username' => ['required', 'string', 'max:255', Rule::unique(User::class, 'username')],
            ])->validate();

            $user = User::query()->create([
                'name' => $input['name'],
                'username' => $invite->username,
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole(UserRole::USER->value);

            $invite->forceFill([
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ])->save();

            return $user;
        });
    }
}
