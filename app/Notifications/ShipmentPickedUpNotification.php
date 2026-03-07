<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies the store and admin when the courier picks up the package.
 * Channel: database.
 */
class ShipmentPickedUpNotification extends Notification implements ShouldQueue
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
            'type' => 'shipment_picked_up',
            'title' => 'Paquete recogido',
            'message' => "El mensajero {$this->shipment->courier?->name} recogió el paquete de la orden #{$this->shipment->id} para {$this->shipment->recipient_name}.",
            'shipment_id' => $this->shipment->id,
            'courier_name' => $this->shipment->courier?->name,
            'recipient_name' => $this->shipment->recipient_name,
        ];
    }
}
