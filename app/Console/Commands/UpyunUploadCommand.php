<?php

namespace App\Console\Commands;

use App\Services\UpyunService;
use Illuminate\Console\Command;

class UpyunUploadCommand extends Command
{
    protected $signature = 'upyun:upload
                            {path : 本地文件路径(如 Ollama 生成图片的路径)}
                            {--remote= : 又拍云上的路径，如 images/ollama/xxx.png；不填则用 images/ollama/ 加文件名}';

    protected $description = '将本地文件上传到又拍云(可用于 Ollama 生成图片后上传)';

    /**
     * 默认 Ollama 上传目录常量
     */
    protected const DEFAULT_OLLAMA_DIR = 'ollama/';

    public function handle(UpyunService $upyun): int
    {
        $localPath = $this->argument('path');

        if (! is_file($localPath)) {
            $this->error("文件不存在: {$localPath}");

            return self::FAILURE;
        }

        $remotePath = $this->option('remote');
        if ($remotePath === null || $remotePath === '') {
            $remotePath = self::DEFAULT_OLLAMA_DIR . basename($localPath);
        }
        $remotePath = ltrim($remotePath, '/');

        if (! $upyun->isConfigured()) {
            $this->error('又拍云未配置。请在 .env 中设置 UPYUN_BUCKET、UPYUN_OPERATOR、UPYUN_PASSWORD');

            return self::FAILURE;
        }

        $this->info("上传 {$localPath} -> {$remotePath} ...");

        $result = $upyun->upload($localPath, $remotePath);

        if (! $result['success']) {
            $this->error($result['message'] ?? '上传失败');

            return self::FAILURE;
        }

        $this->info('上传成功。');
        if (! empty($result['url'])) {
            $this->line('URL: ' . $result['url']);
        } else {
            $this->line('路径: ' . $result['path']);
            $this->comment('若需公开 URL，请在 .env 中设置 UPYUN_DOMAIN(CDN 域名)');
        }

        return self::SUCCESS;
    }
}
