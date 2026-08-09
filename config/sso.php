<?php

$rpgReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('RPG_SSO_RETURN_ORIGINS', 'https://rpg.dogeow.com,http://localhost:3001,http://127.0.0.1:3001'))
)));

$gameReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('GAME_SSO_RETURN_ORIGINS', 'https://game.dogeow.com,http://localhost:3002,http://127.0.0.1:3002'))
)));

$repoWatchReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('REPO_WATCH_SSO_RETURN_ORIGINS', 'https://repo-watch.dogeow.com,http://localhost:3012,http://127.0.0.1:3012'))
)));

$chatReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('CHAT_SSO_RETURN_ORIGINS', 'https://chat.dogeow.com,http://localhost:3013,http://127.0.0.1:3013'))
)));

$mysqlCompareReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('MYSQL_COMPARE_SSO_RETURN_ORIGINS', 'https://mysql-compare.dogeow.com,http://localhost:3006,http://127.0.0.1:3006'))
)));

$knowledgeGraphReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env('KNOWLEDGE_GRAPH_SSO_RETURN_ORIGINS', 'https://mind.dogeow.com,http://localhost:5173,http://127.0.0.1:5173'))
)));

$aiTranslateReturnOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'AI_TRANSLATE_SSO_RETURN_ORIGINS',
        // Chrome extension 使用 https://<id>.chromiumapp.org；开发时还可回落到扩展页。
        'https://*.chromiumapp.org,chrome-extension://*,moz-extension://*'
    ))
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
        'repo-watch' => [
            'secret' => env('REPO_WATCH_SSO_CLIENT_SECRET'),
            'callback_url' => env('REPO_WATCH_SSO_CALLBACK_URL', 'https://repo-watch.dogeow.com/auth/callback'),
            'return_origins' => $repoWatchReturnOrigins,
        ],
        'chat' => [
            'secret' => env('CHAT_SSO_CLIENT_SECRET'),
            'callback_url' => env('CHAT_SSO_CALLBACK_URL', 'https://chat.dogeow.com/auth/callback'),
            'return_origins' => $chatReturnOrigins,
        ],
        'mysql-compare' => [
            'secret' => env('MYSQL_COMPARE_SSO_CLIENT_SECRET'),
            'callback_url' => env('MYSQL_COMPARE_SSO_CALLBACK_URL', 'https://mysql-compare.dogeow.com/auth/callback'),
            'return_origins' => $mysqlCompareReturnOrigins,
            'admin_only' => true,
        ],
        'knowledge-graph' => [
            'callback_url' => env('KNOWLEDGE_GRAPH_SSO_CALLBACK_URL', 'https://mind.dogeow.com/'),
            'return_origins' => $knowledgeGraphReturnOrigins,
            'public_client' => true,
            'issue_api_token' => true,
        ],
        // 浏览器扩展：public PKCE + 直接把 ticket 回跳到 return_to（chromiumapp.org）
        'ai-translate' => [
            'callback_url' => env('AI_TRANSLATE_SSO_CALLBACK_URL', 'https://next.dogeow.com/'),
            'return_origins' => $aiTranslateReturnOrigins,
            'public_client' => true,
            'issue_api_token' => true,
            'use_return_to_as_callback' => true,
        ],
    ],
];
