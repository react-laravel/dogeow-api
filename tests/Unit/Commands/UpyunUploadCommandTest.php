<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\UpyunUploadCommand;
use App\Services\UpyunService;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class UpyunUploadCommandTest extends TestCase
{
    private string $tempFile;

    private string $nonExistentPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/upyun_upload_test_' . uniqid() . '.txt';
        file_put_contents($this->tempFile, 'test content');
        $this->nonExistentPath = sys_get_temp_dir() . '/nonexistent_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    private function runCommand(UpyunUploadCommand $command, array $input): int
    {
        $symfonyInput = new ArrayInput($input);
        $symfonyInput->bind($command->getDefinition());
        $command->setInput($symfonyInput);
        $command->setOutput(new \Illuminate\Console\OutputStyle($symfonyInput, new NullOutput));

        return $command->handle($this->app->make(UpyunService::class));
    }

    public function test_returns_failure_when_file_does_not_exist(): void
    {
        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->nonExistentPath]);

        $this->assertSame(UpyunUploadCommand::FAILURE, $exitCode);
    }

    public function test_returns_failure_when_upyun_not_configured(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(false);
        $upyun->shouldNotReceive('upload');

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->tempFile]);

        $this->assertSame(UpyunUploadCommand::FAILURE, $exitCode);
    }

    public function test_returns_success_when_upload_succeeds(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')
            ->once()
            ->with($this->tempFile, 'ollama/' . basename($this->tempFile))
            ->andReturn(['success' => true, 'url' => 'https://example.com/file.txt']);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->tempFile]);

        $this->assertSame(UpyunUploadCommand::SUCCESS, $exitCode);
    }

    public function test_uses_custom_remote_path_when_option_provided(): void
    {
        $customRemote = 'images/custom/path.png';
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')
            ->once()
            ->with($this->tempFile, $customRemote)
            ->andReturn(['success' => true, 'path' => $customRemote]);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, [
            'path' => $this->tempFile,
            '--remote' => $customRemote,
        ]);

        $this->assertSame(UpyunUploadCommand::SUCCESS, $exitCode);
    }

    public function test_strips_leading_slash_from_remote_path(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')
            ->once()
            ->with($this->tempFile, 'images/ollama/file.png')
            ->andReturn(['success' => true, 'path' => 'images/ollama/file.png']);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, [
            'path' => $this->tempFile,
            '--remote' => '/images/ollama/file.png',
        ]);

        $this->assertSame(UpyunUploadCommand::SUCCESS, $exitCode);
    }

    public function test_returns_failure_when_upload_fails(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')
            ->once()
            ->andReturn(['success' => false, 'message' => 'Upload failed']);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->tempFile]);

        $this->assertSame(UpyunUploadCommand::FAILURE, $exitCode);
    }

    public function test_returns_failure_when_upload_returns_success_false_without_message(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')->once()->andReturn(['success' => false]);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->tempFile]);

        $this->assertSame(UpyunUploadCommand::FAILURE, $exitCode);
    }

    #[DataProvider('successResponseProvider')]
    public function test_success_with_various_response_shapes(array $response): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')->once()->andReturn($response);

        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UpyunUploadCommand::class);
        $exitCode = $this->runCommand($command, ['path' => $this->tempFile]);

        $this->assertSame(UpyunUploadCommand::SUCCESS, $exitCode);
    }

    public static function successResponseProvider(): array
    {
        return [
            'with_url' => [['success' => true, 'url' => 'https://cdn.example.com/file.png']],
            'with_path_only' => [['success' => true, 'path' => 'ollama/file.png']],
        ];
    }
}
