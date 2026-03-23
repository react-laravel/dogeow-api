<?php

namespace App\Http\Requests\Word;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'daily_new_words' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'review_multiplier' => ['sometimes', 'integer', 'in:1,2,3'],
            'current_book_id' => ['sometimes', 'nullable', 'integer', 'exists:word_books,id'],
            'is_auto_pronounce' => ['sometimes', 'boolean'],
        ];
    }
}
