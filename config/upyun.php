<?php

return [
    'bucket' => env('UPYUN_BUCKET'),
    'operator' => env('UPYUN_OPERATOR'),
    'password' => env('UPYUN_PASSWORD'),
    'domain' => env('UPYUN_DOMAIN'), // 可选，CDN 加速域名，用于生成公开访问 URL
    'api_host' => env('UPYUN_API_HOST'),
];
