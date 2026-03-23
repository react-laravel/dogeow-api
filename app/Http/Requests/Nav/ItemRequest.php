<?php

namespace App\Http\Requests\Nav;

use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
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
            'nav_category_id' => 'required|exists:nav_categories,id',
            'name' => 'required|string|max:50',
            'url' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_visible' => 'nullable|boolean',
            'is_new_window' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nav_category_id' => '分类 ID',
            'name' => '导航名称',
            'url' => '链接地址',
            'icon' => '图标',
            'description' => '描述',
            'sort_order' => '排序',
            'is_visible' => '是否可见',
            'is_new_window' => '是否新窗口打开',
        ];
    }
}
