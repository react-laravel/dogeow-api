<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 80,
        'name' => '布鞋',
        'type' => 'boots',
        'sub_type' => 'cloth',
        'base_stats' => [
            'defense' => 1,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 81,
        'name' => '皮靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 3,
            'dexterity' => 2,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 82,
        'name' => '铁靴',
        'type' => 'boots',
        'sub_type' => 'plate',
        'base_stats' => [
            'defense' => 8,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 83,
        'name' => '迅捷之靴',
        'type' => 'boots',
        'sub_type' => 'plate',
        'base_stats' => [
            'defense' => 12,
            'crit_rate' => 0.05,
            'dexterity' => 8,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 84,
        'name' => '游侠之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 20,
            'dexterity' => 15,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 85,
        'name' => '幻影之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 30,
            'dexterity' => 25,
            'crit_damage' => 0.15,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 86,
        'name' => '圣骑士靴',
        'type' => 'boots',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 150,
            'defense' => 45,
            'dexterity' => 30,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 87,
        'name' => '天使之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 35,
            'crit_rate' => 0.12,
            'dexterity' => 45,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 88,
        'name' => '神速之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 50,
            'dexterity' => 65,
            'crit_damage' => 0.3,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 89,
        'name' => '永恒之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 70,
            'crit_rate' => 0.18,
            'dexterity' => 90,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 90,
        'name' => '混沌之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 100,
            'dexterity' => 120,
            'crit_damage' => 0.5,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 91,
        'name' => '创世之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 140,
            'crit_rate' => 0.25,
            'dexterity' => 160,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 92,
        'name' => '神王之靴',
        'type' => 'boots',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 200,
            'all_stats' => 30,
            'dexterity' => 220,
            'crit_damage' => 0.8,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
