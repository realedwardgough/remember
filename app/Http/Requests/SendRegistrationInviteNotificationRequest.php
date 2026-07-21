<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class SendRegistrationInviteNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) config('remember.email_notifications')
            && ($this->user()?->hasRole(UserRole::ADMIN->value) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['email' => ['required', 'email:rfc', 'max:254']];
    }
}
