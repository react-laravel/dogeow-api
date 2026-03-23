<?php

namespace App\Policies\Thing;

use App\Models\Thing\Room;
use App\Models\Thing\Spot;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpotPolicy
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
    public function view(User $user, Spot $spot): bool
    {
        return $user->id === $spot->user_id;
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
    public function update(User $user, Spot $spot): bool
    {
        return $user->id === $spot->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Spot $spot): bool
    {
        return $user->id === $spot->user_id;
    }

    /**
     * Determine whether the user can create a spot for the given room.
     */
    public function createForRoom(User $user, Room $room): bool
    {
        return $user->id === $room->user_id;
    }

    /**
     * Determine whether the user can move the spot to a different room.
     */
    public function moveToRoom(User $user, Spot $spot, Room $room): bool
    {
        return $user->id === $spot->user_id && $user->id === $room->user_id;
    }
}
