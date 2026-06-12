<?php

namespace Tests\Unit\Support;

use App\Support\Game\RpgAssetIconNormalizer;
use Tests\TestCase;

class RpgAssetIconNormalizerTest extends TestCase
{
    public function test_normalizes_legacy_monster_icon_names(): void
    {
        $this->assertSame('skeleton-mage.png', RpgAssetIconNormalizer::normalizeMonster('monster_13.png'));
    }

    public function test_normalizes_legacy_remote_monster_urls(): void
    {
        $this->assertSame(
            'https://upyun.dogeow.com/game/rpg/monsters/bone-king.png',
            RpgAssetIconNormalizer::normalizeMonster('https://upyun.dogeow.com/game/rpg/monsters/monster_14.png')
        );
    }

    public function test_keeps_current_monster_icon_names_unchanged(): void
    {
        $this->assertSame('wild-wolf.png', RpgAssetIconNormalizer::normalizeMonster('wild-wolf.png'));
    }

    public function test_normalizes_legacy_skill_icon_names(): void
    {
        $this->assertSame('iron-wall.png', RpgAssetIconNormalizer::normalizeSkill('skill_24.png'));
    }

    public function test_normalizes_monster_icons_inside_combat_payload(): void
    {
        $monster = RpgAssetIconNormalizer::normalizeMonsterCombatPayload([
            'id' => 13,
            'icon' => 'monster_13.png',
            'name' => '骷髅法师',
        ]);

        $this->assertSame('skeleton-mage.png', $monster['icon']);
    }

    public function test_normalizes_legacy_item_icon_names(): void
    {
        $this->assertSame('beginner-sword.png', RpgAssetIconNormalizer::normalizeItem('item_1.png'));
    }

    public function test_normalizes_legacy_map_background_names(): void
    {
        $this->assertSame(
            'safe-training-camp.jpg',
            RpgAssetIconNormalizer::normalizeMapBackground('map_1.jpg')
        );
    }
}
