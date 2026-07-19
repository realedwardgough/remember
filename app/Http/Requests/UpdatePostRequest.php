<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\PostData;
use App\Enum\TimelinePostType;
use App\Models\Post;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $post = $this->route('post');

        if (! $post instanceof Post) {
            return false;
        }

        return $this->user()?->can('update', $post) ?? false;
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
        ];
    }
}
