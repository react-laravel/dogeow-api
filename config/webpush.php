<?php

return [
    'subject' => env('VAPID_SUBJECT'),
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'pem_file' => env('VAPID_PEM_FILE'),

    'model' => \NotificationChannels\WebPush\PushSubscription::class,
    'table_name' => env('WEBPUSH_DB_TABLE', 'push_subscriptions'),
    'database_connection' => env('WEBPUSH_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /*
    | Guzzle 客户端选项，用于请求 FCM 等推送服务。
    | 若服务器无法直连 Google（如国内），可设置 HTTP 代理，例如：
    | 'client_options' => ['proxy' => env('WEBPUSH_HTTP_PROXY')],
    | 并在 .env 中设置 WEBPUSH_HTTP_PROXY=http://127.0.0.1:7890
    */
    'client_options' => array_filter([
        'proxy' => env('WEBPUSH_HTTP_PROXY'),
    ]),
    'automatic_padding' => env('WEBPUSH_AUTOMATIC_PADDING', true),
];
