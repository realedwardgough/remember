<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConfirmEmailChangeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless((string) $request->user()->id === $request->query('user'), 403);
        $request->validate(['email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($request->user()->id)]]);
        DB::transaction(function () use ($request): void {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            abort_unless(hash_equals(hash('sha256', $user->email.'|'.$user->password), (string) $request->query('previous')), 403);
            $user->forceFill(['email' => $request->query('email'), 'email_verified_at' => now()])->save();
        });

        return redirect()->route('profile.edit')->with('status', 'Your email address has been verified and updated.');
    }
}
