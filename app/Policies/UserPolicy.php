<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\AuthorizesAdmins;

class UserPolicy
{
    use AuthorizesAdmins;

    public function viewAny(User $user): bool
    {
        return $this->allowsAdmin($user);
    }

    public function view(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return true;
        }

        return $this->allowsAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->allowsAdmin($user);
    }

    public function update(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return true;
        }

        return $this->allowsAdmin($user);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return false;
        }

        return $this->allowsAdmin($user);
    }

    public function suspend(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return false;
        }

        return $this->allowsAdmin($user);
    }

    public function forcePasswordRotation(User $user, User $model): bool
    {
        return $this->allowsAdmin($user);
    }

    public function sendPasswordResetLink(User $user, User $model): bool
    {
        return $this->allowsAdmin($user);
    }
}
