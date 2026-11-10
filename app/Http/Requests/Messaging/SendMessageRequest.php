<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('conversation')) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without_all:attachment_ids,shared_post_id', 'string', 'max:10000'],
            'shared_post_id' => ['nullable', 'integer', 'min:1'],
            'attachment_ids' => ['nullable', 'array', 'max:4'],
            'attachment_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'client_request_id' => ['required', 'uuid'],
            'sender_participant_id' => ['prohibited'],
            'sender_id' => ['prohibited'],
            'conversation_id' => ['prohibited'],
            'attachments' => ['prohibited'],
            'post_id' => ['prohibited'],
        ];
    }
}
