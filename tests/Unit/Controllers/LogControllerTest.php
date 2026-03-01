<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\LogController;
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
        $request = new \Illuminate\Http\Request;
        $request->merge(['date' => '2020-01-01']);

        $response = $this->controller->show($request);

        $this->assertEquals(404, $response->getStatusCode());
    }
}
