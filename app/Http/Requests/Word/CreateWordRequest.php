<?php

namespace App\Http\Requests\Word;

use Illuminate\Foundation\Http\FormRequest;

class CreateWordRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:255|unique:words,content',
            'phonetic_us' => 'nullable|string|max:255',
            'explanation' => 'nullable|string',
            'example_sentences' => 'nullable|array',
            'example_sentences.*.en' => 'required_with:example_sentences|string',
            'example_sentences.*.zh' => 'nullable|string',
            'education_level_codes' => 'nullable|array',
            'education_level_codes.*' => 'string|in:primary,junior_high,senior_high,cet4,cet6,postgraduate,ielts,toefl,tem4,tem8',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => '单词内容不能为空',
            'content.unique' => '该单词已存在',
            'content.max' => '单词长度不能超过 255 个字符',
            'example_sentences.*.en.required_with' => '例句英文内容不能为空',
        ];
    }
}
