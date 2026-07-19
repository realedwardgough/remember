<?php

declare(strict_types=1);

namespace App\Actions;

use App\DTOs\RegistrationInviteResultDTO;
use App\Models\RegistrationInvite;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RefreshRegistrationInvite
{
    public function handle(RegistrationInvite $invite): RegistrationInviteResultDTO
    {
        if ($invite->isAccepted()) {
            throw ValidationException::withMessages(['invite' => 'Accepted invitations cannot be refreshed.']);
        }

        $token = Str::random(64);
        $invite->update([
            'token' => $token,
            'token_hash' => RegistrationInvite::hashToken($token),
        ]);

        return new RegistrationInviteResultDTO(
            invite: $invite->refresh(),
            token: $token,
            url: URL::route('register.invite', ['token' => $token]),
        );
    }
}
