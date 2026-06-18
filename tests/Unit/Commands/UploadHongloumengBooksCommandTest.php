<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\UploadHongloumengBooksCommand;
use App\Services\UpyunService;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class UploadHongloumengBooksCommandTest extends TestCase
{
    private string $sourceDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDir = sys_get_temp_dir() . '/hongloumeng_upload_' . uniqid();
        mkdir($this->sourceDir, 0777, true);
        mkdir($this->sourceDir . '/chapters', 0777, true);
        file_put_contents($this->sourceDir . '/index.json', '{"title":"红楼梦对照"}');
        file_put_contents($this->sourceDir . '/chapters/001.json', '{"id":1}');
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->sourceDir);
        parent::tearDown();
    }

    private function runCommand(UploadHongloumengBooksCommand $command, array $input): int
    {
        $symfonyInput = new ArrayInput($input);
        $symfonyInput->bind($command->getDefinition());
        $command->setInput($symfonyInput);
        $command->setOutput(new OutputStyle($symfonyInput, new NullOutput));

        return $command->handle($this->app->make(UpyunService::class));
    }

    public function test_returns_failure_when_upyun_not_configured(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(false);
        $upyun->shouldNotReceive('upload');
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadHongloumengBooksCommand::class);
        $exitCode = $this->runCommand($command, ['--source' => $this->sourceDir]);

        $this->assertSame(UploadHongloumengBooksCommand::FAILURE, $exitCode);
    }

    public function test_returns_failure_when_source_directory_missing(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadHongloumengBooksCommand::class);
        $exitCode = $this->runCommand($command, ['--source' => $this->sourceDir . '/missing']);

        $this->assertSame(UploadHongloumengBooksCommand::FAILURE, $exitCode);
    }

    public function test_uploads_all_json_files_under_prefix(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $sourceDir = realpath($this->sourceDir) ?: $this->sourceDir;
        $upyun->shouldReceive('upload')
            ->once()
            ->with($sourceDir . '/chapters/001.json', 'books/hongloumeng/chapters/001.json', 'application/json')
            ->andReturn(['success' => true, 'path' => 'books/hongloumeng/chapters/001.json']);
        $upyun->shouldReceive('upload')
            ->once()
            ->with($sourceDir . '/index.json', 'books/hongloumeng/index.json', 'application/json')
            ->andReturn(['success' => true, 'path' => 'books/hongloumeng/index.json']);
        $upyun->shouldReceive('buildPublicUrl')
            ->once()
            ->with('/books/hongloumeng/index.json')
            ->andReturn('https://upyun.dogeow.com/books/hongloumeng/index.json');
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadHongloumengBooksCommand::class);
        $exitCode = $this->runCommand($command, ['--source' => $this->sourceDir]);

        $this->assertSame(UploadHongloumengBooksCommand::SUCCESS, $exitCode);
    }

    public function test_returns_failure_when_any_upload_fails(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')->once()->andReturn(['success' => false, 'message' => '上传失败']);
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadHongloumengBooksCommand::class);
        $exitCode = $this->runCommand($command, ['--source' => $this->sourceDir]);

        $this->assertSame(UploadHongloumengBooksCommand::FAILURE, $exitCode);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
