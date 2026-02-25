<?php

namespace App\Http\Controllers\Api\Game;

use App\Http\Controllers\Controller;
use App\Http\Requests\Game\SocketGemRequest;
use App\Http\Requests\Game\UnsocketGemRequest;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemGem;
use App\Services\Game\GameInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GemController extends Controller
{
    use \App\Http\Controllers\Concerns\CharacterConcern;

    /**
     * 镶嵌宝石到装备
     */
    public function socket(SocketGemRequest $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        // 获取装备和宝石
        /** @var GameItem $equipment */
        $equipment = GameItem::where('id', $request->input('item_id'))
            ->where('character_id', $character->id)
            ->firstOrFail();

        /** @var GameItem $gemItem */
        $gemItem = GameItem::where('id', $request->input('gem_item_id'))
            ->where('character_id', $character->id)
            ->where('is_in_storage', false)
            ->firstOrFail();

        $gemDefinition = $gemItem->definition;

        // 验证是否为宝石
        if ($gemDefinition->type !== 'gem') {
            return $this->error('只能镶嵌宝石');
        }

        // 验证装备类型
        $equipmentTypes = ['weapon', 'helmet', 'armor', 'gloves', 'boots', 'belt', 'ring', 'amulet'];
        if (! in_array($equipment->definition->type, $equipmentTypes)) {
            return $this->error('只能向装备镶嵌宝石');
        }

        // 验证插槽数量
        if ($equipment->sockets <= 0) {
            return $this->error('该装备没有宝石插槽');
        }

        // 验证插槽索引
        if ($request->input('socket_index') >= $equipment->sockets) {
            return $this->error('插槽索引超出范围');
        }

        // 检查该插槽是否已有宝石
        $existingGem = GameItemGem::where('item_id', $equipment->id)
            ->where('socket_index', $request->input('socket_index'))
            ->first();

        if ($existingGem) {
            return $this->error('该插槽已有宝石，请先卸下');
        }

        // 镶嵌宝石
        GameItemGem::create([
            'item_id' => $equipment->id,
            'gem_definition_id' => $gemDefinition->id,
            'socket_index' => $request->input('socket_index'),
        ]);

        // 删除宝石物品
        $gemItem->delete();

        return $this->success([
            'equipment' => $equipment->load('gems.gemDefinition'),
            'message' => '宝石镶嵌成功',
        ], '宝石镶嵌成功');
    }

    /**
     * 从装备卸下宝石
     * 只有普通(white)品质的装备可以取下宝石，暗金/套装等无法取下
     */
    public function unsocket(UnsocketGemRequest $request): JsonResponse
    {
        $character = $this->getCharacter($request);

        // 获取装备
        /** @var GameItem $equipment */
        $equipment = GameItem::where('id', $request->input('item_id'))
            ->where('character_id', $character->id)
            ->firstOrFail();

        // 只有普通品质的装备可以取下宝石
        if ($equipment->quality !== 'common') {
            return $this->error('该装备无法取下宝石');
        }

        // 查找宝石
        $gem = GameItemGem::where('item_id', $equipment->id)
            ->where('socket_index', $request->input('socket_index'))
            ->first();

        if (! $gem) {
            return $this->error('该插槽没有宝石');
        }

        $gemDefinition = $gem->gemDefinition;

        // 检查背包空间
        $inventoryService = new GameInventoryService;
        $inventoryCount = $character->items()->where('is_in_storage', false)->count();
        if ($inventoryCount >= $inventoryService::INVENTORY_SIZE) {
            return $this->error('背包已满，无法卸下宝石');
        }

        // 找到空位
        $slotIndex = $inventoryService->findEmptySlot($character, false);

        // 创建宝石物品
        GameItem::create([
            'character_id' => $character->id,
            'definition_id' => $gemDefinition->id,
            'quality' => 'common',
            'stats' => [],
            'affixes' => [],
            'is_in_storage' => false,
            'quantity' => 1,
            'slot_index' => $slotIndex,
            'sockets' => 0,
        ]);

        // 删除镶嵌记录
        $gem->delete();

        return $this->success([
            'equipment' => $equipment->load('gems.gemDefinition'),
            'message' => '宝石卸下成功',
        ], '宝石卸下成功');
    }

    /**
     * 获取装备的宝石信息
     */
    public function getGems(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|integer|exists:game_items,id',
        ]);

        $character = $this->getCharacter($request);

        /** @var GameItem $item */
        $item = GameItem::where('id', $validated['item_id'])
            ->where('character_id', $character->id)
            ->with('gems.gemDefinition')
            ->firstOrFail();

        return $this->success([
            'item' => $item,
            'sockets' => $item->sockets,
            'socketed_gems' => $item->gems,
        ]);
    }
}
