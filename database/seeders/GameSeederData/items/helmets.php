<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 40,
        'name' => '布帽',
        'type' => 'helmet',
        'sub_type' => 'cloth',
        'base_stats' => [
            'max_hp' => 10,
            'defense' => 2,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 41,
        'name' => '皮帽',
        'type' => 'helmet',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 20,
            'defense' => 5,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 42,
        'name' => '铁盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 50,
            'defense' => 15,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 43,
        'name' => '秘银头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 80,
            'defense' => 5,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 44,
        'name' => '龙骨头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 150,
            'defense' => 40,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 45,
        'name' => '神圣头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 250,
            'defense' => 60,
            'max_mana' => 50,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 46,
        'name' => '圣骑士头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 400,
            'defense' => 90,
            'vitality' => 20,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 47,
        'name' => '天使头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 600,
            'defense' => 130,
            'all_stats' => 10,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 48,
        'name' => '神之头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 900,
            'defense' => 180,
            'vitality' => 40,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 49,
        'name' => '永恒头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 1300,
            'defense' => 250,
            'all_stats' => 20,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 50,
        'name' => '混沌头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 2000,
            'defense' => 350,
            'vitality' => 60,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 51,
        'name' => '创世头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 3000,
            'defense' => 480,
            'all_stats' => 35,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 52,
        'name' => '神王头盔',
        'type' => 'helmet',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 4500,
            'defense' => 650,
            'vitality' => 100,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
