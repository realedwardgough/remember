<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTOs\CreateCommentDTO;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function toDTO(): CreateCommentDTO
    {
        return new CreateCommentDTO(
            authorId: $this->user()->id,
            content: $this->validated('content'),
        );
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:1000'],
        ];
    }
}
