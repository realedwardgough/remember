<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\RegistrationInviteResultDTO;
use App\Models\RegistrationInvite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class CreateRegistrationInvite
{
    public function handle(string $username, ?string $email = null): RegistrationInviteResultDTO
    {
        $username = Str::lower($username);
        $token = Str::random(64);
        $invite = RegistrationInvite::query()->create([
            'username' => $username,
            'email' => $email === null ? null : Str::lower($email),
            'expires_at' => now()->addHours((int) config('security.invitation_lifetime_hours', 168)),
            'token_hash' => RegistrationInvite::hashToken($token),
            'token' => $token,
        ]);

        return new RegistrationInviteResultDTO(
            invite: $invite,
            token: $token,
            url: URL::route('register.invite', ['token' => $token]),
        );
    }
}
