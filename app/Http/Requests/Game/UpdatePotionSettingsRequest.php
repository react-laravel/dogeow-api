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
            'auto_use_mp_potion' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [];
    }
}
