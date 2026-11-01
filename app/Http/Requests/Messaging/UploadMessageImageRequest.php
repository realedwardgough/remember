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

    public function messages(): array
    {
        return [
            'image.max' => 'This photo exceeds the 10 MB upload limit. Please choose it again so it can be resized, or use a smaller copy.',
            'image.uploaded' => 'The server could not accept this photo. Its upload limit may be lower than the photo size. Please try a smaller copy.',
        ];
    }
}
