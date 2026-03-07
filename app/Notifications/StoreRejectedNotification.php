<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifies the applicant that their store request was rejected.
 * Channel: mail.
 */
class StoreRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $contactName,
        private readonly string $email,
        private readonly string $reason
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('Actualización sobre tu solicitud — Sistema Mensajería')
            ->greeting("Hola, {$this->contactName}.")
            ->line('Lamentamos informarte que tu solicitud de acceso al Sistema de Mensajería no pudo ser aprobada en este momento.');

        if (! empty($this->reason)) {
            $message->line("**Motivo:** {$this->reason}");
        }

        return $message
            ->line('Si crees que esto es un error o deseas más información, puedes contactarnos directamente.')
            ->salutation('El equipo de Sistema Mensajería');
    }
}
