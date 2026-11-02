<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => ['required_without:recipient_ids', 'prohibits:recipient_ids', 'integer', Rule::exists('users', 'id'), Rule::notIn([$this->user()->id])],
            'recipient_ids' => ['required_without:recipient_id', 'prohibits:recipient_id', 'array', 'min:2', 'max:19'],
            'recipient_ids.*' => ['required', 'integer', 'distinct', Rule::exists('users', 'id'), Rule::notIn([$this->user()->id])],
            'name' => ['required_with:recipient_ids', Rule::prohibitedIf(fn () => ! $this->has('recipient_ids')), 'string', 'max:80'],
            'client_request_id' => ['required_with:recipient_ids', 'uuid'],
        ];
    }
}
