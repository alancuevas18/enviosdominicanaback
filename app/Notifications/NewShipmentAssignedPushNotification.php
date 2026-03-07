<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Channels\WebPushMessage;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Push notification sent to a courier when a new shipment is assigned.
 * Channel: webpush (for courier PWA).
 *
 * Configure VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in .env.
 */
class NewShipmentAssignedPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Shipment $shipment
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $location = $this->shipment->sector ?? $this->shipment->address;

        return (new WebPushMessage())
            ->title('Nueva orden asignada')
            ->icon('/icon-192x192.png')
            ->body("Tienes una nueva entrega para {$this->shipment->recipient_name} en {$location}.")
            ->action('Ver ruta', 'view_route')
            ->data([
                'shipment_id' => $this->shipment->id,
                'url' => '/ruta/hoy',
            ]);
    }
}
