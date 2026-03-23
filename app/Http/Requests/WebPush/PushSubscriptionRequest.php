<?php

namespace App\Http\Requests\WebPush;

use Illuminate\Foundation\Http\FormRequest;

class PushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'endpoint' => 'required|string|max:2000',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'endpoint.required' => '推送 endpoint 不能为空。',
            'keys.required' => '推送 keys 不能为空。',
            'keys.p256dh.required' => 'keys.p256dh 不能为空。',
            'keys.auth.required' => 'keys.auth 不能为空。',
        ];
    }
}
