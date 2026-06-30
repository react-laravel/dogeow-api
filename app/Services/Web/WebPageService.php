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
        $pinnedIp = $this->validateUrl($url);

        $response = Http::timeout(5)
            ->withoutRedirecting()
            ->withOptions($this->buildConnectionOptions($url, $pinnedIp))
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

    /**
     * 校验目标 URL，并返回经过校验、可安全连接的 IP。
     *
     * 关键点：解析主机名得到的 *所有* IP 都必须是公网地址，
     * 任意一个落入私网/保留地址都直接拒绝。返回的 IP 会在
     * {@see buildConnectionOptions()} 中被钉死（pin）到底层连接上，
     * 从而避免 DNS rebinding（校验与真正连接之间 DNS 结果被替换）。
     */
    private function validateUrl(string $url): string
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

        // 已经是 IP 字面量则直接校验
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($host)) {
                throw new InvalidArgumentException('无法获取目标网页内容');
            }

            return $host;
        }

        // 解析 DNS，校验所有 A 记录（任意一个为私网都拒绝）
        $resolved = gethostbynamel($host);
        if ($resolved === false || empty($resolved)) {
            throw new InvalidArgumentException('无法解析主机名: ' . $host);
        }

        foreach ($resolved as $ip) {
            if ($this->isPrivateIp($ip)) {
                throw new InvalidArgumentException('无法获取目标网页内容');
            }
        }

        // 返回首个已校验的公网 IP，用于钉死连接目标
        return $resolved[0];
    }

    /**
     * 构造 HTTP 客户端选项，将连接目标钉死到已校验的 IP。
     *
     * 通过 cURL 的 CURLOPT_RESOLVE 把 host:port 强制映射到校验过的 IP，
     * 这样后续真正建立连接时不会再次进行 DNS 解析，杜绝 rebinding。
     * 同时保留原始 Host 头，目标站点仍按域名处理请求。
     *
     * @return array<string, mixed>
     */
    private function buildConnectionOptions(string $url, string $ip): array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $scheme = strtolower($parsed['scheme'] ?? 'http');
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);

        // 主机本身就是 IP 字面量时无需钉死
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return [];
        }

        return [
            'curl' => [
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
            ],
        ];
    }

    private function isPrivateIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
            return false;
        }

        return true;
    }
}
