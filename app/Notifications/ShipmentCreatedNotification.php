<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies admin(s) when a new shipment is created in their branch.
 * Channel: database.
 */
class ShipmentCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'shipment_created',
            'title' => 'Nueva orden creada',
            'message' => "La tienda \"{$this->shipment->store->name}\" creó una nueva orden para {$this->shipment->recipient_name}.",
            'shipment_id' => $this->shipment->id,
            'store_name' => $this->shipment->store->name,
            'recipient_name' => $this->shipment->recipient_name,
            'branch_id' => $this->shipment->branch_id,
        ];
    }
}
