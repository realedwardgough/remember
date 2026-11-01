<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SharePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'recipient_id' => ['required_without:conversation_id', 'prohibits:conversation_id', 'integer', Rule::exists('users', 'id'), Rule::notIn([$this->user()->id])],
            'conversation_id' => ['required_without:recipient_id', 'prohibits:recipient_id', 'integer', 'min:1'],
            'body' => ['nullable', 'string', 'max:10000'],
            'client_request_id' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'recipient_id.exists' => 'This family member is no longer available.',
            'recipient_id.not_in' => 'Choose another family member.',
            'body.max' => 'Your note can contain up to 10,000 characters.',
        ];
    }
}
