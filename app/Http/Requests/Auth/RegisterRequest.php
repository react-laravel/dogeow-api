<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => '姓名是必填项。',
            'email.required' => '邮箱是必填项。',
            'email.email' => '邮箱格式不正确。',
            'email.unique' => '该邮箱已被使用。',
            'password.required' => '密码是必填项。',
            'password.min' => '密码至少需要 8 个字符。',
            'password.confirmed' => '两次输入的密码不一致。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => '姓名',
            'email' => '邮箱',
            'password' => '密码',
        ];
    }
}
