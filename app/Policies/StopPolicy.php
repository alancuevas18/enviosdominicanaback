<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Stop;
use App\Models\User;

class StopPolicy
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

    /**
     * Only the courier assigned to this stop's route can update its status.
     */
    public function updateStatus(User $user, Stop $stop): bool
    {
        if (! $user->hasRole('courier')) {
            return false;
        }

        $courier = $user->courier;

        if ($courier === null) {
            return false;
        }

        return $stop->routes()
            ->where('courier_id', $courier->id)
            ->exists();
    }

    /**
     * Courier can generate a presigned URL for their stops.
     */
    public function uploadPhoto(User $user, Stop $stop): bool
    {
        return $this->updateStatus($user, $stop);
    }
}
