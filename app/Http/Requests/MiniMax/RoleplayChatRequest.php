<?php

namespace App\Http\Requests\MiniMax;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RoleplayChatRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'character_name' => 'required|string|max:40',
            'character_prompt' => 'required|string|max:2000',
            'user_persona' => 'nullable|string|max:1000',
            'scene' => 'nullable|string|max:500',
            'message' => 'required|string|max:2000',
            'history' => 'sometimes|array|max:20',
            'history.*.role' => 'required|string|in:user,assistant',
            'history.*.content' => 'required|string|max:2000',
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
            'character_name' => '角色名',
            'character_prompt' => 'AI 人设',
            'user_persona' => '用户人设',
            'scene' => '场景',
            'message' => '当前消息',
            'history' => '对话历史',
            'history.*.role' => '历史消息角色',
            'history.*.content' => '历史消息内容',
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'character_name.required' => '角色名不能为空',
            'character_prompt.required' => '请先填写 AI 人设',
            'message.required' => '请输入要发送的内容',
            'history.max' => '对话历史最多保留 20 条消息',
            'history.*.role.in' => '历史消息角色仅支持 user 或 assistant',
        ];
    }
}
