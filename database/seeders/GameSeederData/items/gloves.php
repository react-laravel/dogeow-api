<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 67,
        'name' => '布手套',
        'type' => 'gloves',
        'sub_type' => 'cloth',
        'base_stats' => [
            'defense' => 1,
        ],
        'required_level' => 1,
        'sockets' => 0,
    ],

    [
        'id' => 68,
        'name' => '皮手套',
        'type' => 'gloves',
        'sub_type' => 'leather',
        'base_stats' => [
            'defense' => 3,
            'crit_rate' => 0.02,
        ],
        'required_level' => 5,
        'sockets' => 0,
    ],

    [
        'id' => 69,
        'name' => '铁手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 3,
            'defense' => 8,
        ],
        'required_level' => 10,
        'sockets' => 0,
    ],

    [
        'id' => 70,
        'name' => '精钢手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 8,
            'defense' => 15,
            'crit_rate' => 0.03,
        ],
        'required_level' => 15,
        'sockets' => 0,
    ],

    [
        'id' => 71,
        'name' => '龙皮手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 15,
            'defense' => 25,
        ],
        'required_level' => 20,
        'sockets' => 0,
    ],

    [
        'id' => 72,
        'name' => '力量手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 30,
            'strength' => 10,
        ],
        'required_level' => 25,
        'sockets' => 0,
    ],

    [
        'id' => 73,
        'name' => '圣骑士手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 40,
            'defense' => 40,
            'strength' => 15,
        ],
        'required_level' => 35,
        'sockets' => 0,
    ],

    [
        'id' => 74,
        'name' => '天使手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 60,
            'all_stats' => 10,
            'crit_rate' => 0.1,
        ],
        'required_level' => 45,
        'sockets' => 0,
    ],

    [
        'id' => 75,
        'name' => '神之手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 90,
            'defense' => 60,
            'crit_damage' => 0.4,
        ],
        'required_level' => 55,
        'sockets' => 0,
    ],

    [
        'id' => 76,
        'name' => '永恒手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 130,
            'all_stats' => 20,
            'crit_rate' => 0.15,
        ],
        'required_level' => 65,
        'sockets' => 0,
    ],

    [
        'id' => 77,
        'name' => '混沌手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 180,
            'strength' => 50,
            'crit_damage' => 0.6,
        ],
        'required_level' => 75,
        'sockets' => 0,
    ],

    [
        'id' => 78,
        'name' => '创世手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 250,
            'all_stats' => 35,
            'crit_rate' => 0.2,
        ],
        'required_level' => 85,
        'sockets' => 0,
    ],

    [
        'id' => 79,
        'name' => '神王手套',
        'type' => 'gloves',
        'sub_type' => 'plate',
        'base_stats' => [
            'attack' => 350,
            'strength' => 80,
            'crit_damage' => 1,
        ],
        'required_level' => 95,
        'sockets' => 0,
    ],
];
