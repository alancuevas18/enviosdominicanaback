<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\UpdateStopStatusRequest;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Models\Stop;
use App\Events\ShipmentStatusUpdatedEvent;
use App\Notifications\ShipmentDeliveredNotification;
use App\Notifications\ShipmentNotDeliveredNotification;
use App\Notifications\ShipmentPickedUpNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StopController extends ApiController
{
    /**
     * Change the status of a stop (courier only).
     *
     * PATCH /api/v1/stops/{id}/status
     */
    public function updateStatus(UpdateStopStatusRequest $request, Stop $stop): JsonResponse
    {
        $this->authorize('updateStatus', $stop);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $action = $request->input('action');

        $shipment = $stop->shipment;
        $oldStatus = $shipment->status;

        match ($action) {
            'complete_pickup' => $this->handleCompletePickup($stop, $shipment, $user),
            'complete_delivery' => $this->handleCompleteDelivery($stop, $shipment, $user, $request),
            'fail' => $this->handleFail($stop, $shipment, $user, $request),
            default => null,
        };

        // Broadcast status change if the shipment status was updated
        $shipment->refresh();
        if ($shipment->status !== $oldStatus) {
            ShipmentStatusUpdatedEvent::dispatch($shipment, $oldStatus, $shipment->status);
        }

        return $this->success(
            $stop->fresh()->load(['shipment:id,status']),
            'Estado de la parada actualizado exitosamente.'
        );
    }

    /**
     * Mark a pickup stop as completed.
     */
    private function handleCompletePickup(Stop $stop, Shipment $shipment, \App\Models\User $user): void
    {
        DB::transaction(function () use ($stop, $shipment, $user): void {
            $stop->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            // Check if all pickups for this shipment are completed
            $allPickupsDone = $shipment->stops()
                ->where('type', 'pickup')
                ->where('status', '!=', 'completed')
                ->doesntExist();

            if ($allPickupsDone) {
                $previousStatus = $shipment->status;
                $shipment->update(['status' => 'picked_up']);

                ShipmentStatusHistory::create([
                    'shipment_id' => $shipment->id,
                    'from_status' => $previousStatus,
                    'to_status' => 'picked_up',
                    'changed_by' => $user->id,
                    'role_at_time' => 'courier',
                ]);

                // Notify store and admin
                $shipment->store->user?->notify(new ShipmentPickedUpNotification($shipment));
                foreach ($shipment->branch->admins as $admin) {
                    $admin->notify(new ShipmentPickedUpNotification($shipment));
                }
            }
        });
    }

    /**
     * Mark a delivery stop as completed.
     * Requires a photo to already be uploaded.
     */
    private function handleCompleteDelivery(Stop $stop, Shipment $shipment, \App\Models\User $user, UpdateStopStatusRequest $request): void
    {
        // Must have a photo
        if (empty($stop->photo_s3_key)) {
            abort(422, 'Debes subir la foto de entrega antes de completar esta parada.');
        }

        DB::transaction(function () use ($stop, $shipment, $user, $request): void {
            $stop->update([
                'status' => 'completed',
                'completed_at' => now(),
                'amount_collected' => $request->input('amount_collected'),
            ]);

            $previousStatus = $shipment->status;
            $shipment->update(['status' => 'delivered']);

            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => $previousStatus,
                'to_status' => 'delivered',
                'changed_by' => $user->id,
                'role_at_time' => 'courier',
            ]);

            // Build the notification message
            $message = $this->buildDeliveryMessage($shipment);

            // Notify the store
            $shipment->store->user?->notify(new ShipmentDeliveredNotification($shipment, $message));
        });
    }

    /**
     * Mark a stop as failed (not delivered).
     */
    private function handleFail(Stop $stop, Shipment $shipment, \App\Models\User $user, UpdateStopStatusRequest $request): void
    {
        DB::transaction(function () use ($stop, $shipment, $user, $request): void {
            $failReason = $request->input('fail_reason');

            $stop->update([
                'status' => 'failed',
                'fail_reason' => $failReason,
                'completed_at' => now(),
            ]);

            $previousStatus = $shipment->status;
            $shipment->update(['status' => 'not_delivered']);

            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => $previousStatus,
                'to_status' => 'not_delivered',
                'changed_by' => $user->id,
                'role_at_time' => 'courier',
                'notes' => $failReason,
            ]);

            // Notify admin and root
            foreach ($shipment->branch->admins as $admin) {
                $admin->notify(new ShipmentNotDeliveredNotification($shipment, $failReason));
            }
            foreach (\App\Models\User::role('root')->get() as $root) {
                $root->notify(new ShipmentNotDeliveredNotification($shipment, $failReason));
            }
        });
    }

    /**
     * Build the delivery notification message using template variable substitution.
     */
    private function buildDeliveryMessage(Shipment $shipment): string
    {
        $message = $shipment->custom_notification_message
            ?? $shipment->store->default_notification_message
            ?? 'Tu pedido de {store_name} fue entregado exitosamente a {recipient_name}. Orden #{order_id}.';

        return str_replace(
            ['{recipient_name}', '{store_name}', '{courier_name}', '{order_id}'],
            [
                $shipment->recipient_name,
                $shipment->store->name,
                $shipment->courier?->name ?? 'N/A',
                (string) $shipment->id,
            ],
            $message
        );
    }
}
