<?php

namespace App\Models\Game;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $definition_id
 * @property int $quantity
 * @property bool $is_in_storage
 * @property bool $is_equipped
 * @property int|null $slot_index
 * @property int $sell_price
 * @property array<string,mixed> $stats
 * @property array<int,array<string,mixed>>|null $affixes
 * @property int|null $sockets
 * @property GameItemDefinition|null $definition
 */
class GameItem extends GameItemDefinition
{
    protected $table = 'game_items';

    protected $fillable = [
        'character_id',
        'definition_id',
        'quality',
        'stats',
        'affixes',
        'is_in_storage',
        'is_equipped',
        'quantity',
        'slot_index',
        'sockets',
        'sell_price',
    ];

    protected function casts(): array
    {
        return [
            'stats' => 'array',
            'affixes' => 'array',
            'is_in_storage' => 'boolean',
            'is_equipped' => 'boolean',
        ];
    }

    public const QUALITIES = [
        'common',
        'magic',
        'rare',
        'legendary',
        'mythic',
    ];

    public const QUALITY_COLORS = [
        'common' => '#ffffff',
        'magic' => '#6888ff',
        'rare' => '#ffcc00',
        'legendary' => '#ff8000',
        'mythic' => '#00ff00',
    ];

    public const QUALITY_MULTIPLIERS = [
        'common' => 1.0,
        'magic' => 1.3,
        'rare' => 1.6,
        'legendary' => 2.0,
        'mythic' => 2.5,
    ];

    /**
     * 属性价格系数（每1点属性对应的基础价格）
     * 根据属性在战斗中的价值设定
     */
    public const STAT_PRICES = [
        'attack' => 3,        // 攻击力：每点 3 铜
        'defense' => 2,       // 防御力：每点 2 铜
        'max_hp' => 0.5,      // 生命值：每点 0.5 铜
        'max_mana' => 0.3,    // 法力值：每点 0.3 铜
        'crit_rate' => 500,   // 暴击率：每 1%（0.01）500 铜
        'crit_damage' => 200, // 暴击伤害：每 10%（0.1）200 铜
    ];

    /**
     * 物品类型价格系数
     */
    public const TYPE_PRICE_MULTIPLIERS = [
        'weapon' => 1.2,
        'helmet' => 1.0,
        'armor' => 1.3,
        'gloves' => 0.8,
        'boots' => 0.8,
        'belt' => 0.7,
        'ring' => 1.5,
        'amulet' => 1.8,
        'potion' => 0.5,
        'gem' => 1.0,
    ];

    /**
     * 获取所属角色
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(GameCharacter::class, 'character_id');
    }

    /**
     * 获取物品定义
     */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(GameItemDefinition::class, 'definition_id');
    }

    /**
     * 获取装备上的宝石
     */
    public function gems(): HasMany
    {
        return $this->hasMany(GameItemGem::class, 'item_id')->orderBy('socket_index');
    }

    /**
     * 使用 bcmath 将数值规范为指定小数位，避免浮点精度问题（如暴击率 0.020000000000000004）
     *
     * @param  array<string, mixed>  $arr  键值对（如 stats、affixes）
     * @param  int  $scale  小数位数，率类属性用 4
     * @return array<string, mixed>
     */
    public static function normalizeStatsPrecision(array $arr, int $scale = 4): array
    {
        $result = [];
        foreach ($arr as $key => $value) {
            if (is_numeric($value)) {
                $result[$key] = (float) bcadd((string) $value, '0', $scale);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 获取完整属性（基础 + 随机词缀 + 宝石）
     */
    public function getTotalStats(): array
    {
        $stats = $this->stats ?? [];

        // 添加随机词缀属性
        foreach ($this->affixes ?? [] as $affix) {
            foreach ($affix as $key => $value) {
                $stats[$key] = bcadd((string) ($stats[$key] ?? 0), (string) $value, 4);
            }
        }

        // 添加宝石属性
        foreach ($this->gems ?? [] as $gem) {
            $gemStats = $gem->getGemStats();
            foreach ($gemStats as $key => $value) {
                $stats[$key] = bcadd((string) ($stats[$key] ?? 0), (string) $value, 4);
            }
        }

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        if (isset($array['stats']) && is_array($array['stats'])) {
            $array['stats'] = self::normalizeStatsPrecision($array['stats']);
        }
        if (isset($array['affixes']) && is_array($array['affixes'])) {
            $array['affixes'] = array_map(
                fn (array $affix): array => self::normalizeStatsPrecision($affix),
                $array['affixes']
            );
        }

        return $array;
    }

    /**
     * 获取品质颜色
     */
    public function getQualityColor(): string
    {
        return self::QUALITY_COLORS[$this->quality];
    }

    /**
     * 获取品质倍率
     */
    public function getQualityMultiplier(): float
    {
        return self::QUALITY_MULTIPLIERS[$this->quality];
    }

    /**
     * 获取物品名称（带品质前缀）
     */
    public function getDisplayName(): string
    {
        $prefix = match ($this->quality) {
            'magic' => '魔法 ',
            'rare' => '稀有 ',
            'legendary' => '传奇 ',
            'mythic' => '神话 ',
            default => '',
        };

        return $prefix . ($this->definition->name ?? '未知物品');
    }

    /**
     * 检查角色是否可以使用该物品
     */
    public function canEquip(GameCharacter $character): array
    {
        $definition = $this->definition;

        if ($character->level < $definition->required_level) {
            return [
                'can_equip' => false,
                'reason' => "需要等级 {$definition->required_level}",
            ];
        }

        return [
            'can_equip' => true,
            'reason' => null,
        ];
    }

    /**
     * 计算物品卖出价格（基于属性的公式）
     *
     * @return int 卖出价格（铜币）
     */
    public function calculateSellPrice(): int
    {
        // 药水：基于恢复量计算
        if ($this->definition?->type === 'potion') {
            return $this->calculatePotionPrice();
        }

        // 宝石：基于属性计算
        if ($this->definition?->type === 'gem') {
            return $this->calculateGemPrice();
        }

        // 装备：基于属性计算
        return $this->calculateEquipmentPrice();
    }

    /**
     * 计算药水价格
     */
    private function calculatePotionPrice(): int
    {
        /** @var array<string, mixed> $stats */
        $stats = $this->definition->base_stats ?? [];
        $hpRestore = $stats['max_hp'] ?? 0;
        $manaRestore = $stats['max_mana'] ?? 0;

        // HP 恢复每点 0.3 铜，MP 恢复每点 0.2 铜
        $price = (int) ($hpRestore * 0.3 + $manaRestore * 0.2);

        return max(1, $price);
    }

    /**
     * 计算宝石价格
     */
    private function calculateGemPrice(): int
    {
        /** @var array<string, mixed> $gemStats */
        $gemStats = $this->definition->gem_stats ?? [];
        $price = 0;

        foreach ($gemStats as $stat => $value) {
            $pricePerPoint = self::STAT_PRICES[$stat] ?? 1;
            $price += (int) ($value * $pricePerPoint);
        }

        return max(1, $price);
    }

    /**
     * 计算装备价格
     */
    private function calculateEquipmentPrice(): int
    {
        $totalStats = $this->getTotalStats();
        // 若物品自身 stats 为空（如旧数据），用 definition 的 base_stats 参与计价
        if (empty($totalStats) && $this->definition) {
            $totalStats = $this->definition->base_stats ?? [];
        }
        $basePrice = 0;

        // 1. 计算基础属性价格
        foreach ($totalStats as $stat => $value) {
            $pricePerPoint = self::STAT_PRICES[$stat] ?? 1;
            $basePrice += (int) ($value * $pricePerPoint);
        }

        // 2. 应用品质倍率
        $qualityMultiplier = $this->getQualityMultiplier();

        // 3. 应用物品类型倍率
        $type = $this->definition->type ?? 'weapon';
        $typeMultiplier = self::TYPE_PRICE_MULTIPLIERS[$type] ?? 1.0;

        // 4. 应用需求等级加成（高等级装备更值钱）
        $requiredLevel = $this->definition->required_level ?? 1;
        $levelMultiplier = 1 + ($requiredLevel / 50); // 每50级价格翻倍

        // 5. 插槽加成
        $socketCount = $this->sockets ?? 0;
        $socketBonus = $socketCount * 10; // 每个插槽额外 10 铜

        // 6. 计算最终价格（卖出价格为估算价值的 50%）
        $finalPrice = (int) (($basePrice * $qualityMultiplier * $typeMultiplier * $levelMultiplier) + $socketBonus) * 0.5;

        return max(1, (int) $finalPrice);
    }
}
