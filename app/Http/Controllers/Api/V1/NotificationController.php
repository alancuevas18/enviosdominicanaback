<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends ApiController
{
    /**
     * List the user's notifications (unread first).
     *
     * GET /api/v1/notifications
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $notifications = $user->notifications()
            ->orderByRaw('read_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->paginate(20);

        return $this->paginated($notifications, 'Notificaciones obtenidas exitosamente.');
    }

    /**
     * Mark a single notification as read.
     *
     * PATCH /api/v1/notifications/{id}/read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            return $this->notFound('Notificación no encontrada.');
        }

        $notification->markAsRead();

        return $this->success(null, 'Notificación marcada como leída.');
    }

    /**
     * Mark all notifications as read.
     *
     * PATCH /api/v1/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'Todas las notificaciones marcadas como leídas.');
    }

    /**
     * Delete a notification.
     *
     * DELETE /api/v1/notifications/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        /** @var DatabaseNotification|null $notification */
        $notification = $user->notifications()->find($id);

        if ($notification === null) {
            return $this->notFound('Notificación no encontrada.');
        }

        $notification->delete();

        return $this->success(null, 'Notificación eliminada.');
    }
}
