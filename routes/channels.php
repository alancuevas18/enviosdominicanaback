<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Courier;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| All channels are private (Sanctum-authenticated).
| Channel name             → Who can listen
| ─────────────────────────────────────────────────────────────────────────
| private-branch.{id}      → root (any), admin of that branch
| private-store.{id}       → root, admin of branch, store's own user
| private-courier.{id}     → root, admin of branch, courier's own user
| private-shipment.{id}    → root, admin of branch, store owner, assigned courier
|
*/

/**
 * Admin / root channel — receives new order alerts, shipment status changes,
 * and route updates for a specific branch.
 */
Broadcast::channel('branch.{branchId}', function (User $user, int $branchId): bool {
    if ($user->hasRole('root')) {
        return true;
    }

    if ($user->hasRole('admin')) {
        return $user->branches()->where('branches.id', $branchId)->exists();
    }

    return false;
});

/**
 * Store channel — receives assignment and status updates for their orders.
 */
Broadcast::channel('store.{storeId}', function (User $user, int $storeId): bool {
    if ($user->hasRole('root')) {
        return true;
    }

    $store = Store::withoutGlobalScopes()->find($storeId);
    if (! $store) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return $user->branches()->where('branches.id', $store->branch_id)->exists();
    }

    if ($user->hasRole('store')) {
        return $user->store?->id === $storeId;
    }

    return false;
});

/**
 * Courier channel — receives assignment push + route reorder events.
 */
Broadcast::channel('courier.{courierId}', function (User $user, int $courierId): bool {
    if ($user->hasRole('root')) {
        return true;
    }

    $courier = Courier::withoutGlobalScopes()->find($courierId);
    if (! $courier) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return $user->branches()->where('branches.id', $courier->branch_id)->exists();
    }

    if ($user->hasRole('courier')) {
        return $user->courier?->id === $courierId;
    }

    return false;
});

/**
 * Shipment channel — fine-grained real-time tracking for a single order.
 * Used by the store status-tracking view.
 */
Broadcast::channel('shipment.{shipmentId}', function (User $user, int $shipmentId): bool {
    if ($user->hasRole('root')) {
        return true;
    }

    $shipment = Shipment::withoutGlobalScopes()->find($shipmentId);
    if (! $shipment) {
        return false;
    }

    if ($user->hasRole('admin')) {
        return $user->branches()->where('branches.id', $shipment->branch_id)->exists();
    }

    if ($user->hasRole('store')) {
        return $shipment->store_id === $user->store?->id;
    }

    if ($user->hasRole('courier')) {
        return $shipment->courier_id === $user->courier?->id;
    }

    return false;
});
