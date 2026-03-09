<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StoreAccessRequest;
use App\Models\User;

class StoreAccessRequestPolicy
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

    public function view(User $user, StoreAccessRequest $request): bool
    {
        return false;
    }

    public function approve(User $user, StoreAccessRequest $request): bool
    {
        return false;
    }

    public function reject(User $user, StoreAccessRequest $request): bool
    {
        return false;
    }
}
