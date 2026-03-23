<?php

namespace App\Http\Requests\Thing;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
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
        $path = $this->path();
        $method = $this->method();
        $isUpdate = in_array($method, ['PUT', 'PATCH']);

        // 基础规则
        $rules = [
            'name' => $isUpdate ? 'sometimes|required|string|max:255' : 'required|string|max:255',
        ];

        // 根据路径判断是房间还是位置
        // 检查路径是否包含 'rooms'
        if (str_contains($path, 'rooms')) {
            $rules['area_id'] = $isUpdate
                ? 'sometimes|required|exists:thing_areas,id'
                : 'required|exists:thing_areas,id';
        }

        // 检查路径是否包含 'spots'
        if (str_contains($path, 'spots')) {
            $rules['room_id'] = $isUpdate
                ? 'sometimes|required|exists:thing_rooms,id'
                : 'required|exists:thing_rooms,id';
        }

        return $rules;
    }

    /**
     * Get the validation messages for the request.
     */
    public function messages(): array
    {
        return [
            'name.required' => '名称不能为空',
            'name.max' => '名称不能超过 255 个字符',
            'area_id.required' => '区域 ID 不能为空',
            'area_id.exists' => '所选区域不存在',
            'room_id.required' => '房间 ID 不能为空',
            'room_id.exists' => '所选房间不存在',
        ];
    }
}
