<?php

namespace App\Http\Requests\Chat;

use App\Utils\CharLengthHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRoomRequest extends FormRequest
{
    private const MIN_ROOM_NAME_LENGTH = 2;

    private const MAX_ROOM_NAME_LENGTH = 20;

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
        $roomId = $this->route('roomId');

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('chat_rooms', 'name')->ignore($roomId)->where('is_active', true),
            ],
            'description' => 'nullable|string|max:1000',
            'is_private' => 'sometimes|boolean',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $name = $this->input('name');

            if (empty($name)) {
                return;
            }

            if (CharLengthHelper::belowMinLength($name, self::MIN_ROOM_NAME_LENGTH)) {
                $validator->errors()->add(
                    'name',
                    '房间名称至少需要' . self::MIN_ROOM_NAME_LENGTH . '个字符'
                );
            }

            if (CharLengthHelper::exceedsMaxLength($name, self::MAX_ROOM_NAME_LENGTH)) {
                $validator->errors()->add(
                    'name',
                    '房间名称不能超过' . self::MAX_ROOM_NAME_LENGTH . '个字符（中文/emoji算2个字符，数字/字母算1个字符）'
                );
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Room Name',
            'description' => 'Room Description',
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
            'name.required' => '房间名称是必需的',
            'name.unique' => '该房间名称已存在',
            'description.max' => '描述不能超过1000个字符',
        ];
    }
}
