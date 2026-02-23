
<?php

use Pdp\Domain;
use Pdp\Rules;

$appUrl = env('APP_URL');
$host = parse_url($appUrl, PHP_URL_HOST);

try {
    $pslPath = base_path('database/data/public_suffix_list.dat');
    if (file_exists($pslPath)) {
        $rules = Rules::fromPath($pslPath);
        $domain = Domain::fromIDNA2008($host);
        $result = $rules->resolve($domain);
        $mainDomain = $result->registrableDomain() ?: $host;
    }
} catch (Throwable $e) {
    $mainDomain = $host;
}

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL'),
    ],

    'allowed_origins_patterns' => [
        "#^https://(.+\\.)?$mainDomain$#",
        '#^http://(localhost|127\.0\.0\.1):\d+$#',
        '#^http://100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.\d+\.\d+:3000$#', // Tailscale 地址段
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
