<?php

namespace App\Console\Commands;

use App\Services\UpyunService;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class UploadVolumeBooksCommand extends Command
{
    protected $signature = 'books:upload
                            {id : 书籍 id，对应 dogeow/public/books/{id}}
                            {--source= : 本地书籍目录}
                            {--prefix= : 又拍云上的路径前缀}
                            {--audio : 只上传 audio 目录下的 MP3 与 manifest}
                            {--chapter= : 只上传指定章节音频，例如 0-0}';

    protected $description = '将分卷 TXT、索引或 AI 朗读音频上传到又拍云';

    public function handle(UpyunService $upyun): int
    {
        if (! $upyun->isConfigured()) {
            $this->error('又拍云未配置。请在 .env 中设置 UPYUN_BUCKET、UPYUN_OPERATOR、UPYUN_PASSWORD');

            return self::FAILURE;
        }

        $id = (string) $this->argument('id');
        if (! preg_match('/^[a-z0-9]+$/', $id)) {
            $this->error("无效的书籍 id: {$id}");

            return self::FAILURE;
        }

        $audioOnly = (bool) $this->option('audio');
        $chapter = trim((string) $this->option('chapter'));
        if ($chapter !== '' && ! preg_match('/^[0-9]+-[0-9]+$/', $chapter) && ! preg_match('/^[a-zA-Z0-9._-]+$/', $chapter)) {
            $this->error("无效的章节编号: {$chapter}");

            return self::FAILURE;
        }
        if ($chapter !== '' && ! $audioOnly) {
            $this->error('--chapter 只能与 --audio 一起使用');

            return self::FAILURE;
        }
        $source = (string) ($this->option('source') ?: base_path(
            $audioOnly ? "../dogeow/public/books/{$id}/audio" : "../dogeow/public/books/{$id}"
        ));
        $source = realpath($source) ?: $source;
        $prefix = trim((string) ($this->option('prefix') ?: ($audioOnly ? "books/{$id}/audio" : "books/{$id}")), '/');

        if (! is_dir($source)) {
            $this->error("源目录不存在: {$source}");
            $this->comment(
                $audioOnly
                    ? "请先在 dogeow 目录运行: npm run narration:generate -- --book {$id} --chapter 0-0"
                    : "请先在 dogeow 目录运行: npm run preprocess:volume -- {$id}"
            );

            return self::FAILURE;
        }

        $files = $this->collectFiles($source, $audioOnly, $chapter !== '' ? $chapter : null);
        if ($files === []) {
            $this->error(
                $audioOnly
                    ? "目录内没有可上传的 MP3/manifest: {$source}"
                    : "目录内没有可上传的 TXT/JSON/MP3 文件: {$source}"
            );

            return self::FAILURE;
        }

        $this->info('上传 ' . count($files) . ' 个' . ($audioOnly ? '音频' : '') . "文件到又拍云 /{$prefix}/ ...");

        $uploaded = 0;
        foreach ($files as $absolutePath => $relativePath) {
            $remotePath = $prefix . '/' . str_replace('\\', '/', $relativePath);
            $this->line("  {$relativePath} -> /{$remotePath}");

            $result = $upyun->upload($absolutePath, $remotePath, $this->mimeType($relativePath));
            if (! $result['success']) {
                $this->error($result['message'] ?? "上传失败: {$relativePath}");

                return self::FAILURE;
            }

            $uploaded++;
        }

        $sampleRelative = 'index.json';
        if ($audioOnly) {
            $manifests = array_values(array_filter(
                $files,
                static fn (string $path): bool => str_ends_with(strtolower($path), 'manifest.json')
            ));
            $sampleRelative = $manifests[0] ?? (array_values($files)[0] ?? 'manifest.json');
        }
        $sampleUrl = $upyun->buildPublicUrl('/' . $prefix . '/' . str_replace('\\', '/', $sampleRelative));
        $this->info("上传完成，共 {$uploaded} 个文件。");
        $this->line(($audioOnly ? '音频清单 URL: ' : '索引 URL: ') . $sampleUrl);

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> absolute path => relative path
     */
    private function collectFiles(string $source, bool $audioOnly = false, ?string $chapter = null): array
    {
        $source = rtrim($source, DIRECTORY_SEPARATOR);
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = ltrim(str_replace($source, '', $file->getRealPath() ?: $file->getPathname()), DIRECTORY_SEPARATOR);
            if (! $this->shouldCollect($relativePath, $audioOnly, $chapter)) {
                continue;
            }

            $absolutePath = $file->getRealPath() ?: $file->getPathname();
            $files[$absolutePath] = $relativePath;
        }

        ksort($files);

        return $files;
    }

    private function shouldCollect(string $relativePath, bool $audioOnly, ?string $chapter = null): bool
    {
        $basename = strtolower(basename($relativePath));
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (str_contains($basename, '.generating.')) {
            return false;
        }

        if ($chapter !== null && $chapter !== '') {
            $parts = explode('/', str_replace('\\', '/', $relativePath));
            if (! isset($parts[1]) || $parts[1] !== $chapter) {
                return false;
            }
        }

        if ($audioOnly) {
            return $extension === 'mp3' || $basename === 'manifest.json';
        }

        return in_array($extension, ['txt', 'json', 'mp3'], true);
    }

    private function mimeType(string $relativePath): string
    {
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'json' => 'application/json',
            'mp3' => 'audio/mpeg',
            default => 'text/plain; charset=utf-8',
        };
    }
}
