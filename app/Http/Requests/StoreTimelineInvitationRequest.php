<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\UserRole;
use App\Models\RegistrationInvite;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTimelineInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::ADMIN->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'username' => [
                'required', 'string', 'lowercase', 'alpha_dash:ascii', 'max:50',
                Rule::unique(User::class, 'username'),
                Rule::unique(RegistrationInvite::class, 'username')->whereNull('accepted_at'),
            ],
        ];
    }
}
