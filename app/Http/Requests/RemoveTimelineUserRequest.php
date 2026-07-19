<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class RemoveTimelineUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::ADMIN->value) ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [];
    }
}
