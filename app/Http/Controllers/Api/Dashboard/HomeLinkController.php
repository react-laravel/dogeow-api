<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HomeLinkController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            [
                'id' => 'vnstat',
                'label' => 'vnStat',
                'caption' => 'vnstat.dogeow.com',
                'href' => 'https://vnstat.dogeow.com',
                'icon' => 'activity',
                'gradientClassName' => 'bg-gradient-to-br from-cyan-400 via-sky-500 to-indigo-500',
            ],
            [
                'id' => 'canvas',
                'label' => 'Canvas',
                'caption' => 'canvas.dogeow.com',
                'href' => 'https://canvas.dogeow.com/',
                'icon' => 'pen-tool',
                'gradientClassName' => 'bg-gradient-to-br from-orange-400 via-amber-500 to-rose-500',
            ],
            [
                'id' => 'upyun-web',
                'label' => 'UpYun Web',
                'caption' => 'upyun-web.dogeow.com',
                'href' => 'https://upyun-web.dogeow.com/',
                'icon' => 'cloud',
                'gradientClassName' => 'bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-600',
            ],
            [
                'id' => 'rmbg',
                'label' => 'RMBG',
                'caption' => 'rmbg.dogeow.com',
                'href' => 'https://rmbg.dogeow.com/',
                'icon' => 'scissors',
                'gradientClassName' => 'bg-gradient-to-br from-fuchsia-400 via-pink-500 to-rose-600',
            ],
            [
                'id' => 'mind',
                'label' => '知识图谱',
                'caption' => 'mind.dogeow.com',
                'href' => 'https://mind.dogeow.com/',
                'icon' => 'network',
                'gradientClassName' => 'bg-gradient-to-br from-violet-500 via-fuchsia-500 to-indigo-600',
            ],
        ]);
    }
}
