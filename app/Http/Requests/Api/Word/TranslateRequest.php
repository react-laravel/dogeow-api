<?php

namespace App\Http\Requests\Api\Word;

use Illuminate\Foundation\Http\FormRequest;

class TranslateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:2000'],
            'langpair' => ['sometimes', 'string', 'max:20', 'regex:/^[a-z]{2}\|[a-z]{2}$/i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text.required' => '请输入要翻译的文本',
            'text.max' => '文本过长',
            'langpair.regex' => '语言对格式无效',
        ];
    }
}
