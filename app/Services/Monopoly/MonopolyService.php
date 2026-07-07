<?php

namespace App\Services\Monopoly;

use App\Events\Monopoly\MonopolyLobbyUpdated;
use App\Events\Monopoly\MonopolyStateUpdated;
use App\Models\Monopoly\MonopolyEvent;
use App\Models\Monopoly\MonopolyPlayer;
use App\Models\Monopoly\MonopolyProperty;
use App\Models\Monopoly\MonopolyRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonopolyService
{
    public function listRooms(int $userId): array
    {
        return MonopolyRoom::query()
            ->withCount('players')
            ->whereIn('status', ['waiting', 'playing'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (MonopolyRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'status' => $room->status,
                'players_count' => $room->players_count,
                'max_players' => $room->max_players,
                'is_member' => $room->players()->where('user_id', $userId)->exists(),
                'created_at' => $room->created_at?->toISOString(),
            ])
            ->all();
    }

    public function lobbyRooms(): array
    {
        return MonopolyRoom::query()
            ->withCount('players')
            ->whereIn('status', ['waiting', 'playing'])
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (MonopolyRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'status' => $room->status,
                'players_count' => $room->players_count,
                'max_players' => $room->max_players,
                'is_member' => false,
                'created_at' => $room->created_at?->toISOString(),
            ])
            ->all();
    }

    public function createRoom(User $user, string $name): MonopolyRoom
    {
        return DB::transaction(function () use ($user, $name) {
            $room = MonopolyRoom::create([
                'created_by' => $user->id,
                'name' => $name,
                'status' => 'waiting',
                'max_players' => (int) config('monopoly.max_players'),
                'config' => [
                    'initial_cash' => (int) config('monopoly.initial_cash'),
                    'start_bonus' => (int) config('monopoly.start_bonus'),
                    'max_rounds' => (int) config('monopoly.max_rounds'),
                    'max_houses_per_property' => (int) config('monopoly.max_houses_per_property'),
                    'max_houses_per_build_action' => (int) config('monopoly.max_houses_per_build_action'),
                ],
            ]);

            $this->createPlayer($room, $user->name, 'human', $user->id, true);
            $this->createProperties($room);
            $this->log($room, null, 'room.created', "{$user->name} 创建了房间");

            $this->broadcast($room, 'state.updated');
            $this->broadcastLobby();

            return $room;
        });
    }

    public function joinRoom(MonopolyRoom $room, User $user): MonopolyPlayer
    {
        return DB::transaction(function () use ($room, $user) {
            $room = $this->lockRoom($room);
            $this->assertWaiting($room);
            $this->assertCapacity($room);

            $existing = $room->players()->where('user_id', $user->id)->first();
            if ($existing) {
                return $existing;
            }

            $player = $this->createPlayer($room, $user->name, 'human', $user->id);
            $this->log($room, $player, 'player.joined', "{$player->name} 加入了房间");
            $this->broadcast($room, 'player.joined', ['player_id' => $player->id]);
            $this->broadcastLobby();

            return $player;
        });
    }

    public function leaveRoom(MonopolyRoom $room, User $user): void
    {
        DB::transaction(function () use ($room, $user) {
            $room = $this->lockRoom($room);
            $player = $this->playerForUser($room, $user->id);

            if ($room->status === 'playing') {
                $this->bankrupt($player, "{$player->name} 离开对局并破产");
                $this->advanceTurnIfNeeded($room, $player);
            } else {
                $player->delete();
            }

            $this->log($room, $player, 'player.left', "{$player->name} 离开了房间");
            $this->broadcast($room, 'player.left', ['player_id' => $player->id]);
            $this->broadcastLobby();
        });
    }

    public function addComputer(MonopolyRoom $room, int $userId): MonopolyPlayer
    {
        return DB::transaction(function () use ($room, $userId) {
            $room = $this->lockRoom($room);
            $this->assertHost($room, $userId);
            $this->assertWaiting($room);
            $this->assertCapacity($room);

            $number = $room->players()->where('type', 'computer')->count() + 1;
            $player = $this->createPlayer($room, "电脑 {$number}", 'computer');
            $this->log($room, $player, 'player.joined', "{$player->name} 加入了房间");
            $this->broadcast($room, 'player.joined', ['player_id' => $player->id]);
            $this->broadcastLobby();

            return $player;
        });
    }

    public function removeComputer(MonopolyRoom $room, int $userId, int $playerId): void
    {
        DB::transaction(function () use ($room, $userId, $playerId) {
            $room = $this->lockRoom($room);
            $this->assertHost($room, $userId);
            $this->assertWaiting($room);

            $player = $room->players()->where('type', 'computer')->findOrFail($playerId);
            $name = $player->name;
            $player->delete();
            $this->resequencePlayers($room);
            $this->log($room, null, 'player.left', "{$name} 离开了房间");
            $this->broadcast($room, 'player.left', ['player_id' => $playerId]);
            $this->broadcastLobby();
        });
    }

    public function start(MonopolyRoom $room, int $userId): MonopolyRoom
    {
        return DB::transaction(function () use ($room, $userId) {
            $room = $this->lockRoom($room);
            $this->assertHost($room, $userId);
            $this->assertWaiting($room);

            if ($room->players()->count() < 2) {
                throw ValidationException::withMessages(['players' => '至少需要 2 名玩家']);
            }

            $room->update([
                'status' => 'playing',
                'current_turn_order' => 0,
                'round' => 1,
                'started_at' => now(),
            ]);
            $this->log($room, null, 'game.started', '游戏开始');
            $this->broadcast($room, 'state.updated');
            $this->broadcastLobby();
            $this->runComputerTurns($room);

            return $room;
        });
    }

    public function roll(MonopolyRoom $room, int $userId): array
    {
        return DB::transaction(function () use ($room, $userId) {
            $room = $this->lockRoom($room);
            $player = $this->currentHumanPlayer($room, $userId);
            if ($player->last_roll !== null) {
                throw ValidationException::withMessages(['turn' => '本回合已经掷过骰子']);
            }

            $roll = random_int(1, 6);
            $this->movePlayer($room, $player, $roll);
            $player->last_roll = $roll;
            $player->save();
            $this->resolveLanding($room, $player);
            $this->broadcast($room, 'dice.rolled', ['player_id' => $player->id, 'roll' => $roll]);

            return ['roll' => $roll, 'state' => $this->state($room)];
        });
    }

    public function buy(MonopolyRoom $room, int $userId): MonopolyProperty
    {
        return DB::transaction(function () use ($room, $userId) {
            $room = $this->lockRoom($room);
            $player = $this->currentHumanPlayer($room, $userId);
            $property = $this->buyForPlayer($room, $player);
            $this->broadcast($room, 'state.updated', ['property_id' => $property->id]);

            return $property;
        });
    }

    public function build(MonopolyRoom $room, int $userId, int $propertyId, int $houses): MonopolyProperty
    {
        return DB::transaction(function () use ($room, $userId, $propertyId, $houses) {
            $room = $this->lockRoom($room);
            $player = $this->playerForUser($room, $userId);
            $property = $room->properties()->where('type', 'city')->findOrFail($propertyId);

            if ($property->owner_player_id !== $player->id) {
                throw ValidationException::withMessages(['property' => '只能给自己的城市盖房']);
            }

            $houses = max(1, min($houses, (int) config('monopoly.max_houses_per_build_action')));
            if ($property->houses + $houses > (int) config('monopoly.max_houses_per_property')) {
                throw ValidationException::withMessages(['houses' => '单个地皮最多 5 套房']);
            }

            $cost = $property->house_price * $houses;
            if ($player->cash < $cost) {
                throw ValidationException::withMessages(['cash' => '现金不足']);
            }

            $player->decrement('cash', $cost);
            $property->increment('houses', $houses);
            $this->log($room, $player, 'property.built', "{$player->name} 在 {$property->name} 建造 {$houses} 套房", [
                'property_id' => $property->id,
                'houses' => $houses,
                'cost' => $cost,
            ]);
            $this->broadcast($room, 'state.updated', ['property_id' => $property->id]);

            return $this->freshProperty($property);
        });
    }

    public function endTurn(MonopolyRoom $room, int $userId): void
    {
        DB::transaction(function () use ($room, $userId) {
            $room = $this->lockRoom($room);
            $player = $this->currentHumanPlayer($room, $userId);
            if ($player->last_roll === null && ! $player->is_in_jail) {
                throw ValidationException::withMessages(['turn' => '请先掷骰子']);
            }

            $this->advanceTurn($room);
            $this->broadcast($room, 'turn.advanced');
            $this->runComputerTurns($room);
        });
    }

    public function leaveJail(MonopolyRoom $room, int $userId, string $method): void
    {
        DB::transaction(function () use ($room, $userId, $method) {
            $room = $this->lockRoom($room);
            $player = $this->currentHumanPlayer($room, $userId);
            if (! $player->is_in_jail) {
                throw ValidationException::withMessages(['jail' => '玩家不在监狱中']);
            }

            if ($method === 'card' && $player->jail_cards > 0) {
                $player->decrement('jail_cards');
            } elseif ($method === 'pay') {
                $this->charge($player, 100_000, "{$player->name} 支付 100K 出狱");
            } else {
                throw ValidationException::withMessages(['method' => '不能使用该出狱方式']);
            }

            $player->update(['is_in_jail' => false, 'jail_turns' => 0]);
            $this->log($room, $player, 'jail.left', "{$player->name} 离开监狱");
            $this->broadcast($room, 'state.updated');
        });
    }

    public function state(MonopolyRoom $room): array
    {
        $this->syncProperties($room);
        $room = MonopolyRoom::with(['players.properties', 'properties.owner', 'events.player'])->findOrFail($room->id);
        $board = collect(config('monopoly.board'))->keyBy('index');
        $currentPlayer = $room->players->firstWhere('turn_order', $room->current_turn_order);

        return [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'status' => $room->status,
                'max_players' => $room->max_players,
                'max_rounds' => $this->maxRounds($room),
                'current_turn_order' => $room->current_turn_order,
                'round' => $room->round,
                'created_by' => $room->created_by,
            ],
            'current_player_id' => $currentPlayer?->id,
            'board' => array_values(config('monopoly.board')),
            'players' => $room->players->map(fn (MonopolyPlayer $player) => [
                'id' => $player->id,
                'user_id' => $player->user_id,
                'name' => $player->name,
                'type' => $player->type,
                'turn_order' => $player->turn_order,
                'cash' => $player->cash,
                'position' => $player->position,
                'tile_name' => $board[$player->position]['name'] ?? '',
                'is_host' => $player->is_host,
                'is_bankrupt' => $player->is_bankrupt,
                'is_in_jail' => $player->is_in_jail,
                'jail_turns' => $player->jail_turns,
                'jail_cards' => $player->jail_cards,
                'last_roll' => $player->last_roll,
            ])->values()->all(),
            'properties' => $room->properties->map(fn (MonopolyProperty $property) => [
                'id' => $property->id,
                'tile_index' => $property->tile_index,
                'type' => $property->type,
                'name' => $property->name,
                'price' => $property->price,
                'base_rent' => $property->base_rent,
                'current_rent' => $this->rent($room, $property),
                'house_price' => $property->house_price,
                'owner_player_id' => $property->owner_player_id,
                'owner_name' => $property->owner?->name,
                'houses' => $property->houses,
            ])->values()->all(),
            'events' => $room->events->take(40)->reverse()->values()->map(fn (MonopolyEvent $event) => [
                'id' => $event->id,
                'type' => $event->type,
                'message' => $event->message,
                'player_id' => $event->player_id,
                'payload' => $event->payload,
                'created_at' => $event->created_at?->toISOString(),
            ])->all(),
        ];
    }

    private function createPlayer(MonopolyRoom $room, string $name, string $type, ?int $userId = null, bool $host = false): MonopolyPlayer
    {
        return MonopolyPlayer::create([
            'room_id' => $room->id,
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'turn_order' => (int) $room->players()->max('turn_order') + ($room->players()->exists() ? 1 : 0),
            'cash' => (int) config('monopoly.initial_cash'),
            'is_host' => $host,
        ]);
    }

    private function createProperties(MonopolyRoom $room): void
    {
        foreach (config('monopoly.board') as $tile) {
            if (! in_array($tile['type'], ['city', 'rail', 'air'], true)) {
                continue;
            }

            MonopolyProperty::updateOrCreate(
                [
                    'room_id' => $room->id,
                    'tile_index' => $tile['index'],
                ],
                [
                    'type' => $tile['type'],
                    'name' => $tile['name'],
                    'price' => $tile['price'],
                    'base_rent' => $tile['rent'],
                    'house_price' => $tile['house_price'] ?? 0,
                ]
            );
        }
    }

    private function syncProperties(MonopolyRoom $room): void
    {
        $expectedCount = collect(config('monopoly.board'))
            ->whereIn('type', ['city', 'rail', 'air'])
            ->count();

        if ($room->properties()->count() < $expectedCount) {
            $this->createProperties($room);
        }
    }

    private function lockRoom(MonopolyRoom $room): MonopolyRoom
    {
        return MonopolyRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
    }

    private function assertWaiting(MonopolyRoom $room): void
    {
        if ($room->status !== 'waiting') {
            throw ValidationException::withMessages(['room' => '房间不在等待状态']);
        }
    }

    private function assertCapacity(MonopolyRoom $room): void
    {
        if ($room->players()->count() >= $room->max_players) {
            throw ValidationException::withMessages(['players' => '房间人数已满']);
        }
    }

    private function assertHost(MonopolyRoom $room, int $userId): void
    {
        if ($room->created_by !== $userId) {
            throw ValidationException::withMessages(['host' => '只有房主可以执行该操作']);
        }
    }

    private function playerForUser(MonopolyRoom $room, int $userId): MonopolyPlayer
    {
        return $room->players()->where('user_id', $userId)->firstOrFail();
    }

    private function currentHumanPlayer(MonopolyRoom $room, int $userId): MonopolyPlayer
    {
        if ($room->status !== 'playing') {
            throw ValidationException::withMessages(['room' => '游戏尚未开始']);
        }

        $player = $this->playerForUser($room, $userId);
        if ($player->turn_order !== $room->current_turn_order || $player->is_bankrupt) {
            throw ValidationException::withMessages(['turn' => '还没有轮到你']);
        }

        return $player;
    }

    private function movePlayer(MonopolyRoom $room, MonopolyPlayer $player, int $steps): void
    {
        if ($player->is_in_jail) {
            $player->increment('jail_turns');
            $this->log($room, $player, 'jail.wait', "{$player->name} 在监狱中等待");

            return;
        }

        $boardCount = count(config('monopoly.board'));
        $old = $player->position;
        $new = ($old + $steps) % $boardCount;
        $player->position = $new;

        if ($old + $steps >= $boardCount) {
            $player->cash += (int) config('monopoly.start_bonus');
            $this->log($room, $player, 'start.bonus', "{$player->name} 经过起点，获得 2M");
        }

        $player->save();
        $tile = $this->tile($new);
        $this->log($room, $player, 'player.moved', "{$player->name} 前进 {$steps} 格，到达 {$tile['name']}", [
            'roll' => $steps,
            'position' => $new,
        ]);
    }

    private function resolveLanding(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        if ($player->is_in_jail || $player->is_bankrupt) {
            return;
        }

        $tile = $this->tile($player->position);
        match ($tile['type']) {
            'city', 'rail', 'air' => $this->resolvePropertyLanding($room, $player),
            'chance' => $this->drawCard($room, $player, 'chance'),
            'welfare' => $this->drawCard($room, $player, 'welfare'),
            'jail' => $this->sendToJail($room, $player, "{$player->name} 到达监狱参观区"),
            'start' => $this->grantStartLanding($room, $player),
            default => null,
        };
    }

    private function resolvePropertyLanding(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        $property = $room->properties()->where('tile_index', $player->position)->firstOrFail();
        if ($property->owner_player_id === null) {
            $this->log($room, $player, 'property.available', "{$property->name} 可以购买，价格 {$property->price}");

            return;
        }

        if ($property->owner_player_id === $player->id) {
            $this->log($room, $player, 'property.owned', "{$player->name} 到达自己的 {$property->name}");

            return;
        }

        $owner = MonopolyPlayer::findOrFail($property->owner_player_id);
        $rent = $this->rent($room, $property);
        $paid = min($player->cash, $rent);
        $player->decrement('cash', $paid);
        $owner->increment('cash', $paid);
        $this->log($room, $player, 'rent.paid', "{$player->name} 向 {$owner->name} 支付 {$paid} 租金", [
            'rent' => $rent,
            'paid' => $paid,
            'property_id' => $property->id,
        ]);

        $player = $this->freshPlayer($player);
        if ($paid < $rent || $player->cash <= 0) {
            $this->bankrupt($player, "{$player->name} 现金不足，破产");
        }
    }

    private function buyForPlayer(MonopolyRoom $room, MonopolyPlayer $player): MonopolyProperty
    {
        $property = $room->properties()->where('tile_index', $player->position)->firstOrFail();
        if ($property->owner_player_id !== null) {
            throw ValidationException::withMessages(['property' => '该资产已经被购买']);
        }
        if ($player->cash < $property->price) {
            throw ValidationException::withMessages(['cash' => '现金不足']);
        }

        $player->decrement('cash', $property->price);
        $property->update(['owner_player_id' => $player->id]);
        $this->log($room, $player, 'property.bought', "{$player->name} 购买了 {$property->name}", [
            'property_id' => $property->id,
            'price' => $property->price,
        ]);

        return $this->freshProperty($property);
    }

    private function drawCard(MonopolyRoom $room, MonopolyPlayer $player, string $deck): void
    {
        $cards = config($deck === 'chance' ? 'monopoly.chance_cards' : 'monopoly.welfare_cards');
        $card = $cards[array_rand($cards)];
        $this->log($room, $player, "{$deck}.drawn", "{$player->name} 抽到 {$card['title']}：{$card['description']}", $card);

        match ($card['action']) {
            'cash' => $this->applyCash($room, $player, (int) $card['amount']),
            'move_to' => $this->moveTo($room, $player, (int) $card['position'], (bool) ($card['grant_start_bonus'] ?? false)),
            'move_steps' => $this->movePlayer($room, $player, (int) $card['steps']),
            'jail' => $this->sendToJail($room, $player, "{$player->name} 被送进监狱"),
            'jail_card' => $this->grantJailCard($room, $player),
            default => null,
        };

        if (in_array($card['action'], ['move_to', 'move_steps'], true)) {
            $this->resolveLanding($room, $player->fresh());
        }
    }

    private function applyCash(MonopolyRoom $room, MonopolyPlayer $player, int $amount): void
    {
        if ($amount >= 0) {
            $player->increment('cash', $amount);
            $this->log($room, $player, 'cash.received', "{$player->name} 获得 {$amount}", ['amount' => $amount]);

            return;
        }

        $this->charge($player, abs($amount), "{$player->name} 支付 " . abs($amount));
        $player = $this->freshPlayer($player);
        if ($player->cash <= 0) {
            $this->bankrupt($player, "{$player->name} 现金不足，破产");
        }
    }

    private function charge(MonopolyPlayer $player, int $amount, string $message): void
    {
        $player->decrement('cash', min($player->cash, $amount));
        $this->log($player->room()->firstOrFail(), $player, 'cash.paid', $message, ['amount' => $amount]);
    }

    private function grantJailCard(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        $player->increment('jail_cards');
        $this->log($room, $player, 'jail.card.received', "{$player->name} 获得 1 张出狱卡");
    }

    private function moveTo(MonopolyRoom $room, MonopolyPlayer $player, int $position, bool $grantStartBonus): void
    {
        $player->position = $position;
        if ($grantStartBonus) {
            $player->cash += (int) config('monopoly.start_bonus');
        }
        $player->save();
        $this->log($room, $player, 'player.moved', "{$player->name} 移动到 {$this->tile($position)['name']}");
    }

    private function sendToJail(MonopolyRoom $room, MonopolyPlayer $player, string $message): void
    {
        $player->update([
            'position' => (int) config('monopoly.jail_position'),
            'is_in_jail' => true,
            'jail_turns' => 0,
        ]);
        $this->log($room, $player, 'jail.entered', $message);
    }

    private function grantStartLanding(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        $this->log($room, $player, 'start.landed', "{$player->name} 到达起点");
    }

    private function rent(MonopolyRoom $room, MonopolyProperty $property): int
    {
        if ($property->type === 'city') {
            return (int) floor(($property->price + ($property->house_price * $property->houses)) * 0.1);
        }

        $ownedCount = $room->properties()
            ->where('type', $property->type)
            ->where('owner_player_id', $property->owner_player_id)
            ->count();

        return $property->base_rent * max(1, $ownedCount);
    }

    private function bankrupt(MonopolyPlayer $player, string $message): void
    {
        $player->update(['is_bankrupt' => true, 'cash' => 0]);
        MonopolyProperty::where('owner_player_id', $player->id)->update([
            'owner_player_id' => null,
            'houses' => 0,
        ]);
        $this->log($player->room()->firstOrFail(), $player, 'player.bankrupt', $message);
    }

    private function advanceTurnIfNeeded(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        if ($room->current_turn_order === $player->turn_order) {
            $this->advanceTurn($room);
        }
    }

    private function advanceTurn(MonopolyRoom $room): void
    {
        $players = $room->players()->where('is_bankrupt', false)->orderBy('turn_order')->get();
        if ($players->count() <= 1) {
            $winner = $players->first();
            $room->update(['status' => 'finished', 'finished_at' => now()]);
            $this->log($room, $winner, 'game.finished', $winner ? "{$winner->name} 获胜" : '游戏结束', [
                'reason' => 'bankruptcy',
                'winner_player_id' => $winner?->id,
            ]);

            return;
        }

        $currentIndex = $players->search(fn (MonopolyPlayer $player) => $player->turn_order === $room->current_turn_order);
        $next = $players[($currentIndex === false ? 0 : $currentIndex + 1) % $players->count()];
        $players->each(fn (MonopolyPlayer $player) => $player->update(['last_roll' => null]));
        $room->current_turn_order = $next->turn_order;
        if ($next->turn_order === 0) {
            $room->round++;
        }
        $room->save();

        if ($room->round > $this->maxRounds($room)) {
            $this->finishByNetWorth($room);

            return;
        }

        $this->log($room, $next, 'turn.advanced', "轮到 {$next->name}");
    }

    private function finishByNetWorth(MonopolyRoom $room): void
    {
        $standings = $room->players()
            ->where('is_bankrupt', false)
            ->orderBy('turn_order')
            ->get()
            ->map(fn (MonopolyPlayer $player) => [
                'player' => $player,
                'net_worth' => $this->netWorth($player),
            ])
            ->sort(function (array $left, array $right) {
                /** @var MonopolyPlayer $leftPlayer */
                $leftPlayer = $left['player'];
                /** @var MonopolyPlayer $rightPlayer */
                $rightPlayer = $right['player'];

                return [$right['net_worth'], $rightPlayer->cash] <=> [$left['net_worth'], $leftPlayer->cash];
            })
            ->values();

        $winnerEntry = $standings->first();
        $winner = $winnerEntry['player'] ?? null;
        $winnerNetWorth = (int) ($winnerEntry['net_worth'] ?? 0);
        $room->update(['status' => 'finished', 'finished_at' => now()]);

        $this->log(
            $room,
            $winner,
            'game.finished',
            $winner
                ? "达到 {$this->maxRounds($room)} 轮，{$winner->name} 以净资产 {$this->formatAmount($winnerNetWorth)} 获胜"
                : '达到最大轮数，游戏结束',
            [
                'reason' => 'max_rounds',
                'max_rounds' => $this->maxRounds($room),
                'winner_player_id' => $winner?->id,
                'winner_net_worth' => $winner ? $winnerNetWorth : null,
                'standings' => $standings->map(function (array $standing) {
                    /** @var MonopolyPlayer $player */
                    $player = $standing['player'];

                    return [
                        'player_id' => $player->id,
                        'name' => $player->name,
                        'cash' => $player->cash,
                        'net_worth' => (int) $standing['net_worth'],
                    ];
                })->all(),
            ]
        );
    }

    private function netWorth(MonopolyPlayer $player): int
    {
        $assets = MonopolyProperty::query()
            ->where('owner_player_id', $player->id)
            ->get()
            ->sum(fn (MonopolyProperty $property) => $property->price + ($property->house_price * $property->houses));

        return $player->cash + (int) $assets;
    }

    private function maxRounds(MonopolyRoom $room): int
    {
        return max(1, (int) ($room->config['max_rounds'] ?? config('monopoly.max_rounds')));
    }

    private function formatAmount(int $amount): string
    {
        if ($amount >= 1_000_000) {
            return rtrim(rtrim(number_format($amount / 1_000_000, 1), '0'), '.') . 'M';
        }

        return (int) floor($amount / 1_000) . 'K';
    }

    private function runComputerTurns(MonopolyRoom $room): void
    {
        $guard = 0;
        while ($guard++ < 20) {
            $room = $this->freshRoom($room);
            if ($room->status !== 'playing') {
                return;
            }

            $player = $room->players()->where('turn_order', $room->current_turn_order)->first();
            if (! $player?->isComputer() || $player->is_bankrupt) {
                return;
            }

            if ($player->is_in_jail) {
                if ($player->jail_cards > 0) {
                    $player->decrement('jail_cards');
                    $player->update(['is_in_jail' => false, 'jail_turns' => 0]);
                } elseif ($player->cash > 300_000) {
                    $this->charge($player, 100_000, "{$player->name} 支付 100K 出狱");
                    $player->update(['is_in_jail' => false, 'jail_turns' => 0]);
                }
            }

            $roll = random_int(1, 6);
            $player = $this->freshPlayer($player);
            $this->movePlayer($room, $player, $roll);
            $player = $this->freshPlayer($player);
            $player->update(['last_roll' => $roll]);
            $this->resolveLanding($room, $player);
            $this->computerBuyOrBuild($room, $this->freshPlayer($player));
            $this->advanceTurn($room);
        }

        $this->broadcast($this->freshRoom($room), 'turn.advanced');
    }

    private function computerBuyOrBuild(MonopolyRoom $room, MonopolyPlayer $player): void
    {
        if ($player->is_bankrupt) {
            return;
        }

        $property = $room->properties()->where('tile_index', $player->position)->first();
        if ($property && $property->owner_player_id === null && $player->cash - $property->price >= 250_000) {
            $this->buyForPlayer($room, $player);

            return;
        }

        $buildTarget = $player->properties()
            ->where('type', 'city')
            ->where('houses', '<', (int) config('monopoly.max_houses_per_property'))
            ->orderByDesc('base_rent')
            ->first();

        if ($buildTarget && $player->cash - $buildTarget->house_price >= 500_000) {
            $player->decrement('cash', $buildTarget->house_price);
            $buildTarget->increment('houses');
            $this->log($room, $player, 'property.built', "{$player->name} 在 {$buildTarget->name} 建造 1 套房");
        }
    }

    private function resequencePlayers(MonopolyRoom $room): void
    {
        $room->players()->orderBy('turn_order')->get()->values()->each(function (MonopolyPlayer $player, int $index) {
            $player->update(['turn_order' => $index]);
        });
    }

    private function tile(int $position): array
    {
        return collect(config('monopoly.board'))->firstWhere('index', $position) ?? config('monopoly.board')[0];
    }

    private function freshRoom(MonopolyRoom $room): MonopolyRoom
    {
        return MonopolyRoom::findOrFail($room->id);
    }

    private function freshPlayer(MonopolyPlayer $player): MonopolyPlayer
    {
        return MonopolyPlayer::findOrFail($player->id);
    }

    private function freshProperty(MonopolyProperty $property): MonopolyProperty
    {
        return MonopolyProperty::findOrFail($property->id);
    }

    private function log(MonopolyRoom $room, ?MonopolyPlayer $player, string $type, string $message, array $payload = []): MonopolyEvent
    {
        return MonopolyEvent::create([
            'room_id' => $room->id,
            'player_id' => $player?->id,
            'type' => $type,
            'message' => $message,
            'payload' => $payload ?: null,
        ]);
    }

    private function broadcast(MonopolyRoom $room, string $type, array $payload = []): void
    {
        broadcast(new MonopolyStateUpdated($room->id, $type, $this->state($room), $payload))->toOthers();
    }

    private function broadcastLobby(): void
    {
        broadcast(new MonopolyLobbyUpdated($this->lobbyRooms()))->toOthers();
    }
}
