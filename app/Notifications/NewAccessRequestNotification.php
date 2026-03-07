<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\StoreAccessRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies root users when a store submits an access request.
 * Channel: database.
 */
class NewAccessRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly StoreAccessRequest $accessRequest
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
            'type' => 'new_access_request',
            'title' => 'Nueva solicitud de acceso',
            'message' => "La tienda \"{$this->accessRequest->business_name}\" ha solicitado acceso al sistema.",
            'access_request_id' => $this->accessRequest->id,
            'business_name' => $this->accessRequest->business_name,
            'contact_name' => $this->accessRequest->contact_name,
            'email' => $this->accessRequest->email,
            'branch_id' => $this->accessRequest->branch_id,
        ];
    }
}
