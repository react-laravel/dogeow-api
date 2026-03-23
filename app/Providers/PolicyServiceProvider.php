<?php

namespace App\Providers;

use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatRoom;
use App\Models\Chat\ChatRoomUser;
use App\Models\Game\GameCharacter;
use App\Models\Game\GameItem;
use App\Models\Note\Note;
use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\Word\Word;
use App\Policies\Chat\ChatMessagePolicy;
use App\Policies\Chat\ChatModerationPolicy;
use App\Policies\Chat\ChatRoomPolicy;
use App\Policies\Game\GameCharacterPolicy;
use App\Policies\Game\GameItemPolicy;
use App\Policies\Note\NotePolicy;
use App\Policies\Thing\AreaPolicy;
use App\Policies\Thing\ItemCategoryPolicy;
use App\Policies\Thing\RoomPolicy;
use App\Policies\Thing\SpotPolicy;
use App\Policies\Thing\ThingItemPolicy;
use App\Policies\Word\WordPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class PolicyServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        ChatRoom::class => ChatRoomPolicy::class,
        ChatMessage::class => ChatMessagePolicy::class,
        ChatRoomUser::class => ChatModerationPolicy::class,
        GameCharacter::class => GameCharacterPolicy::class,
        GameItem::class => GameItemPolicy::class,
        Note::class => NotePolicy::class,
        Area::class => AreaPolicy::class,
        Room::class => RoomPolicy::class,
        Spot::class => SpotPolicy::class,
        ItemCategory::class => ItemCategoryPolicy::class,
        Item::class => ThingItemPolicy::class,
        Word::class => WordPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
