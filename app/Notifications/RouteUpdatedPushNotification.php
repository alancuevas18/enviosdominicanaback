<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Channels\WebPushChannel;
use App\Channels\WebPushMessage;
use App\Models\Route;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Push notification sent to a courier when their route is reordered.
 * Channel: webpush (for courier PWA).
 */
class RouteUpdatedPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Route $route
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title('Tu ruta fue actualizada')
            ->icon('/icon-192x192.png')
            ->body("El administrador actualizó el orden de tu ruta para hoy ({$this->route->date->format('d/m/Y')}). Revisa los cambios.")
            ->action('Ver ruta', 'view_route')
            ->data([
                'route_id' => $this->route->id,
                'url' => '/ruta/hoy',
            ]);
    }
}
