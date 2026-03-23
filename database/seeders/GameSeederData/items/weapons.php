<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 1,
        'name' => '新手剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 5,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 2,
        'name' => '铁剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 15,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 3,
        'name' => '精钢剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 30,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 4,
        'name' => '符文剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 50,
            'crit_rate' => 0.05,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 5,
        'name' => '龙牙剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 80,
            'crit_damage' => 0.3,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 6,
        'name' => '泰坦之剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 120,
            'max_hp' => 100,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 7,
        'name' => '圣骑士剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 160,
            'max_hp' => 150,
            'defense' => 30,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 8,
        'name' => '狂战士之剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 220,
            'strength' => 25,
            'crit_damage' => 0.5,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 9,
        'name' => '天使之剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 300,
            'max_hp' => 300,
            'crit_rate' => 0.15,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 10,
        'name' => '神圣审判剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 400,
            'all_stats' => 15,
            'crit_damage' => 0.8,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 11,
        'name' => '神王之剑',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 550,
            'max_hp' => 800,
            'crit_rate' => 0.2,
            'crit_damage' => 1,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 12,
        'name' => '永恒之刃',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 700,
            'all_stats' => 30,
            'crit_damage' => 1.2,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 13,
        'name' => '混沌斩裂者',
        'type' => 'weapon',
        'sub_type' => 'sword',
        'base_stats' => [
            'attack' => 900,
            'max_hp' => 1500,
            'crit_rate' => 0.25,
            'crit_damage' => 1.5,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],

    [
        'id' => 14,
        'name' => '新手法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 3,
            'max_mana' => 20,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 15,
        'name' => '橡木法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 10,
            'max_mana' => 50,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 16,
        'name' => '水晶法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 25,
            'max_mana' => 100,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 17,
        'name' => '月亮法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 45,
            'max_mana' => 200,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 18,
        'name' => '星辰法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 70,
            'max_mana' => 350,
            'crit_rate' => 0.08,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 19,
        'name' => '虚空法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 100,
            'max_mana' => 500,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 20,
        'name' => '奥术法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 140,
            'energy' => 20,
            'max_mana' => 700,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 21,
        'name' => '凤凰法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 200,
            'max_mana' => 1000,
            'crit_damage' => 0.4,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 22,
        'name' => '天使法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 280,
            'max_mana' => 1500,
            'crit_rate' => 0.12,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 23,
        'name' => '大魔导师法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 380,
            'energy' => 40,
            'max_mana' => 2200,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 24,
        'name' => '神之启示法杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 500,
            'max_mana' => 3000,
            'all_stats' => 20,
            'crit_rate' => 0.18,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 25,
        'name' => '永恒魔力源',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 650,
            'max_mana' => 4000,
            'crit_damage' => 1,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 26,
        'name' => '混沌魔杖',
        'type' => 'weapon',
        'sub_type' => 'staff',
        'base_stats' => [
            'attack' => 850,
            'energy' => 60,
            'max_mana' => 5500,
            'crit_rate' => 0.22,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],

    [
        'id' => 27,
        'name' => '新手弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 4,
            'crit_rate' => 0.02,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 28,
        'name' => '长弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 12,
            'crit_rate' => 0.05,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 29,
        'name' => '精灵弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 28,
            'crit_rate' => 0.1,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 30,
        'name' => '猎魔弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 48,
            'crit_rate' => 0.15,
            'crit_damage' => 0.25,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 31,
        'name' => '暗影之弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 75,
            'crit_rate' => 0.2,
            'dexterity' => 15,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 32,
        'name' => '风神之弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 110,
            'crit_rate' => 0.25,
            'crit_damage' => 0.5,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 33,
        'name' => '精灵王之弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 150,
            'crit_rate' => 0.28,
            'dexterity' => 25,
            'crit_damage' => 0.6,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 34,
        'name' => '凤凰羽弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 210,
            'crit_rate' => 0.32,
            'crit_damage' => 0.8,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 35,
        'name' => '天使之弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 290,
            'crit_rate' => 0.35,
            'dexterity' => 40,
            'crit_damage' => 1,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 36,
        'name' => '神射手之弓',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 390,
            'crit_rate' => 0.4,
            'crit_damage' => 1.2,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 37,
        'name' => '神之狩猎者',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 520,
            'all_stats' => 20,
            'crit_rate' => 0.45,
            'crit_damage' => 1.5,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 38,
        'name' => '永恒追猎者',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 680,
            'crit_rate' => 0.5,
            'crit_damage' => 1.8,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 39,
        'name' => '混沌穿刺者',
        'type' => 'weapon',
        'sub_type' => 'bow',
        'base_stats' => [
            'attack' => 880,
            'crit_rate' => 0.55,
            'dexterity' => 80,
            'crit_damage' => 2.2,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
