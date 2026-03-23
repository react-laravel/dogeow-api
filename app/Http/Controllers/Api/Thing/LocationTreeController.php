<?php

namespace App\Http\Controllers\Api\Thing;

use App\Http\Controllers\Controller;
use App\Services\Location\LocationTreeService;
use Illuminate\Support\Facades\Auth;

class LocationTreeController extends Controller
{
    public function __construct(
        private readonly LocationTreeService $locationTreeService
    ) {}

    /**
     * 获取位置树形结构
     */
    public function tree()
    {
        $result = $this->locationTreeService->buildLocationTree(Auth::id());

        return $this->success($result, 'Location tree retrieved successfully');
    }
}
