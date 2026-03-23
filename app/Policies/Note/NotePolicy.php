<?php

namespace App\Policies\Note;

use App\Models\Note\Note;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any notes.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the note.
     */
    public function view(User $user, Note $note): bool
    {
        if ($note->is_public) {
            return true;
        }

        return $note->user_id === $user->id;
    }

    /**
     * Determine whether the user can create notes.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the note.
     */
    public function update(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the note.
     */
    public function delete(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine whether the user can share the note.
     */
    public function share(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }

    /**
     * Determine whether the user can publish the note.
     */
    public function publish(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
