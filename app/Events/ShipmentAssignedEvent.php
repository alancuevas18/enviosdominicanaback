<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Courier;
use App\Models\Shipment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a shipment is assigned to a courier.
 *
 * Broadcast to:
 *   - private-courier.{courierId}  → courier gets real-time alert (in addition to WebPush)
 *   - private-store.{storeId}      → store sees courier name appear instantly
 *   - private-branch.{branchId}    → admin dashboard updates the row status
 */
class ShipmentAssignedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment,
        public readonly Courier $courier
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("courier.{$this->courier->id}"),
            new PrivateChannel("store.{$this->shipment->store_id}"),
            new PrivateChannel("branch.{$this->shipment->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.assigned';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'           => $this->shipment->id,
            'branch_id'    => $this->shipment->branch_id,
            'store_id'     => $this->shipment->store_id,
            'status'       => $this->shipment->status,
            'courier'      => [
                'id'    => $this->courier->id,
                'name'  => $this->courier->name,
                'phone' => $this->courier->phone,
            ],
            'assigned_at'  => now()->toIso8601String(),
        ];
    }
}
