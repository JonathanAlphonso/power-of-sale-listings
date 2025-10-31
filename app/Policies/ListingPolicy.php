<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;

class ListingPolicy
{
    /**
     * Run before any other authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Listing $listing): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Listing $listing): bool
    {
        return $user->isAdmin();
    }

    public function suppress(User $user, Listing $listing): bool
    {
        return $user->isAdmin();
    }

    public function unsuppress(User $user, Listing $listing): bool
    {
        return $user->isAdmin();
    }
}
