<?php

// 由 php artisan game:export-seeder-definitions 从数据库导出，供 GameSeeder 使用
return [

    [
        'id' => 123,
        'name' => '木制护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 15,
        ],
        'required_level' => 1,
    ],

    [
        'id' => 124,
        'name' => '骨制护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 30,
            'max_mana' => 15,
        ],
        'required_level' => 5,
    ],

    [
        'id' => 125,
        'name' => '水晶护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 50,
            'defense' => 5,
            'max_mana' => 50,
        ],
        'required_level' => 10,
    ],

    [
        'id' => 126,
        'name' => '狮子护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 20,
            'max_hp' => 80,
            'defense' => 15,
        ],
        'required_level' => 15,
    ],

    [
        'id' => 127,
        'name' => '猫眼护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'crit_rate' => 0.15,
            'dexterity' => 15,
            'crit_damage' => 0.3,
        ],
        'required_level' => 15,
    ],

    [
        'id' => 128,
        'name' => '队长护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 100,
            'max_mana' => 100,
            'all_stats' => 10,
        ],
        'required_level' => 20,
    ],

    [
        'id' => 129,
        'name' => '地狱护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 40,
            'strength' => 20,
            'crit_damage' => 0.5,
        ],
        'required_level' => 25,
    ],

    [
        'id' => 130,
        'name' => '神圣护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 300,
            'max_mana' => 300,
            'all_stats' => 20,
        ],
        'required_level' => 30,
    ],

    [
        'id' => 131,
        'name' => '圣骑士护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 60,
            'max_hp' => 400,
            'defense' => 40,
        ],
        'required_level' => 40,
    ],

    [
        'id' => 132,
        'name' => '天使护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 500,
            'max_mana' => 500,
            'all_stats' => 25,
        ],
        'required_level' => 50,
    ],

    [
        'id' => 133,
        'name' => '神圣天使护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 100,
            'all_stats' => 30,
            'crit_damage' => 0.6,
        ],
        'required_level' => 60,
    ],

    [
        'id' => 134,
        'name' => '永恒护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 1000,
            'max_mana' => 1000,
            'all_stats' => 35,
        ],
        'required_level' => 70,
    ],

    [
        'id' => 135,
        'name' => '混沌护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 180,
            'crit_rate' => 0.2,
            'crit_damage' => 0.8,
        ],
        'required_level' => 80,
    ],

    [
        'id' => 136,
        'name' => '创世护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'max_hp' => 2000,
            'max_mana' => 2000,
            'all_stats' => 50,
        ],
        'required_level' => 90,
    ],

    [
        'id' => 137,
        'name' => '神王护符',
        'type' => 'amulet',
        'sub_type' => null,
        'base_stats' => [
            'attack' => 300,
            'all_stats' => 60,
            'crit_damage' => 1.2,
        ],
        'required_level' => 100,
    ],
];
