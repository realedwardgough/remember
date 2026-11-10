<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\RegistrationInvite;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationInviteController extends Controller
{
    public function __invoke(string $token): Response
    {
        $invite = RegistrationInvite::query()
            ->where('token_hash', RegistrationInvite::hashToken($token))
            ->whereNull('accepted_at')
            ->firstOrFail();

        abort_if($invite->isExpired(), 404);

        return Inertia::render('auth/Register', [
            'invite' => [
                'token' => $token,
                'username' => $invite->username,
                'email' => $invite->email,
            ],
        ]);
    }
}
