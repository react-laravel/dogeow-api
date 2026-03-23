<?php

namespace App\Http\Requests\Note;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'content' => 'sometimes|nullable|string',
            'content_markdown' => 'sometimes|nullable|string',
            'is_draft' => 'sometimes|boolean',
            'slug' => 'sometimes|nullable|string|max:255',
            'summary' => 'sometimes|nullable|string',
            'is_wiki' => 'sometimes|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => '标题',
            'content' => '内容',
            'content_markdown' => 'Markdown 内容',
            'is_draft' => '草稿状态',
            'slug' => 'Slug',
            'summary' => '摘要',
            'is_wiki' => 'Wiki 节点',
        ];
    }
}
