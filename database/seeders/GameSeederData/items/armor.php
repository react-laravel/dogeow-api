<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 53,
        'name' => '布衣',
        'type' => 'armor',
        'sub_type' => 'cloth',
        'base_stats' => [
            'max_hp' => 20,
            'defense' => 5,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 54,
        'name' => '皮甲',
        'type' => 'armor',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 40,
            'defense' => 12,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 55,
        'name' => '锁子甲',
        'type' => 'armor',
        'sub_type' => 'mail',
        'base_stats' => [
            'max_hp' => 80,
            'defense' => 25,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 56,
        'name' => '板甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 120,
            'defense' => 40,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 57,
        'name' => '秘银甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 200,
            'defense' => 65,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 58,
        'name' => '龙鳞甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 20,
            'max_hp' => 350,
            'defense' => 100,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 59,
        'name' => '神之铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 500,
            'defense' => 20,
        ],
        'required_level' => 30,
        'sockets' => 0,
    ],

    [
        'id' => 60,
        'name' => '圣骑士铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 800,
            'defense' => 220,
            'vitality' => 30,
        ],
        'required_level' => 40,
        'sockets' => 0,
    ],

    [
        'id' => 61,
        'name' => '天使铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 1200,
            'defense' => 300,
            'all_stats' => 15,
        ],
        'required_level' => 50,
        'sockets' => 0,
    ],

    [
        'id' => 62,
        'name' => '神圣铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 1800,
            'defense' => 400,
            'vitality' => 50,
        ],
        'required_level' => 60,
        'sockets' => 0,
    ],

    [
        'id' => 63,
        'name' => '永恒铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 2600,
            'defense' => 550,
            'all_stats' => 25,
        ],
        'required_level' => 70,
        'sockets' => 0,
    ],

    [
        'id' => 64,
        'name' => '混沌铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 4000,
            'defense' => 750,
            'vitality' => 80,
        ],
        'required_level' => 80,
        'sockets' => 0,
    ],

    [
        'id' => 65,
        'name' => '创世铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 6000,
            'defense' => 1000,
            'all_stats' => 40,
        ],
        'required_level' => 90,
        'sockets' => 0,
    ],

    [
        'id' => 66,
        'name' => '神王铠甲',
        'type' => 'armor',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 9000,
            'defense' => 200,
            'vitality' => 120,
        ],
        'required_level' => 100,
        'sockets' => 0,
    ],
];
