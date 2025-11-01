<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\User;
use App\Policies\Concerns\AuthorizesAdmins;

class ListingPolicy
{
    use AuthorizesAdmins;

    public function viewAny(User $user): bool
    {
        return $this->allowsAdmin($user);
    }

    public function view(User $user, Listing $listing): bool
    {
        return $this->allowsAdmin($user);
    }

    public function update(User $user, Listing $listing): bool
    {
        return $this->allowsAdmin($user);
    }

    public function suppress(User $user, Listing $listing): bool
    {
        return $this->allowsAdmin($user);
    }

    public function unsuppress(User $user, Listing $listing): bool
    {
        return $this->allowsAdmin($user);
    }
}
