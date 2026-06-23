<?php

namespace App\Services\Web;

use Dogeow\PhpHelpers\Html;
use Dogeow\PhpHelpers\Url;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class WebPageService
{
    public function fetchContent(string $url): array
    {
        $this->validateScheme($url);
        $url = Url::normalizeHttpUrl($url);
        $this->validateUrl($url);

        $response = Http::timeout(5)
            ->withoutRedirecting()
            ->get($url);

        if (! $response->ok()) {
            throw new \RuntimeException('获取网页失败: ' . $response->status());
        }

        $html = $response->body();

        return [
            'title' => Html::extractTitle($html),
            'favicon' => Html::extractFavicon($html, $url),
        ];
    }

    private function validateScheme(string $url): void
    {
        $parsed = parse_url($url);

        if ($parsed === false) {
            throw new InvalidArgumentException('无法解析 URL 主机名');
        }

        if (! isset($parsed['scheme'])) {
            return;
        }

        $scheme = strtolower($parsed['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('仅支持 HTTP/HTTPS 协议');
        }
    }

    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if (! $parsed || empty($parsed['host'])) {
            throw new InvalidArgumentException('无法解析 URL 主机名');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException('仅支持 HTTP/HTTPS 协议');
        }

        $host = $parsed['host'];

        // 跳过已解析为 IP 的主机，直接校验
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            // 解析 DNS，只取第一个 A/AAAA 记录用于校验
            $resolved = gethostbynamel($host);
            if ($resolved === false || empty($resolved)) {
                throw new InvalidArgumentException('无法解析主机名: ' . $host);
            }
            $ip = $resolved[0];
        }

        if ($this->isPrivateIp($ip)) {
            throw new InvalidArgumentException('无法获取目标网页内容');
        }
    }

    private function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return false;
        }

        return true;
    }
}
