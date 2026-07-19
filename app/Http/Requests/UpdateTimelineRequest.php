<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\TimelineData;
use App\Enum\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::ADMIN->value) ?? false;
    }

    public function toDTO(): TimelineData
    {
        $validated = $this->validated();

        return new TimelineData($validated['name'], $validated['description'] ?? null);
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:280'],
        ];
    }
}
