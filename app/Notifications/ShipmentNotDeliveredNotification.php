<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Alerts admin and root when a delivery fails.
 * Channel: database.
 */
class ShipmentNotDeliveredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment,
        private readonly string $failReason
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
            'type' => 'shipment_not_delivered',
            'title' => 'Orden no entregada',
            'message' => "La orden #{$this->shipment->id} para {$this->shipment->recipient_name} no pudo ser entregada. Motivo: {$this->failReason}",
            'shipment_id' => $this->shipment->id,
            'courier_name' => $this->shipment->courier?->name,
            'recipient_name' => $this->shipment->recipient_name,
            'fail_reason' => $this->failReason,
            'branch_id' => $this->shipment->branch_id,
        ];
    }
}
