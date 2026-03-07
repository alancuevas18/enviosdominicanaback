<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

class StorePolicy
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
        return $user->hasAnyRole(['admin', 'store']);
    }

    public function view(User $user, Store $store): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $store->branch_id;
        }

        if ($user->hasRole('store')) {
            return $user->store?->id === $store->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Store $store): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $store->branch_id;
        }

        if ($user->hasRole('store')) {
            return $user->store?->id === $store->id;
        }

        return false;
    }

    public function delete(User $user, Store $store): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $store->branch_id;
        }

        return false;
    }
}
