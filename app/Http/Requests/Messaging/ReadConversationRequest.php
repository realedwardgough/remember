<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class ReadConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('conversation')) ?? false;
    }

    public function rules(): array
    {
        return ['read_token' => ['required', 'string', 'max:4096']];
    }
}
