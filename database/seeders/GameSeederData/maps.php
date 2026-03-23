<?php

$maps = [
    // 第一幕 - 森林
    ['name' => '新手营地', 'act' => 1, 'monster_ids' => [1, 2], 'description' => '安全的训练场所'],
    ['name' => '幽暗森林', 'act' => 1, 'monster_ids' => [2, 3], 'description' => '野狼出没的森林'],
    ['name' => '哥布林巢穴', 'act' => 1, 'monster_ids' => [3, 4], 'description' => '哥布林的聚集地'],
    ['name' => '野猪平原', 'act' => 1, 'monster_ids' => [1, 6], 'description' => '野猪王领地'],
    ['name' => '树人圣地', 'act' => 1, 'monster_ids' => [4, 5], 'description' => '树人长老的领地'],

    // 第二幕 - 洞穴
    ['name' => '黑暗洞穴入口', 'act' => 2, 'monster_ids' => [7, 8], 'description' => '通往地下的入口'],
    ['name' => '蜘蛛洞穴', 'act' => 2, 'monster_ids' => [8, 14], 'description' => '蜘蛛的巢穴'],
    ['name' => '骷髅墓地', 'act' => 2, 'monster_ids' => [9, 10], 'description' => '古老的墓地'],
    ['name' => '骸骨大厅', 'act' => 2, 'monster_ids' => [10, 11], 'description' => '骸骨之王的宫殿'],

    // 第三幕 - 地狱
    ['name' => '地狱之门', 'act' => 3, 'monster_ids' => [12, 13], 'description' => '通往地狱的入口'],
    ['name' => '火焰平原', 'act' => 3, 'monster_ids' => [13, 16], 'description' => '燃烧的平原'],
    ['name' => '炎魔洞穴', 'act' => 3, 'monster_ids' => [13, 15], 'description' => '炎魔的栖息地'],
    ['name' => '恶魔要塞', 'act' => 3, 'monster_ids' => [15, 16], 'description' => '恶魔的堡垒'],
    ['name' => '魔王宫殿', 'act' => 3, 'monster_ids' => [16, 14], 'description' => '地狱魔王的宫殿'],

    // 第四幕 - 深渊
    ['name' => '深渊入口', 'act' => 4, 'monster_ids' => [17, 18], 'description' => '通往深渊的入口'],
    ['name' => '黑暗迷宫', 'act' => 4, 'monster_ids' => [18, 19], 'description' => '充满危险的迷宫'],
    ['name' => '虚空裂隙', 'act' => 4, 'monster_ids' => [17, 19], 'description' => '空间扭曲的裂隙'],
    ['name' => '深渊王座', 'act' => 4, 'monster_ids' => [19, 20], 'description' => '深渊领主的王座'],

    // 第五幕 - 天界
    ['name' => '天界入口', 'act' => 5, 'monster_ids' => [21, 22], 'description' => '通往天界的阶梯'],
    ['name' => '天使圣殿', 'act' => 5, 'monster_ids' => [22, 23], 'description' => '天使的圣殿'],
    ['name' => '荣耀大厅', 'act' => 5, 'monster_ids' => [24, 26], 'description' => '荣耀的殿堂'],
    ['name' => '堕落天使领域', 'act' => 5, 'monster_ids' => [24, 25], 'description' => '堕落天使的领地'],
    ['name' => '大天使圣殿', 'act' => 5, 'monster_ids' => [25, 26], 'description' => '大天使长的圣殿'],

    // 第六幕 - 神域
    ['name' => '神域入口', 'act' => 6, 'monster_ids' => [27, 28], 'description' => '通往神域的入口'],
    ['name' => '神仆大厅', 'act' => 6, 'monster_ids' => [28, 29], 'description' => '神仆的居所'],
    ['name' => '神官圣殿', 'act' => 6, 'monster_ids' => [29, 30], 'description' => '神官的圣殿'],
    ['name' => '神将殿', 'act' => 6, 'monster_ids' => [30, 31], 'description' => '神将的宫殿'],
    ['name' => '神王殿', 'act' => 6, 'monster_ids' => [31, 32], 'description' => '神王化身的神殿'],
    ['name' => '审判之庭', 'act' => 6, 'monster_ids' => [31, 32], 'description' => '审判天使的法庭'],

    // 第七幕 - 永恒之境
    ['name' => '永恒入口', 'act' => 7, 'monster_ids' => [33, 34], 'description' => '通往永恒的入口'],
    ['name' => '时空裂谷', 'act' => 7, 'monster_ids' => [34, 35], 'description' => '时空扭曲的裂谷'],
    ['name' => '永恒战场', 'act' => 7, 'monster_ids' => [35, 36], 'description' => '永恒的战场'],
    ['name' => '永恒法师塔', 'act' => 7, 'monster_ids' => [36, 37], 'description' => '永恒法师的高塔'],
    ['name' => '永恒王座', 'act' => 7, 'monster_ids' => [37, 38], 'description' => '永恒之王的王座'],
    ['name' => '永恒圣殿', 'act' => 7, 'monster_ids' => [38, 39], 'description' => '永恒的圣殿'],

    // 第八幕 - 混沌虚空
    ['name' => '混沌入口', 'act' => 8, 'monster_ids' => [40, 41], 'description' => '通往混沌的入口'],
    ['name' => '虚空边缘', 'act' => 8, 'monster_ids' => [41, 42], 'description' => '虚空的边缘'],
    ['name' => '混沌裂隙', 'act' => 8, 'monster_ids' => [42, 43], 'description' => '混沌的裂隙'],
    ['name' => '混沌魔殿', 'act' => 8, 'monster_ids' => [43, 44], 'description' => '混沌魔神的殿堂'],
    ['name' => '混沌源点', 'act' => 8, 'monster_ids' => [44, 45], 'description' => '混沌的源头'],
    ['name' => '混沌王座', 'act' => 8, 'monster_ids' => [45, 46], 'description' => '混沌之王的最终王座'],
];

$assetKeys = [
    'safe-training-camp',
    'dark-enchanted-forest',
    'goblin-cave-lair',
    'wild-boar-plains',
    'treant-sacred-grove',
    'dark-cave-entrance',
    'spider-cave',
    'skeleton-graveyard',
    'bone-hall',
    'hell-gate',
    'burning-fire-plains',
    'fire-demon-cave',
    'demon-fortress',
    'demon-king-palace',
    'abyss-entrance',
    'dark-maze',
    'void-rift',
    'abyss-throne',
    'heaven-gate',
    'angel-temple',
    'hall-of-glory',
    'fallen-angel-realm',
    'archangel-temple',
    'god-realm-entrance',
    'god-servant-hall',
    'god-priest-temple',
    'god-general-palace',
    'god-king-temple',
    'judgment-court',
    'eternal-gate',
    'time-space-rift',
    'eternal-battlefield',
    'eternal-mage-tower',
    'eternal-throne',
    'eternal-sanctuary',
    'chaos-gate',
    'void-edge',
    'chaos-rift',
    'chaos-demon-hall',
    'chaos-origin-point',
    'chaos-king-throne',
];

return array_map(
    fn (array $map, int $index) => array_merge($map, [
        'asset_key' => $assetKeys[$index],
    ]),
    $maps,
    array_keys($maps)
);
