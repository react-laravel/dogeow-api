<?php

namespace App\Policies\Thing;

use App\Models\Thing\ItemCategory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemCategoryPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ItemCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ItemCategory $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ItemCategory $category): bool
    {
        return $user->id === $category->user_id;
    }
}
