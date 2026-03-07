<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies the store owner when their shipment is delivered.
 * The message supports variable substitution: {recipient_name}, {store_name}, {courier_name}, {order_id}.
 * Channel: database.
 */
class ShipmentDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment,
        private readonly string $message
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
            'type' => 'shipment_delivered',
            'title' => 'Orden entregada',
            'message' => $this->message,
            'shipment_id' => $this->shipment->id,
            'courier_name' => $this->shipment->courier?->name,
            'recipient_name' => $this->shipment->recipient_name,
        ];
    }
}
