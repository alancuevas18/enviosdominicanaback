<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Sends the approved store their temporary credentials via email.
 * Channel: mail.
 */
class StoreApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly User $user,
        private readonly string $temporaryPassword
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('¡Tu solicitud fue aprobada! — Sistema Mensajería')
            ->greeting("¡Hola, {$this->user->name}!")
            ->line('Nos complace informarte que tu solicitud de acceso al Sistema de Mensajería ha sido **aprobada**.')
            ->line('Tus credenciales de acceso son:')
            ->line("**Email:** {$this->user->email}")
            ->line("**Contraseña temporal:** {$this->temporaryPassword}")
            ->action('Iniciar sesión', config('app.url') . '/login')
            ->line('Por seguridad, te recomendamos cambiar tu contraseña al iniciar sesión por primera vez.')
            ->line('Si tienes alguna pregunta, no dudes en contactarnos.')
            ->salutation('El equipo de Sistema Mensajería');
    }
}
