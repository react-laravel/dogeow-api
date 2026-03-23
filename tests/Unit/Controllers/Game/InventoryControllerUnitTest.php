<?php

namespace Tests\Unit\Controllers\Game;

use App\Http\Controllers\Api\Game\InventoryController;
use App\Http\Requests\Game\EquipItemRequest;
use App\Http\Requests\Game\MoveItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Http\Requests\Game\UnequipItemRequest;
use App\Http\Requests\Game\UsePotionRequest;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameInventoryService;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class InventoryControllerUnitTest extends TestCase
{
    private GameInventoryService $inventoryService;

    private InventoryController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = Mockery::mock(GameInventoryService::class);
        $this->controller = new InventoryController($this->inventoryService);

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')->andReturnNull()->byDefault();
        $broadcastFactory = Mockery::mock(BroadcastFactory::class);
        $broadcastFactory->shouldReceive('event')->andReturnUsing(
            fn ($event) => new PendingBroadcast($dispatcher, $event)
        )->byDefault();
        $this->app->instance(BroadcastFactory::class, $broadcastFactory);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_index_returns_inventory_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['items' => [['id' => 1]], 'equipment' => []];

        $this->inventoryService->shouldReceive('getInventory')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->index($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($payload['items'], $data['data']['items']);
    }

    public function test_equip_returns_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['slot' => 'weapon'];
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('equipItem')->once()->with($this->sameCharacter($character), $item->id)->andReturn($payload);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->equip($this->makeFormRequest(EquipItemRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('装备成功', $data['message']);
        $this->assertSame($payload['slot'], $data['data']['slot']);
    }

    public function test_equip_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('equipItem')->once()->with($this->sameCharacter($character), $item->id)->andThrow(new \RuntimeException('无法装备'));

        $response = $this->controller->equip($this->makeFormRequest(EquipItemRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('无法装备', $data['message']);
    }

    public function test_unequip_returns_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('unequipItem')->once()->with($this->sameCharacter($character), 'weapon')->andReturn(['item_id' => 3]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->unequip($this->makeFormRequest(UnequipItemRequest::class, $user, $character, [
            'slot' => 'weapon',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('卸下装备成功', $data['message']);
    }

    public function test_unequip_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('unequipItem')->once()->with($this->sameCharacter($character), 'weapon')->andThrow(new \RuntimeException('无法卸下'));

        $response = $this->controller->unequip($this->makeFormRequest(UnequipItemRequest::class, $user, $character, [
            'slot' => 'weapon',
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('无法卸下', $data['message']);
    }

    public function test_sell_returns_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('sellItem')->once()->with($this->sameCharacter($character), $item->id, 2)->andReturn(['total_price' => 15]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sell($this->makeFormRequest(SellItemRequest::class, $user, $character, [
            'item_id' => $item->id,
            'quantity' => 2,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('出售成功', $data['message']);
        $this->assertSame(15, $data['data']['total_price']);
    }

    public function test_sell_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('sellItem')->once()->with($this->sameCharacter($character), $item->id, 1)->andThrow(new \RuntimeException('无法出售'));

        $response = $this->controller->sell($this->makeFormRequest(SellItemRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('无法出售', $data['message']);
    }

    public function test_move_returns_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('moveItem')->once()->with($this->sameCharacter($character), $item->id, true, 4)->andReturn(['slot_index' => 4]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->move($this->makeFormRequest(MoveItemRequest::class, $user, $character, [
            'item_id' => $item->id,
            'to_storage' => true,
            'slot_index' => 4,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('移动成功', $data['message']);
        $this->assertSame(4, $data['data']['slot_index']);
    }

    public function test_move_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->inventoryService->shouldReceive('moveItem')->once()->with($this->sameCharacter($character), $item->id, false, null)->andThrow(new \RuntimeException('无法移动'));

        $response = $this->controller->move($this->makeFormRequest(MoveItemRequest::class, $user, $character, [
            'item_id' => $item->id,
            'to_storage' => false,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('无法移动', $data['message']);
    }

    public function test_use_potion_returns_success(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character, ['quantity' => 3]);

        $this->inventoryService->shouldReceive('usePotion')->once()->with($this->sameCharacter($character), $item->id)->andReturn(['healed' => 20]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->usePotion($this->makeFormRequest(UsePotionRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('使用药品成功', $data['message']);
        $this->assertSame(20, $data['data']['healed']);
    }

    public function test_use_potion_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character, ['quantity' => 3]);

        $this->inventoryService->shouldReceive('usePotion')->once()->with($this->sameCharacter($character), $item->id)->andThrow(new \RuntimeException('无法使用药品'));

        $response = $this->controller->usePotion($this->makeFormRequest(UsePotionRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('无法使用药品', $data['message']);
    }

    public function test_sort_returns_default_message(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sortInventory')->once()->with($this->sameCharacter($character), 'default')->andReturn(['sorted' => true]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sort($this->makeValidatedRequest($user, $character, [
            'sort_by' => 'default',
        ]));

        $this->assertSame('整理完成', json_decode($response->getContent(), true)['message']);
    }

    public function test_sort_returns_quality_message(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sortInventory')->once()->with($this->sameCharacter($character), 'quality')->andReturn(['sorted' => true]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sort($this->makeValidatedRequest($user, $character, [
            'sort_by' => 'quality',
        ]));

        $this->assertSame('按品质整理完成', json_decode($response->getContent(), true)['message']);
    }

    public function test_sort_returns_price_message(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sortInventory')->once()->with($this->sameCharacter($character), 'price')->andReturn(['sorted' => true]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sort($this->makeValidatedRequest($user, $character, [
            'sort_by' => 'price',
        ]));

        $this->assertSame('按价格整理完成', json_decode($response->getContent(), true)['message']);
    }

    public function test_sort_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sortInventory')->once()->with($this->sameCharacter($character), 'default')->andThrow(new \RuntimeException('整理失败'));

        $response = $this->controller->sort($this->makeValidatedRequest($user, $character, [
            'sort_by' => 'default',
        ]));

        $this->assertSame('整理失败', json_decode($response->getContent(), true)['message']);
    }

    public function test_sell_by_quality_returns_success_message(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sellItemsByQuality')->once()->with($this->sameCharacter($character), 'rare')->andReturn([
            'count' => 2,
            'total_price' => 45,
        ]);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sellByQuality($this->makeValidatedRequest($user, $character, [
            'quality' => 'rare',
        ]));

        $this->assertSame('已出售 2 件稀有物品，获得 45 铜', json_decode($response->getContent(), true)['message']);
    }

    public function test_sell_by_quality_returns_error_when_service_throws(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->inventoryService->shouldReceive('sellItemsByQuality')->once()->with($this->sameCharacter($character), 'common')->andThrow(new \RuntimeException('批量出售失败'));

        $response = $this->controller->sellByQuality($this->makeValidatedRequest($user, $character, [
            'quality' => 'common',
        ]));

        $this->assertSame('批量出售失败', json_decode($response->getContent(), true)['message']);
    }

    public function test_get_quality_name_returns_original_for_unknown_quality(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getQualityName');
        $method->setAccessible(true);

        $this->assertSame('ancient', $method->invoke($this->controller, 'ancient'));
    }

    public function test_get_quality_name_returns_magic_for_magic(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getQualityName');
        $method->setAccessible(true);

        $this->assertSame('魔法', $method->invoke($this->controller, 'magic'));
    }

    public function test_get_quality_name_returns_legendary_for_legendary(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getQualityName');
        $method->setAccessible(true);

        $this->assertSame('传奇', $method->invoke($this->controller, 'legendary'));
    }

    public function test_get_quality_name_returns_mythic_for_mythic(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getQualityName');
        $method->setAccessible(true);

        $this->assertSame('神话', $method->invoke($this->controller, 'mythic'));
    }

    private function createCharacter(User $user, array $attributes = []): GameCharacter
    {
        return GameCharacter::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Hero ' . $user->id,
            'class' => 'warrior',
            'gender' => 'male',
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'strength' => 10,
            'dexterity' => 10,
            'vitality' => 10,
            'energy' => 10,
            'skill_points' => 0,
            'current_hp' => 100,
            'current_mana' => 50,
            'current_map_id' => 1,
            'is_fighting' => false,
            'difficulty_tier' => 1,
        ], $attributes));
    }

    private function createItemDefinition(array $attributes = []): GameItemDefinition
    {
        return GameItemDefinition::create(array_merge([
            'name' => 'Basic Sword',
            'type' => 'weapon',
            'sub_type' => 'sword',
            'sockets' => 0,
            'gem_stats' => null,
            'base_stats' => ['attack' => 10],
            'required_level' => 1,
            'icon' => 'sword',
            'description' => 'Basic item definition',
            'is_active' => true,
            'buy_price' => 100,
            'sell_price' => 50,
        ], $attributes));
    }

    private function createInventoryItem(GameCharacter $character, array $attributes = []): GameItem
    {
        $definition = $this->createItemDefinition();

        return GameItem::create(array_merge([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => $definition->base_stats,
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 20,
        ], $attributes));
    }

    private function sameCharacter(GameCharacter $character): mixed
    {
        return Mockery::on(static fn ($candidate): bool => $candidate instanceof GameCharacter && $candidate->is($character));
    }

    private function makeRequest(User $user, GameCharacter $character, array $payload = []): Request
    {
        $request = Request::create('/api/rpg/inventory', 'GET', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @param  class-string<FormRequest>  $class
     */
    private function makeFormRequest(string $class, User $user, GameCharacter $character, array $payload = []): FormRequest
    {
        /** @var FormRequest $request */
        $request = $class::create('/api/rpg/inventory', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function makeValidatedRequest(User $user, GameCharacter $character, array $payload = []): Request
    {
        $request = Request::create('/api/rpg/inventory', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
