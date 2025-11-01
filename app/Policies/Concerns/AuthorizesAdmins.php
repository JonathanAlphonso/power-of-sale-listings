<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait AuthorizesAdmins
{
    public function before(User $user): ?bool
    {
        if ($user->isSuspended()) {
            return false;
        }

        return null;
    }

    protected function allowsAdmin(User $user): bool
    {
        return $user->isAdmin();
    }
}
