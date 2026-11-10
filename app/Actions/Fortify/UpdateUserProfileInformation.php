<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\VerifyEmailChange;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'current_password' => [Rule::requiredIf(($input['email'] ?? null) !== $user->email), 'nullable', 'string', 'current_password'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ])->validateWithBag('updateProfileInformation');

        if ($input['email'] !== $user->email) {
            $url = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes(30), [
                'user' => (string) $user->id, 'email' => $input['email'],
                'previous' => hash('sha256', $user->email.'|'.$user->password),
            ]);
            Notification::route('mail', $input['email'])->notify(new VerifyEmailChange($url));
            request()->session()->flash('status', 'Check your new email address for a confirmation link. Your login email stays the same until you confirm it.');
        }

        $user->forceFill(['name' => $input['name']])->save();
    }
}
