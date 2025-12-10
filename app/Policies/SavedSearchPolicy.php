<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SavedSearchPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any saved searches.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the saved search.
     */
    public function view(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can create saved searches.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the saved search.
     */
    public function update(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }

    /**
     * Determine whether the user can delete the saved search.
     */
    public function delete(User $user, SavedSearch $savedSearch): bool
    {
        return $user->id === $savedSearch->user_id;
    }
}
