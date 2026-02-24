<?php

namespace App\Jobs\Game;

use App\Events\Game\GameCombatUpdate;
use App\Models\Game\GameCharacter;
use App\Services\Game\GameCombatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use RuntimeException;

class AutoCombatRoundJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    private const REDIS_KEY_PREFIX = 'rpg:combat:auto:';

    private const LOCK_TIMEOUT = 35;

    public function __construct(
        public int $characterId,
        public array $skillIds = []
    ) {
        $this->onQueue('default');
    }

    public function handle(GameCombatService $combatService): void
    {
        $key = self::REDIS_KEY_PREFIX . $this->characterId;
        $payload = Redis::get($key);

        if ($payload === null) {
            return;
        }

        // 使用 Laravel Cache 锁来确保原子性
        $lockKey = 'rpg:combat:lock:' . $this->characterId;
        $lock = Cache::lock($lockKey, self::LOCK_TIMEOUT);

        if (! $lock->get()) {
            return;
        }

        try {
            // 再次检查 Redis key 是否存在
            if (Redis::get($key) === null) {
                return;
            }

            $data = json_decode($payload, true);

            $skillIds = $data['skill_ids'] ?? [];
            if (! is_array($skillIds)) {
                $skillIds = [];
            }

            // 检查是否有被取消的技能，如果有则从列表中移除
            $cancelledSkillIds = $data['cancelled_skill_ids'] ?? [];
            if (! empty($cancelledSkillIds)) {
                $skillIds = array_values(array_diff($skillIds, $cancelledSkillIds));
                $data['skill_ids'] = $skillIds;
                Redis::set($key, json_encode($data));
            }

            $character = GameCharacter::query()->find($this->characterId);
            if (! $character) {
                Redis::del($key);
                $lock->release();

                return;
            }

            // 先检查是否需要刷新怪物，如果需要则广播怪物出现
            if ($combatService->shouldRefreshMonsters($character)) {
                $map = $character->currentMap;
                if ($map) {
                    $combatService->broadcastMonstersAppear($character, $map);
                }
            }

            // 执行回合前再次从 Redis 读取技能列表，确保用户中途取消/启用技能能立即生效
            $freshPayload = Redis::get($key);
            if ($freshPayload !== null) {
                $freshData = json_decode($freshPayload, true);
                if (is_array($freshData)) {
                    $freshSkillIds = $freshData['skill_ids'] ?? [];
                    if (is_array($freshSkillIds)) {
                        $skillIds = array_values(array_map('intval', $freshSkillIds));
                    }
                }
            }

            $result = $combatService->executeRound($character, $skillIds);

            // 释放锁
            $lock->release();

            if (! empty($result['defeat']) || ! empty($result['auto_stopped'])) {
                Redis::del($key);

                return;
            }

            // 检查 Redis key 是否仍然存在
            if (Redis::get($key) !== null) {
                // 等待 3 秒后再调度下一个 job
                // 这是一个简单但可靠的方法
                sleep(3);
                self::dispatch($this->characterId, []);
            }
        } catch (RuntimeException|InvalidArgumentException $e) {
            $lock->release();
            $this->broadcastAutoStoppedAndCleanup($character ?? null, $e, $key);
        }
    }

    /**
     * 获取下一个 job 应该执行的时间（秒）
     */
    public function withExponentialBackoff(int $attempt): int
    {
        return pow(2, $attempt);
    }

    private function broadcastAutoStoppedAndCleanup(?GameCharacter $character, \Throwable $e, string $redisKey): void
    {
        if ($character) {
            Redis::del($redisKey);

            // 重置战斗状态
            $character->is_fighting = false;
            $character->save();

            $payload = null;
            if ($e->getPrevious() instanceof \Throwable) {
                $decoded = json_decode($e->getPrevious()->getMessage(), true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $character->refresh();
            $charArray = $character->toArray();
            $charArray['current_hp'] = $payload['current_hp'] ?? $character->getCurrentHp();
            $charArray['current_mana'] = $character->getCurrentMana();

            $result = [
                'victory' => false,
                'defeat' => false,
                'auto_stopped' => true,
                'monster' => ['name' => '', 'type' => 'normal', 'level' => 1],
                'damage_dealt' => 0,
                'damage_taken' => 0,
                'rounds' => 0,
                'experience_gained' => 0,
                'copper_gained' => 0,
                'loot' => [],
                'character' => $charArray,
                'current_hp' => $charArray['current_hp'],
                'current_mana' => $charArray['current_mana'],
                'combat_log_id' => 0,
            ];

            broadcast(new GameCombatUpdate($character->id, $result));
        }
    }

    public static function redisKey(int $characterId): string
    {
        return self::REDIS_KEY_PREFIX . $characterId;
    }
}
