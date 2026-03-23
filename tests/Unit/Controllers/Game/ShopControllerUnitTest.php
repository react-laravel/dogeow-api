<?php

namespace Tests\Unit\Controllers\Game;

use App\Http\Controllers\Api\Game\ShopController;
use App\Http\Requests\Game\BuyItemRequest;
use App\Http\Requests\Game\SellItemRequest;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Game\GameItemDefinition;
use App\Models\User;
use App\Services\Game\GameInventoryService;
use App\Services\Game\GameShopService;
use Illuminate\Broadcasting\PendingBroadcast;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class ShopControllerUnitTest extends TestCase
{
    private GameShopService $shopService;

    private GameInventoryService $inventoryService;

    private ShopController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shopService = Mockery::mock(GameShopService::class);
        $this->inventoryService = Mockery::mock(GameInventoryService::class);
        $this->controller = new ShopController($this->shopService, $this->inventoryService);

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

    public function test_index_returns_shop_items(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['items' => [['id' => 1]], 'player_copper' => 88];

        $this->shopService->shouldReceive('getShopItems')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->index($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Success', $data['message']);
        $this->assertSame($payload['player_copper'], $data['data']['player_copper']);
        $this->assertSame($payload['items'], $data['data']['items']);
    }

    public function test_index_returns_error_when_service_fails(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->shopService->shouldReceive('getShopItems')->once()->with($this->sameCharacter($character))->andThrow(new \RuntimeException('boom'));

        $response = $this->controller->index($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('获取商店物品失败', $data['message']);
        $this->assertSame('boom', $data['errors']['error']);
    }

    public function test_refresh_returns_success_payload(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['items' => [['id' => 9]], 'player_copper' => 0];

        $this->shopService->shouldReceive('refreshShop')->once()->with($this->sameCharacter($character))->andReturn($payload);

        $response = $this->controller->refresh($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('刷新成功', $data['message']);
        $this->assertSame($payload['items'], $data['data']['items']);
    }

    public function test_refresh_returns_error_when_service_fails(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);

        $this->shopService->shouldReceive('refreshShop')->once()->with($this->sameCharacter($character))->andThrow(new \InvalidArgumentException('货币不足'));

        $response = $this->controller->refresh($this->makeRequest($user, $character));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('货币不足', $data['message']);
    }

    public function test_buy_returns_success_and_broadcasts_inventory(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['item' => ['id' => 5]];
        $definition = $this->createItemDefinition();

        $this->shopService->shouldReceive('buyItem')->once()->with($this->sameCharacter($character), $definition->id, 2)->andReturn($payload);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->buy($this->makeFormRequest(BuyItemRequest::class, $user, $character, [
            'item_id' => $definition->id,
            'quantity' => 2,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('购买成功', $data['message']);
        $this->assertSame($payload['item'], $data['data']['item']);
    }

    public function test_buy_returns_error_when_service_fails(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $definition = $this->createItemDefinition();

        $this->shopService->shouldReceive('buyItem')->once()->with($this->sameCharacter($character), $definition->id, 1)->andThrow(new \RuntimeException('购买失败'));

        $response = $this->controller->buy($this->makeFormRequest(BuyItemRequest::class, $user, $character, [
            'item_id' => $definition->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('购买失败', $data['message']);
    }

    public function test_sell_returns_success_and_broadcasts_inventory(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $payload = ['sold' => 1];
        $item = $this->createInventoryItem($character);

        $this->shopService->shouldReceive('sellItem')->once()->with($this->sameCharacter($character), $item->id, 3)->andReturn($payload);
        $this->inventoryService->shouldReceive('getInventoryForBroadcast')->once()->with($this->sameCharacter($character))->andReturn(['items' => []]);

        $response = $this->controller->sell($this->makeFormRequest(SellItemRequest::class, $user, $character, [
            'item_id' => $item->id,
            'quantity' => 3,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('出售成功', $data['message']);
        $this->assertSame($payload['sold'], $data['data']['sold']);
    }

    public function test_sell_returns_error_when_service_fails(): void
    {
        $user = User::factory()->create();
        $character = $this->createCharacter($user);
        $item = $this->createInventoryItem($character);

        $this->shopService->shouldReceive('sellItem')->once()->with($this->sameCharacter($character), $item->id, 1)->andThrow(new \RuntimeException('出售失败'));

        $response = $this->controller->sell($this->makeFormRequest(SellItemRequest::class, $user, $character, [
            'item_id' => $item->id,
        ]));
        $data = json_decode($response->getContent(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('出售失败', $data['message']);
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
            'name' => 'Potion',
            'type' => 'potion',
            'sub_type' => 'hp',
            'base_stats' => ['hp' => 10],
            'required_level' => 1,
            'icon' => 'potion',
            'description' => 'Potion',
            'is_active' => true,
            'buy_price' => 10,
            'sell_price' => 5,
        ], $attributes));
    }

    private function createInventoryItem(GameCharacter $character, array $attributes = []): GameItem
    {
        $definition = $this->createItemDefinition([
            'type' => 'weapon',
            'sub_type' => 'sword',
            'base_stats' => ['attack' => 4],
        ]);

        return GameItem::create(array_merge([
            'character_id' => $character->id,
            'definition_id' => $definition->id,
            'quality' => 'common',
            'stats' => ['attack' => 4],
            'affixes' => [],
            'is_in_storage' => false,
            'is_equipped' => false,
            'quantity' => 1,
            'slot_index' => 0,
            'sockets' => 0,
            'sell_price' => 5,
        ], $attributes));
    }

    private function sameCharacter(GameCharacter $character): mixed
    {
        return Mockery::on(static fn ($candidate): bool => $candidate instanceof GameCharacter && $candidate->is($character));
    }

    private function makeRequest(User $user, GameCharacter $character, array $payload = []): Request
    {
        $request = Request::create('/api/rpg/shop', 'GET', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    /**
     * @param  class-string<\Illuminate\Foundation\Http\FormRequest>  $class
     */
    private function makeFormRequest(string $class, User $user, GameCharacter $character, array $payload = []): object
    {
        $request = $class::create('/api/rpg/shop', 'POST', array_merge([
            'character_id' => $character->id,
        ], $payload));
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}
