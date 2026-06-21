<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class KnowledgeGraphRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'data' => 'nullable|array',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => '图谱名称',
            'description' => '描述',
            'data' => '图谱数据',
        ];
    }
}
