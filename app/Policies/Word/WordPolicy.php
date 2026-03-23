<?php

namespace App\Policies\Word;

use App\Models\User;
use App\Models\Word\Word;
use Illuminate\Auth\Access\HandlesAuthorization;

class WordPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any words.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the word.
     */
    public function view(User $user, Word $word): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create words.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the word.
     */
    public function update(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the word.
     */
    public function delete(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }

    /**
     * Determine whether the user can review the word.
     */
    public function review(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }

    /**
     * Determine whether the user can mark word as learned.
     */
    public function markLearned(User $user, Word $word): bool
    {
        return $word->user_id === $user->id;
    }
}
