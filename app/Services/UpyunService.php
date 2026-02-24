<?php

namespace App\Services;

use GuzzleHttp\Psr7\LazyOpenStream;
use Illuminate\Support\Facades\Http;

/**
 * 又拍云 REST API 上传服务（用于本地文件上传到 UpYun，如 Ollama 生成的图片）
 *
 * @see https://docs.upyun.com/api/rest_api/
 * @see https://docs.upyun.com/api/authorization/
 */
class UpyunService
{
    private string $bucket;

    private string $operator;

    private string $passwordMd5;

    private string $apiHost;

    private ?string $domain;

    public function __construct()
    {
        $this->bucket = (string) config('upyun.bucket');
        $this->operator = (string) config('upyun.operator');
        $this->passwordMd5 = md5((string) config('upyun.password'));
        $this->apiHost = (string) config('upyun.api_host');
        $this->domain = config('upyun.domain') ? (string) config('upyun.domain') : null;
    }

    /**
     * 检查是否已配置又拍云
     */
    public function isConfigured(): bool
    {
        return $this->bucket !== '' && $this->operator !== '' && config('upyun.password') !== '';
    }

    /**
     * 上传本地文件到又拍云
     *
     * @param  string  $localPath  本地文件路径（如 Ollama 生成图片的路径，或 Laravel 上传的临时路径）
     * @param  string  $remotePath  又拍云上的路径，如 /images/ollama/xxx.png（以 / 开头，不要带 bucket）
     * @param  string|null  $contentType  可选。未传时按路径扩展名猜测（临时文件无扩展名时会得到 application/octet-stream，易导致访问乱码）
     * @return array{success: bool, url?: string, message?: string}
     */
    public function upload(string $localPath, string $remotePath, ?string $contentType = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => '又拍云未配置，请设置 UPYUN_BUCKET、UPYUN_OPERATOR、UPYUN_PASSWORD'];
        }

        $remotePath = ltrim($remotePath, '/');
        if ($remotePath === '') {
            return ['success' => false, 'message' => 'remotePath 不能为空'];
        }

        if (! is_file($localPath) || ! is_readable($localPath)) {
            return ['success' => false, 'message' => "本地文件不存在或不可读: {$localPath}"];
        }

        $contentLength = filesize($localPath);
        $contentMd5 = md5_file($localPath);
        $contentType = $contentType ?? $this->guessMimeType($localPath);

        $uri = '/' . $this->bucket . '/' . $remotePath;
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $signature = $this->makeSignature('PUT', $uri, $date, $contentMd5);

        $url = 'https://' . $this->apiHost . $uri;

        $stream = new LazyOpenStream($localPath, 'rb');

        // 必须显式传 Content-Type；Laravel withBody() 默认第二参数为 application/json，否则又拍云会存成 JSON 导致访问乱码
        $response = Http::withHeaders([
            'Authorization' => $signature,
            'Date' => $date,
            'Content-Length' => (string) $contentLength,
            'Content-MD5' => $contentMd5,
            'Content-Type' => $contentType,
        ])->withBody($stream, $contentType)->put($url);

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => '又拍云上传失败: ' . $response->status() . ' ' . $response->body(),
            ];
        }

        $publicUrl = $this->domain
            ? rtrim($this->domain, '/') . '/' . $remotePath
            : null;

        return [
            'success' => true,
            'url' => $publicUrl,
            'path' => '/' . $remotePath,
        ];
    }

    /**
     * 生成 REST API 签名
     * Signature = Base64(HMAC-SHA1(Password_MD5, Method&URI&Date&Content-MD5))
     */
    private function makeSignature(string $method, string $uri, string $date, string $contentMd5): string
    {
        $parts = [$method, $uri, $date];
        if ($contentMd5 !== '') {
            $parts[] = $contentMd5;
        }
        $stringToSign = implode('&', $parts);
        $sign = base64_encode(hash_hmac('sha1', $stringToSign, $this->passwordMd5, true));

        return 'UPYUN ' . $this->operator . ':' . $sign;
    }

    private function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $map = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $map[$ext] ?? 'application/octet-stream';
    }
}
