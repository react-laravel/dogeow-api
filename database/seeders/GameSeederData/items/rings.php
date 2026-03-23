<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 106,
        'name' => '铜戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 2,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 107,
        'name' => '银戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 5,
            'crit_rate' => 0.02,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 108,
        'name' => '金戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 10,
            'crit_rate' => 0.05,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 109,
        'name' => '红宝石戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 15,
            'crit_damage' => 0.2,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 110,
        'name' => '蓝宝石戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 12,
            'max_mana' => 100,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 111,
        'name' => '翡翠戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 100,
            'defense' => 10,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 112,
        'name' => '乌鸦戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 25,
            'crit_rate' => 0.1,
            'crit_damage' => 0.3,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 113,
        'name' => '乔丹之石',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 30,
            'max_mana' => 150,
            'all_stats' => 5,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 114,
        'name' => '矮人戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 200,
            'defense' => 30,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 115,
        'name' => '魔法戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'energy' => 20,
            'max_mana' => 300,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 116,
        'name' => '圣骑士戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 45,
            'defense' => 25,
            'all_stats' => 10,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 117,
        'name' => '天使之戒',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 65,
            'max_hp' => 300,
            'crit_rate' => 0.12,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 118,
        'name' => '神之戒指',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 90,
            'all_stats' => 15,
            'crit_damage' => 0.5,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 119,
        'name' => '永恒之戒',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 120,
            'crit_rate' => 0.18,
            'crit_damage' => 0.6,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 120,
        'name' => '混沌之戒',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 160,
            'max_hp' => 600,
            'all_stats' => 25,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 121,
        'name' => '创世之戒',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 220,
            'crit_rate' => 0.22,
            'crit_damage' => 0.8,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 122,
        'name' => '神王之戒',
        'type' => 'ring',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 300,
            'all_stats' => 40,
            'crit_damage' => 1,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
