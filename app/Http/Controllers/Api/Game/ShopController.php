<?php

namespace App\Http\Controllers\Api\Game;

use App\Events\Game\GameInventoryUpdate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\BuyItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Services\Game\GameInventoryService;
use App\Services\Game\GameShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class ShopController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    public function __construct(
        private readonly GameShopService $shopService,
        private readonly GameInventoryService $inventoryService,
    ) {}

    /**
     * 获取商店物品列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->shopService->getShopItems($character);

            return $this->success($result);
        } catch (Throwable $e) {
            return $this->error('获取商店物品失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 强制刷新商店：扣除 1 银币后清除缓存并返回新的商店列表
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->shopService->refreshShop($character);

            return $this->success($result, '刷新成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 购买物品
     */
    public function buy(BuyItemRequest $request): JsonResponse
    {
        try {
            $character = $this->getCharacter($request);
            $result = $this->shopService->buyItem(
                $character,
                $request->input('item_id'),
                $request->input('quantity', 1)
            );
            broadcast(new GameInventoryUpdate($character->id, $this->inventoryService->getInventoryForBroadcast($character)));

            return $this->success($result, '购买成功');
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
            $result = $this->shopService->sellItem(
                $character,
                $request->input('item_id'),
                $request->input('quantity', 1)
            );
            broadcast(new GameInventoryUpdate($character->id, $this->inventoryService->getInventoryForBroadcast($character)));

            return $this->success($result, '出售成功');
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
