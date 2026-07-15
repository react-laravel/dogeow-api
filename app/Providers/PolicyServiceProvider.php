<?php

namespace App\Providers;

use App\Models\Note\Note;
use App\Models\Thing\Area;
use App\Models\Thing\Item;
use App\Models\Thing\ItemCategory;
use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\Word\Word;
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
