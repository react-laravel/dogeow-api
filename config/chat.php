<?php

return [
    'message' => [
        'max_length' => 2000,
        'min_length' => 1,
    ],
    'room' => [
        'name_max_length' => 100,
        'name_min_length' => 3,
        'description_max_length' => 500,
    ],
    'rate_limit' => [
        'messages_per_minute' => 10,
        'window_seconds' => 60,
    ],
    'presence' => [
        'timeout_minutes' => 5,
        'heartbeat_interval_seconds' => 30,
    ],
];
