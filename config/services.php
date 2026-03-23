<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'knowledge' => [
        // 知识库索引构建接口(Next.js API)，需在 .env 配置 KNOWLEDGE_BUILD_INDEX_URL
        'build_index_url' => env('KNOWLEDGE_BUILD_INDEX_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | System status (OpenClaw + Supervisor)
    |--------------------------------------------------------------------------
    */
    'openclaw' => [
        'health_url' => env('OPENCLAW_HEALTH_URL'),
        'timeout_seconds' => (int) env('OPENCLAW_HEALTH_TIMEOUT', 5),
        'max_redirects' => (int) env('OPENCLAW_HEALTH_MAX_REDIRECTS', 5),
    ],

    'supervisor' => [
        'reverb_program' => env('SUPERVISOR_REVERB_PROGRAM', 'reverb'),
        'queue_program' => env('SUPERVISOR_QUEUE_PROGRAM', 'queue-default'),
        'reverb_probe_pattern' => env('SUPERVISOR_REVERB_PROBE_PATTERN', 'artisan reverb:start'),
        'queue_probe_pattern' => env('SUPERVISOR_QUEUE_PROBE_PATTERN', 'artisan queue:(work|listen)'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
        'token' => env('GITHUB_TOKEN', env('GITHUB_PAT')),
        'webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
        'repo_watch_refresh_hours' => (int) env('GITHUB_REPO_WATCH_REFRESH_HOURS', 6),
    ],

    'upyun' => [
        'bucket' => env('UPYUN_BUCKET'),
        'operator' => env('UPYUN_OPERATOR'),
        'password' => env('UPYUN_PASSWORD'),
        'domain' => env('UPYUN_DOMAIN'), // 可选，CDN 加速域名，用于生成公开访问 URL
        'cdn_url' => env('UPYUN_CDN_URL', env('UPYUN_DOMAIN')), // CDN URL for status check
        'api_host' => env('UPYUN_API_HOST'),
    ],

    'minimax' => [
        'token_api_key' => env('MINIMAX_TOKEN_API_KEY'),
        'balance_api_key' => env('MINIMAX_BALANCE_API_KEY'),
        'group_id' => env('MINIMAX_GROUP_ID'),
        'api_base_url' => env('MINIMAX_API_BASE_URL', 'https://api.minimaxi.com'),
        'roleplay_model' => env('MINIMAX_ROLEPLAY_MODEL', 'M2-her'),
    ],

];
