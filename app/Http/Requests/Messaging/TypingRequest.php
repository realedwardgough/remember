<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class TypingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', $this->route('conversation')) ?? false;
    }

    public function rules(): array
    {
        return [
            'typing' => ['required', 'boolean'],
            'source_id' => ['required', 'uuid'],
            'participant_id' => ['prohibited'],
            'sender_id' => ['prohibited'],
        ];
    }
}
