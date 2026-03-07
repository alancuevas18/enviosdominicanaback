<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends ApiController
{
    /**
     * Register a Web Push subscription from the service worker.
     * Only couriers can subscribe.
     *
     * POST /api/v1/push/subscribe
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'public_key' => ['required', 'string'],
            'auth_token' => ['required', 'string'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Upsert: update if endpoint exists, otherwise create
        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $request->input('endpoint')],
            [
                'user_id' => $user->id,
                'public_key' => $request->input('public_key'),
                'auth_token' => $request->input('auth_token'),
            ]
        );

        return $this->success($subscription, 'Suscripción push registrada exitosamente.');
    }

    /**
     * Remove the push subscription for the current device.
     *
     * DELETE /api/v1/push/unsubscribe
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => ['required', 'string'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $deleted = PushSubscription::where('user_id', $user->id)
            ->where('endpoint', $request->input('endpoint'))
            ->delete();

        if ($deleted === 0) {
            return $this->notFound('Suscripción no encontrada.');
        }

        return $this->success(null, 'Suscripción push eliminada exitosamente.');
    }
}
