<?php

namespace App\Policies\Thing;

use App\Models\Thing\Item;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ThingItemPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any items.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the item.
     */
    public function view(User $user, Item $item): bool
    {
        if ($item->is_public) {
            return true;
        }

        return $item->user_id === $user->id;
    }

    /**
     * Determine whether the user can create items.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the item.
     */
    public function update(User $user, Item $item): bool
    {
        return $item->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the item.
     */
    public function delete(User $user, Item $item): bool
    {
        return $item->user_id === $user->id;
    }

    /**
     * Determine whether the user can share the item.
     */
    public function share(User $user, Item $item): bool
    {
        return $item->user_id === $user->id;
    }

    /**
     * Determine whether the user can archive the item.
     */
    public function archive(User $user, Item $item): bool
    {
        return $item->user_id === $user->id;
    }
}
