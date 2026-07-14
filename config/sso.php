<?php

$rpgReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('RPG_SSO_RETURN_ORIGINS', 'https://rpg.dogeow.com,http://localhost:3001,http://127.0.0.1:3001'))
)));

$gameReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('GAME_SSO_RETURN_ORIGINS', 'https://game.dogeow.com,http://localhost:3002,http://127.0.0.1:3002'))
)));

return [
    'ticket_lifetime_seconds' => (int) env('SSO_TICKET_LIFETIME_SECONDS', 60),

    'clients' => [
        'rpg' => [
            'secret' => env('RPG_SSO_CLIENT_SECRET'),
            'callback_url' => env('RPG_SSO_CALLBACK_URL', 'https://rpg.dogeow.com/auth/callback'),
            'return_origins' => $rpgReturnOrigins,
        ],
        'game' => [
            'secret' => env('GAME_SSO_CLIENT_SECRET'),
            'callback_url' => env('GAME_SSO_CALLBACK_URL', 'https://game.dogeow.com/auth/callback'),
            'return_origins' => $gameReturnOrigins,
        ],
    ],
];
