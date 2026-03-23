<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Web\ClientInfoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientInfoController extends Controller
{
    public function __construct(
        private readonly ClientInfoService $clientInfoService
    ) {}

    /**
     * 获取客户端基本信息(IP 和 User-Agent)，立即返回
     */
    public function getBasicInfo(Request $request): JsonResponse
    {
        $data = $this->clientInfoService->getBasicInfo($request);

        return response()->json($data);
    }

    /**
     * 获取地理位置信息，可能需要较长时间
     */
    public function getLocationInfo(Request $request): JsonResponse
    {
        $ip = $request->ip();
        $data = $this->clientInfoService->getLocationInfo(
            $ip,
            skipReservedIpLookup: ! app()->runningUnitTests()
        );

        $statusCode = isset($data['error']) ? 500 : 200;

        return response()->json($data, $statusCode);
    }

    /**
     * 获取完整客户端信息(保持向后兼容)
     */
    public function getClientInfo(Request $request): JsonResponse
    {
        $data = $this->clientInfoService->getClientInfo(
            $request,
            skipReservedIpLookup: ! app()->runningUnitTests()
        );

        return response()->json($data);
    }
}
