<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Api\ClientInfoController;
use App\Services\Web\ClientInfoService;
use Illuminate\Http\Request;
use Tests\TestCase;

class ClientInfoControllerTest extends TestCase
{
    public function test_get_basic_info_returns_json(): void
    {
        $service = new ClientInfoService;
        $controller = new ClientInfoController($service);

        $request = new Request;
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $controller->getBasicInfo($request);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('ip', $data);
    }

    public function test_get_client_info_returns_json(): void
    {
        $service = new ClientInfoService;
        $controller = new ClientInfoController($service);

        $request = new Request;
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $response = $controller->getClientInfo($request);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $response);
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('ip', $data);
        $this->assertArrayHasKey('user_agent', $data);
    }
}
