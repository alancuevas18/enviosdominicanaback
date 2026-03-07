<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Courier;
use App\Models\User;

class CourierPolicy
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
        return $user->hasRole('admin');
    }

    public function view(User $user, Courier $courier): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $courier->branch_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Courier $courier): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $courier->branch_id;
        }

        return false;
    }

    public function delete(User $user, Courier $courier): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $courier->branch_id;
        }

        return false;
    }
}
