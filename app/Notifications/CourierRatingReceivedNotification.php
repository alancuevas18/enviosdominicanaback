<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ShipmentRating;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies the admin when a store rates a courier.
 * Channel: database.
 */
class CourierRatingReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ShipmentRating $rating
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $stars = str_repeat('⭐', $this->rating->score);

        return [
            'type' => 'courier_rating_received',
            'title' => 'Nueva calificación de mensajero',
            'message' => "La tienda \"{$this->rating->store->name}\" calificó al mensajero {$this->rating->courier->name} con {$this->rating->score}/5 {$stars}.",
            'rating_id' => $this->rating->id,
            'courier_id' => $this->rating->courier_id,
            'courier_name' => $this->rating->courier->name,
            'store_name' => $this->rating->store->name,
            'score' => $this->rating->score,
            'comment' => $this->rating->comment,
        ];
    }
}
