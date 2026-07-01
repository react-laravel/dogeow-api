<?php

namespace App\Console\Commands;

use App\Services\UpyunService;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class UploadLuxunBooksCommand extends Command
{
    protected $signature = 'books:upload-luxun
                            {--source= : 本地书籍目录，默认 ../dogeow/public/books/luxun}
                            {--prefix=books/luxun : 又拍云上的路径前缀}';

    protected $description = '将鲁迅全集分卷 TXT 批量上传到又拍云';

    public function handle(UpyunService $upyun): int
    {
        if (! $upyun->isConfigured()) {
            $this->error('又拍云未配置。请在 .env 中设置 UPYUN_BUCKET、UPYUN_OPERATOR、UPYUN_PASSWORD');

            return self::FAILURE;
        }

        $source = (string) ($this->option('source') ?: base_path('../dogeow/public/books/luxun'));
        $source = realpath($source) ?: $source;
        $prefix = trim((string) $this->option('prefix'), '/');

        if (! is_dir($source)) {
            $this->error("源目录不存在: {$source}");

            return self::FAILURE;
        }

        $files = $this->collectFiles($source);
        if ($files === []) {
            $this->error("目录内没有可上传的 TXT 文件: {$source}");

            return self::FAILURE;
        }

        $this->info('上传 ' . count($files) . " 个分卷文件到又拍云 /{$prefix}/ ...");

        $uploaded = 0;
        foreach ($files as $absolutePath => $relativePath) {
            $remotePath = $prefix . '/' . str_replace('\\', '/', $relativePath);
            $this->line("  {$relativePath} -> /{$remotePath}");

            $result = $upyun->upload($absolutePath, $remotePath, 'text/plain; charset=utf-8');
            if (! $result['success']) {
                $this->error($result['message'] ?? "上传失败: {$relativePath}");

                return self::FAILURE;
            }

            $uploaded++;
        }

        $sampleUrl = $upyun->buildPublicUrl('/' . $prefix . '/index.json');
        $this->info("上传完成，共 {$uploaded} 个文件。");
        $this->line('示例 URL: ' . $sampleUrl);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> absolute path => relative path
     */
    private function collectFiles(string $source): array
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'txt') {
                continue;
            }

            $absolutePath = $file->getRealPath() ?: $file->getPathname();
            $relativePath = ltrim(str_replace($source, '', $absolutePath), DIRECTORY_SEPARATOR);
            $files[$absolutePath] = $relativePath;
        }

        ksort($files);

        return $files;
    }
}
