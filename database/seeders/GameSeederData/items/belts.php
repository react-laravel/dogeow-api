<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 93,
        'name' => '布腰带',
        'type' => 'belt',
        'sub_type' => 'cloth',
        'base_stats' => [
            'max_hp' => 10,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 94,
        'name' => '皮带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 25,
            'defense' => 2,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 95,
        'name' => '铁腰带',
        'type' => 'belt',
        'sub_type' => 'plate',
        'base_stats' => [
            'max_hp' => 50,
            'defense' => 5,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 96,
        'name' => '巨人腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 100,
            'vitality' => 10,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 97,
        'name' => '生命腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 200,
            'vitality' => 20,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 98,
        'name' => '泰坦腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 350,
            'strength' => 15,
            'vitality' => 35,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 99,
        'name' => '圣骑士腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 550,
            'defense' => 30,
            'vitality' => 45,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 100,
        'name' => '天使腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 850,
            'vitality' => 60,
            'all_stats' => 10,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 101,
        'name' => '神之腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 1200,
            'strength' => 40,
            'vitality' => 80,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 102,
        'name' => '永恒腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 1700,
            'vitality' => 100,
            'all_stats' => 20,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 103,
        'name' => '混沌腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 2500,
            'strength' => 60,
            'vitality' => 130,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 104,
        'name' => '创世腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 3500,
            'vitality' => 170,
            'all_stats' => 35,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 105,
        'name' => '神王腰带',
        'type' => 'belt',
        'sub_type' => 'leather',
        'base_stats' => [
            'max_hp' => 5000,
            'strength' => 100,
            'vitality' => 220,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
