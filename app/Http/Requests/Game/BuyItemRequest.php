<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class BuyItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'item_id' => 'required|integer|exists:game_item_definitions,id',
            'quantity' => 'sometimes|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'item_id.required' => '物品 ID 不能为空',
            'item_id.exists' => '物品不存在',
            'quantity.min' => '数量不能小于 1',
        ];
    }
}
