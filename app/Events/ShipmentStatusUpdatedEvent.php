<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a stop status change causes the parent shipment status to change.
 * Covers: pending → picked_up → delivered | not_delivered
 *
 * Broadcast to:
 *   - private-shipment.{shipmentId} → fine-grained tracking view (store)
 *   - private-store.{storeId}       → store's order list refreshes the row
 *   - private-branch.{branchId}     → admin dashboard live update
 */
class ShipmentStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly string $fromStatus,
        public readonly string $toStatus
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("shipment.{$this->shipment->id}"),
            new PrivateChannel("store.{$this->shipment->store_id}"),
            new PrivateChannel("branch.{$this->shipment->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.status_updated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'          => $this->shipment->id,
            'branch_id'   => $this->shipment->branch_id,
            'store_id'    => $this->shipment->store_id,
            'from_status' => $this->fromStatus,
            'to_status'   => $this->toStatus,
            'updated_at'  => now()->toIso8601String(),
        ];
    }
}
