<?php

use App\Models\Game\GameItemDefinition;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $gems = require database_path('seeders/Game/Data/items/gems.php');

        foreach ($gems as $gem) {
            $assetKey = $gem['asset_key'] ?? ('item_' . $gem['id']);
            unset($gem['asset_key'], $gem['icon_prompt']);

            GameItemDefinition::query()->updateOrCreate(
                ['id' => $gem['id']],
                array_merge($gem, [
                    'icon' => $assetKey . '.png',
                    'description' => '可镶嵌到装备上，提升属性',
                    'is_active' => true,
                ]),
            );
        }
    }

    public function down(): void
    {
        GameItemDefinition::query()
            ->where('type', 'gem')
            ->whereIn('id', [146, 147, 148, 149, 150, 151])
            ->delete();
    }
};
