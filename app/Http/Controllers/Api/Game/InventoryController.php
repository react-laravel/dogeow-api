<?php

namespace App\Http\Controllers\Api\Game;

use App\Events\Game\GameInventoryUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\EquipItemRequest;
use App\Http\Requests\Game\MoveItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Http\Requests\Game\UnequipItemRequest;
use App\Http\Requests\Game\UsePotionRequest;
use App\Models\Game\GameCharacter;
use App\Services\Game\GameInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class InventoryController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    public function __construct(
        private readonly GameInventoryService $inventoryService,
    ) {}

    /**
     * 广播背包更新
     */
    private function broadcastInventoryUpdate(GameCharacter $character): void
    {
        broadcast(new GameInventoryUpdate($character->id, $this->inventoryService->getInventoryForBroadcast($character)));
    }

    /**
     * 获取背包物品
     */
    public function index(Request $request): JsonResponse
    {
        $character = $this->getCharacter($request);
        $result = $this->inventoryService->getInventory($character);

        return $this->success($result);
    }

    /**
     * 装备物品
     */
    public function equip(EquipItemRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->inventoryService->equipItem($character, $request->input('item_id'));
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, '装备成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 卸下装备
     */
    public function unequip(UnequipItemRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->inventoryService->unequipItem($character, $request->input('slot'));
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, '卸下装备成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 出售物品
     */
    public function sell(SellItemRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->inventoryService->sellItem(
                $character,
                $request->input('item_id'),
                $request->input('quantity', 1)
            );
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, '出售成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 移动物品
     */
    public function move(MoveItemRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->inventoryService->moveItem(
                $character,
                $request->input('item_id'),
                $request->input('to_storage'),
                $request->input('slot_index')
            );
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, '移动成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 使用药品
     */
    public function usePotion(UsePotionRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->inventoryService->usePotion($character, $request->input('item_id'));
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, '使用药品成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 整理背包
     */
    public function sort(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'sort_by' => 'nullable|string|in:quality,price,default',
            ]);

            $character = $this->getCharacter($request);
            $sortBy = $request->input('sort_by', 'default');
            $result = $this->inventoryService->sortInventory($character, $sortBy);
            $this->broadcastInventoryUpdate($character);

            $message = match ($sortBy) {
                'quality' => '按品质整理完成',
                'price' => '按价格整理完成',
                default => '整理完成',
            };

            return $this->success($result, $message);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 批量出售指定品质的物品
     */
    public function sellByQuality(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'quality' => 'required|string|in:common,magic,rare,legendary,mythic',
            ]);

            $character = $this->getCharacter($request);
            $quality = $request->input('quality');
            $result = $this->inventoryService->sellItemsByQuality($character, $quality);
            $this->broadcastInventoryUpdate($character);

            return $this->success($result, "已出售 {$result['count']} 件{$this->getQualityName($quality)}物品，获得 {$result['total_price']} 铜");
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取品质中文名
     */
    private function getQualityName(string $quality): string
    {
        return match ($quality) {
            'common' => '普通',
            'magic' => '魔法',
            'rare' => '稀有',
            'legendary' => '传奇',
            'mythic' => '神话',
            default => $quality,
        };
    }
}
