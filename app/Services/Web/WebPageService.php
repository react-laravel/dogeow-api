<?php

namespace App\Services\Web;

use Dogeow\PhpHelpers\Html;
use Dogeow\PhpHelpers\Url;
use Illuminate\Support\Facades\Http;

class WebPageService
{
    public function fetchContent(string $url): array
    {
        $url = Url::normalizeHttpUrl($url);

        $response = Http::timeout(5)->get($url);

        if (! $response->ok()) {
            throw new \RuntimeException('获取网页失败: ' . $response->status());
        }

        $html = $response->body();

        return [
            'title' => Html::extractTitle($html),
            'favicon' => Html::extractFavicon($html, $url),
        ];
    }
}
