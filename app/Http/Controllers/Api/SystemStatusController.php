<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SystemStatus\SystemStatusService;
use Illuminate\Http\JsonResponse;

/**
 * 公开接口：网站状态(Hermes/OpenClaw Gateway、Reverb、队列)，供 /about/site 使用。
 */
class SystemStatusController extends Controller
{
    public function __construct(
        private readonly SystemStatusService $systemStatusService
    ) {}

    /**
     * GET /api/system/status
     */
    public function index(): JsonResponse
    {
        $data = $this->systemStatusService->getAggregatedStatus();

        return $this->success($data, 'System status retrieved successfully');
    }
}
