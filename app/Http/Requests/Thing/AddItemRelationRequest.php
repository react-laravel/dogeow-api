<?php

namespace App\Http\Requests\Thing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddItemRelationRequest extends FormRequest
{
    private const RELATION_TYPES = [
        'accessory',
        'replacement',
        'related',
        'bundle',
        'parent',
        'child',
    ];

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
            'related_item_id' => 'required|integer|exists:thing_items,id',
            'relation_type' => ['required', 'string', Rule::in(self::RELATION_TYPES)],
            'description' => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_item_id.required' => '关联物品不能为空',
            'related_item_id.integer' => '关联物品 ID 必须为整数',
            'related_item_id.exists' => '关联物品不存在',
            'relation_type.required' => '关联类型不能为空',
            'relation_type.string' => '关联类型格式不正确',
            'description.string' => '关联描述必须是字符串',
            'description.max' => '关联描述不能超过 500 个字符',
        ];
    }
}
