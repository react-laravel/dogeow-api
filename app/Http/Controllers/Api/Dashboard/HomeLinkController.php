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
            [
                'id' => 'apixdoc',
                'label' => 'API 文档',
                'caption' => 'apixdoc.dogeow.com',
                'href' => 'https://apixdoc.dogeow.com/',
                'icon' => 'network',
                'gradientClassName' => 'bg-gradient-to-br from-blue-500 via-indigo-500 to-violet-600',
            ],
            [
                'id' => 'code-line',
                'label' => '代码行分析',
                'caption' => 'code-line.dogeow.com',
                'href' => 'https://code-line.dogeow.com/',
                'icon' => 'activity',
                'gradientClassName' => 'bg-gradient-to-br from-slate-500 via-zinc-600 to-neutral-800',
            ],
            [
                'id' => 'game',
                'label' => '2.5D 游戏',
                'caption' => 'game.dogeow.com',
                'href' => 'https://game.dogeow.com/',
                'icon' => 'activity',
                'gradientClassName' => 'bg-gradient-to-br from-lime-400 via-emerald-500 to-green-700',
            ],
            [
                'id' => 'ip-checker',
                'label' => 'IP 检测',
                'caption' => 'ip-checker.dogeow.com',
                'href' => 'https://ip-checker.dogeow.com/',
                'icon' => 'network',
                'gradientClassName' => 'bg-gradient-to-br from-sky-400 via-blue-500 to-cyan-600',
            ],
            [
                'id' => 'mysql-compare',
                'label' => 'MySQL Compare',
                'caption' => 'mysql-compare.dogeow.com',
                'href' => 'https://mysql-compare.dogeow.com/',
                'icon' => 'network',
                'gradientClassName' => 'bg-gradient-to-br from-amber-400 via-orange-500 to-red-600',
            ],
            [
                'id' => 'news',
                'label' => '聚合新闻',
                'caption' => 'news.dogeow.com',
                'href' => 'https://news.dogeow.com/',
                'icon' => 'activity',
                'gradientClassName' => 'bg-gradient-to-br from-rose-400 via-pink-500 to-purple-600',
            ],
            [
                'id' => 'simple-diff',
                'label' => 'Simple Diff',
                'caption' => 'simple-diff.dogeow.com',
                'href' => 'https://simple-diff.dogeow.com/',
                'icon' => 'pen-tool',
                'gradientClassName' => 'bg-gradient-to-br from-teal-400 via-cyan-500 to-blue-600',
            ],
            [
                'id' => 'snake',
                'label' => '贪吃蛇',
                'caption' => 'snake.dogeow.com',
                'href' => 'https://snake.dogeow.com/',
                'icon' => 'activity',
                'gradientClassName' => 'bg-gradient-to-br from-green-400 via-lime-500 to-yellow-500',
            ],
            [
                'id' => 'threejs',
                'label' => 'Three.js',
                'caption' => 'threejs.dogeow.com',
                'href' => 'https://threejs.dogeow.com/',
                'icon' => 'pen-tool',
                'gradientClassName' => 'bg-gradient-to-br from-purple-400 via-indigo-500 to-blue-700',
            ],
        ]);
    }
}
