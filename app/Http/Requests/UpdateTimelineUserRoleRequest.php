<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimelineUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::ADMIN->value) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return ['role' => ['required', Rule::enum(UserRole::class)]];
    }

    public function role(): UserRole
    {
        return UserRole::from($this->validated('role'));
    }
}
