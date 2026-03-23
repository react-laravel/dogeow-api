<?php

namespace Tests\Unit\Controllers;

use App\Events\LogUpdated;
use App\Http\Controllers\Api\LogController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LogControllerTest extends TestCase
{
    private LogController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new LogController;
    }

    public function test_index_returns_json_response(): void
    {
        $response = $this->controller->index();

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
    }

    public function test_index_returns_array_of_logs(): void
    {
        $response = $this->controller->index();
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data);
    }

    public function test_show_returns_404_for_nonexistent_date(): void
    {
        $request = new Request;
        $request->merge(['date' => '2020-01-01']);

        $response = $this->controller->show($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_show_returns_latest_requested_lines(): void
    {
        $file = storage_path('logs/laravel-2026-03-01.log');
        file_put_contents($file, "first\nsecond\nthird\n");

        $request = new Request(['date' => '2026-03-01', 'lines' => 2]);
        $response = $this->controller->show($request);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame("third\n", $data['content']);
        $this->assertSame(4, $data['total_lines']);
    }

    public function test_notify_dispatches_event_for_latest_log_file(): void
    {
        Event::fake([LogUpdated::class]);

        $olderFile = storage_path('logs/laravel-2026-02-28.log');
        $latestFile = storage_path('logs/laravel-2026-03-01.log');
        file_put_contents($olderFile, 'older');
        file_put_contents($latestFile, 'latest');
        touch($olderFile, time() - 60);
        touch($latestFile, time());

        $response = $this->controller->notify();
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame('2026-03-01', $data['date']);
        Event::assertDispatched(LogUpdated::class);
    }

    public function test_notify_returns_404_when_no_log_file_exists(): void
    {
        File::cleanDirectory(storage_path('logs'));

        $response = $this->controller->notify();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertSame(['message' => '未找到日志文件'], json_decode($response->getContent(), true));
    }
}
