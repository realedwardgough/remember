<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Fortify;

class AccountSecurityController extends Controller
{
    public function show(Request $request): Response
    {
        Inertia::encryptHistory();
        $user = $request->user();
        $pending = $user->two_factor_secret !== null && ! $user->hasEnabledTwoFactorAuthentication();

        return Inertia::render('auth/AccountSecurity', [
            'enabled' => $user->hasEnabledTwoFactorAuthentication(),
            'pending' => $pending,
            'qrCode' => $pending ? 'data:image/svg+xml;base64,'.base64_encode($user->twoFactorQrCodeSvg()) : null,
            'setupKey' => $pending ? Fortify::currentEncrypter()->decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => $user->two_factor_secret !== null ? $user->recoveryCodes() : [],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function enable(Request $request, EnableTwoFactorAuthentication $enable): RedirectResponse
    {
        $enable($request->user());

        return back();
    }

    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'digits:6']]);
        $confirm($request->user(), $request->input('code'));

        return back()->with('status', 'Two-factor authentication is enabled. Keep your recovery codes somewhere safe.');
    }

    public function disable(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $disable($request->user());

        return back()->with('status', 'Two-factor authentication is disabled.');
    }

    public function recoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        abort_unless($request->user()->hasEnabledTwoFactorAuthentication(), 409);
        $generate($request->user());

        return back()->with('status', 'New recovery codes created. Your old codes no longer work.');
    }
}
