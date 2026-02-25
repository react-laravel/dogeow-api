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
        // 知识库索引构建接口（Next.js API），需在 .env 配置 KNOWLEDGE_BUILD_INDEX_URL
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
    ],

    'supervisor' => [
        'reverb_program' => env('SUPERVISOR_REVERB_PROGRAM', 'reverb'),
        'queue_program' => env('SUPERVISOR_QUEUE_PROGRAM', 'queue-default'),
    ],

];
