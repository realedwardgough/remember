<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class UploadMessageImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', $this->route('conversation')) ?? false;
    }

    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'max:10240'],
            'client_request_id' => ['required', 'uuid'],
        ];
    }
}
