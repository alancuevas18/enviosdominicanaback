<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Root can do everything.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('root')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $managedUser): bool
    {
        return false;
    }

    public function createAdmin(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $managedUser): bool
    {
        return false;
    }

    public function syncBranches(User $user, User $managedUser): bool
    {
        return false;
    }

    public function delete(User $user, User $managedUser): bool
    {
        return false; // root bypasses via before()
    }
}
