<?php

namespace Tests\Feature\Models\Game;

use App\Models\Game\GameCharacter;
use App\Models\Game\GameEquipment;
use App\Models\Game\GameItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCharacterEquipmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_equipment_bonus_includes_affix_bonuses(): void
    {
        $user = User::factory()->create();
        $character = GameCharacter::create([
            'user_id' => $user->id,
            'name' => 'Test Character',
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 1,
            'experience' => 0,
            'hp' => 100,
            'max_hp' => 100,
            'mp' => 50,
            'max_mp' => 50,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'copper' => 0,
        ]);

        // 创建一个带词缀的物品
        $item = GameItem::create([
            'character_id' => $character->id,
            'type' => 'equipment',
            'definition_id' => 1,
            'quantity' => 1,
            'stats' => ['strength' => 10],
            'affixes' => [
                ['strength' => 5, 'dexterity' => 3],
                ['strength' => 2],
            ],
        ]);

        // 将物品装备到角色上
        GameEquipment::create([
            'character_id' => $character->id,
            'item_id' => $item->id,
            'slot' => 'weapon',
        ]);

        $character = $character->fresh();
        $strengthBonus = $character->getEquipmentBonus('strength');

        // 10 (base stats) + 5 (affix 1) + 2 (affix 2) = 17
        $this->assertEquals(17.0, $strengthBonus);
    }

    public function test_current_map_relationship_returns_belongs_to(): void
    {
        $character = new GameCharacter;
        $relation = $character->currentMap();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $relation);
    }
}
