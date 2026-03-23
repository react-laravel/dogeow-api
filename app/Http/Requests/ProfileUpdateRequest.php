<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => '姓名是必填项',
            'name.string' => '姓名必须是字符串',
            'name.max' => '姓名不能超过 255 个字符',
            'email.required' => '邮箱是必填项',
            'email.string' => '邮箱必须是字符串',
            'email.email' => '邮箱格式不正确',
            'email.max' => '邮箱不能超过 255 个字符',
            'email.unique' => '该邮箱已被使用',
        ];
    }
}
