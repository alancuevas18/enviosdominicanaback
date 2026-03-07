<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
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
     * Determine whether the user can view any shipments.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['root', 'admin', 'store', 'courier']);
    }

    /**
     * Determine whether the user can view the shipment.
     * Admin: must be in the same branch. Store: must own it. Courier: assigned.
     */
    public function view(User $user, Shipment $shipment): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $shipment->branch_id;
        }

        if ($user->hasRole('store')) {
            return $user->store?->id === $shipment->store_id;
        }

        if ($user->hasRole('courier')) {
            return $user->courier?->id === $shipment->courier_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create shipments.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['root', 'admin', 'store']);
    }

    /**
     * Determine whether the user can update the shipment.
     * Only allowed if status is 'pending'.
     */
    public function update(User $user, Shipment $shipment): bool
    {
        if ($shipment->status !== 'pending') {
            return false;
        }

        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $shipment->branch_id;
        }

        if ($user->hasRole('store')) {
            return $user->store?->id === $shipment->store_id;
        }

        return false;
    }

    /**
     * Determine whether the user can assign a courier to the shipment.
     */
    public function assign(User $user, Shipment $shipment): bool
    {
        if ($user->hasRole('admin')) {
            return $user->getActiveBranchId() === $shipment->branch_id;
        }

        return false;
    }

    /**
     * Determine whether the user (store) can rate the shipment.
     * Only if: delivered, no prior rating, and this store owns it.
     */
    public function rate(User $user, Shipment $shipment): bool
    {
        if (! $user->hasRole('store')) {
            return false;
        }

        $store = $user->store;

        if ($store === null || $store->id !== $shipment->store_id) {
            return false;
        }

        if ($shipment->status !== 'delivered') {
            return false;
        }

        return $shipment->rating === null;
    }
}
