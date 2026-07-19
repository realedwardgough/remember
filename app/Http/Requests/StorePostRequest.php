<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\PostData;
use App\Enum\TimelinePostType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StorePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function toDTO(): PostData
    {
        $validated = $this->validated();

        return new PostData(
            title: $validated['title'],
            content: $validated['content'] ?? null,
            postType: TimelinePostType::from($validated['post_type']),
            publishedAt: $validated['published_at'],
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'post_type' => ['required', Rule::enum(TimelinePostType::class)],
            'published_at' => ['required', 'date'],
            'media' => ['nullable', 'array', 'max:10'],
            'media.*' => [
                'required',
                File::types(['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'doc', 'docx'])
                    ->max(10 * 1024),
            ],
        ];
    }
}
