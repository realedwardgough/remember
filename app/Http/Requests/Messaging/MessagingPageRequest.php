<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

class MessagingPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['cursor' => ['bail', 'nullable', 'string', 'max:1024', function (string $attribute, mixed $value, Closure $fail): void {
            $decoded = json_decode(base64_decode(strtr($value, '-_', '+/'), true) ?: '', true);
            $rules = [
                'id' => ['required', 'integer', 'min:1'],
                '_pointsToNextItems' => ['required', 'boolean'],
            ];
            if ($this->routeIs('messaging.index')) {
                $rules['updated_at'] = ['required', 'string', 'max:64'];
            }
            if (! is_array($decoded) || Validator::make($decoded, $rules)->fails()) {
                $fail('The pagination cursor is invalid.');
            }
        }]];
    }
}
