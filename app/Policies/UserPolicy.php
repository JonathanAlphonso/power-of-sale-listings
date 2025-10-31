<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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

    public function view(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return true;
        }

        return $user->isAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return false;
        }

        return $user->isAdmin();
    }

    public function suspend(User $user, User $model): bool
    {
        if ($model->is($user)) {
            return false;
        }

        return $user->isAdmin();
    }

    public function forcePasswordRotation(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function sendPasswordResetLink(User $user, User $model): bool
    {
        return $user->isAdmin();
    }
}
