<?php

namespace App\Policies\Thing;

use App\Models\Thing\Area;
use App\Models\Thing\Room;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RoomPolicy
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
    public function view(User $user, Room $room): bool
    {
        return $user->id === $room->user_id;
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
    public function update(User $user, Room $room): bool
    {
        return $user->id === $room->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Room $room): bool
    {
        return $user->id === $room->user_id;
    }

    /**
     * Determine whether the user can create a room for the given area.
     */
    public function createForArea(User $user, Area $area): bool
    {
        return $user->id === $area->user_id;
    }

    /**
     * Determine whether the user can move the room to a different area.
     */
    public function moveToArea(User $user, Room $room, Area $area): bool
    {
        return $user->id === $room->user_id && $user->id === $area->user_id;
    }
}
