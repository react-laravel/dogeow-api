<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\UpdatePotionSettingsRequest;
use App\Http\Requests\Game\UsePotionRequest;
use App\Jobs\Game\AutoCombatRoundJob;
use App\Services\Game\GameCombatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CombatController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    public function __construct(
        private readonly GameCombatService $combatService,
    ) {}

    /**
     * 获取战斗状态
     */
    public function status(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatStatus($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗状态失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新药水自动使用设置
     */
    public function updatePotionSettings(UpdatePotionSettingsRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $character = $this->combatService->updatePotionSettings($character, $request->validated());

            return $this->success(['character' => $character->toArray()], '药水设置已更新');
        } catch (Throwable $e) {
            return $this->error('更新药水自动使用设置失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 开始自动战斗：服务器每 3 秒执行一回合，通过 Reverb WebSocket 推送战斗结果
     */
    public function start(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            // 检测角色是否死亡，复活并传送到地图一
            if ($character->current_hp <= 0) {
                // 清除战斗状态
                $character->clearCombatState();

                $character->current_hp = $character->getMaxHp();
                $character->current_mana = $character->getMaxMana();
                $character->current_map_id = 1;
                $character->is_fighting = true;
                $character->save();

                // 复活成功，但不自动启动战斗，让用户手动开始
                return $this->success(['message' => '角色已满血复活并传送到新手村，请手动开始战斗']);
            }

            // 检查是否已经有自动战斗在运行
            $key = AutoCombatRoundJob::redisKey($character->id);
            if (Redis::get($key) !== null) {
                return $this->error('自动战斗已在运行中，请先停止当前战斗');
            }

            $skillIds = $request->input('skill_ids') ?? [];
            $skillIds = is_array($skillIds) ? array_map('intval', array_values($skillIds)) : [];

            Redis::set($key, json_encode(['skill_ids' => $skillIds]));

            AutoCombatRoundJob::dispatch($character->id, $skillIds);

            return $this->success(['message' => '自动战斗已开始，结果将通过 WebSocket 推送']);
        } catch (Throwable $e) {
            return $this->error($e->getMessage() ?: '开始战斗失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 停止自动战斗
     */
    public function stop(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            $key = AutoCombatRoundJob::redisKey($character->id);
            Redis::del($key);

            if ($character->is_fighting) {
                $character->update(['is_fighting' => false]);
            }

            return $this->success(['message' => '自动战斗已停止']);
        } catch (Throwable $e) {
            return $this->error($e->getMessage() ?: '停止战斗失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取战斗日志
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatLogs($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗日志失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取单条战斗日志详情
     */
    public function logDetail(Request $request, int $log): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatLogDetail($character, $log);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗日志详情失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取战斗统计
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->combatService->getCombatStats($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取战斗统计失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 更新战斗中使用的技能配置
     */
    public function updateSkills(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);

            $skillIds = $request->input('skill_ids') ?? [];
            $skillIds = is_array($skillIds) ? array_map('intval', array_values($skillIds)) : [];

            $key = AutoCombatRoundJob::redisKey($character->id);
            $payload = Redis::get($key);

            if ($payload === null) {
                return $this->error('当前没有进行中的自动战斗');
            }

            $data = json_decode($payload, true);
            $data['skill_ids'] = $skillIds;
            $data['cancelled_skill_ids'] = [];

            Redis::set($key, json_encode($data));

            return $this->success(['skill_ids' => $data['skill_ids']], '技能配置已更新');
        } catch (Throwable $e) {
            return $this->error('更新技能配置失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 使用药品
     */
    public function usePotion(UsePotionRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $inventoryService = new \App\Services\Game\GameInventoryService;
            $result = $inventoryService->usePotion($character, $request->input('item_id'));

            return $this->success([
                'current_hp' => $character->getCurrentHp(),
                'current_mana' => $character->getCurrentMana(),
                'max_hp' => $character->getMaxHp(),
                'max_mana' => $character->getMaxMana(),
                'message' => $result['message'] ?? '药品使用成功',
            ], '药品使用成功');
        } catch (Throwable $e) {
            return $this->error('使用药品失败', ['error' => $e->getMessage()]);
        }
    }
}
