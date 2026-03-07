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
 * Fired when a store creates a new shipment.
 * Broadcast to: private-branch.{branchId} so admins see it live on the dashboard.
 */
class ShipmentCreatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Shipment $shipment
    ) {}

    /** @return array<Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("branch.{$this->shipment->branch_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'shipment.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id'             => $this->shipment->id,
            'branch_id'      => $this->shipment->branch_id,
            'store_id'       => $this->shipment->store_id,
            'recipient_name' => $this->shipment->recipient_name,
            'address'        => $this->shipment->address,
            'sector'         => $this->shipment->sector,
            'status'         => $this->shipment->status,
            'amount_to_collect' => $this->shipment->amount_to_collect,
            'created_at'     => $this->shipment->created_at?->toIso8601String(),
        ];
    }
}
