<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePotionSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'auto_use_hp_potion' => 'nullable|boolean',
            'hp_potion_threshold' => 'nullable|integer|min:1|max:100|required_if_accepted:auto_use_hp_potion',
            'auto_use_mp_potion' => 'nullable|boolean',
            'mp_potion_threshold' => 'nullable|integer|min:1|max:100|required_if_accepted:auto_use_mp_potion',
        ];
    }

    public function messages(): array
    {
        return [
            'hp_potion_threshold.required_if_accepted' => '启用自动 HP 药水时必须设置 HP 药水阈值',
            'hp_potion_threshold.min' => 'HP 药水阈值最小为 1',
            'hp_potion_threshold.max' => 'HP 药水阈值最大为 100',
            'mp_potion_threshold.required_if_accepted' => '启用自动 MP 药水时必须设置 MP 药水阈值',
            'mp_potion_threshold.min' => 'MP 药水阈值最小为 1',
            'mp_potion_threshold.max' => 'MP 药水阈值最大为 100',
        ];
    }
}
