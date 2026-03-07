<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\AssignShipmentRequest;
use App\Http\Requests\Api\V1\CreateShipmentRequest;
use App\Http\Requests\Api\V1\RateShipmentRequest;
use App\Http\Requests\Api\V1\UpdateShipmentRequest;
use App\Models\Courier;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\ShipmentRating;
use App\Models\ShipmentStatusHistory;
use App\Models\Stop;
use App\Events\ShipmentAssignedEvent;
use App\Events\ShipmentCreatedEvent;
use App\Notifications\NewShipmentAssignedPushNotification;
use App\Notifications\ShipmentAssignedNotification;
use App\Notifications\ShipmentCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShipmentController extends ApiController
{
    /**
     * List shipments based on user role.
     *
     * GET /api/v1/shipments
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shipment::class);

        $request->validate([
            'status' => ['nullable', 'string', 'in:pending,assigned,picked_up,in_route,delivered,not_delivered'],
            'from'   => ['nullable', 'date_format:Y-m-d'],
            'to'     => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Shipment::query()
            ->with(['store:id,name', 'courier:id,name', 'branch:id,name'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->input('to')));

        // Store only sees their own shipments
        if ($user->hasRole('store')) {
            $storeId = $user->store?->id;
            $query->where('store_id', $storeId);
        }

        // Courier only sees assigned shipments
        if ($user->hasRole('courier')) {
            $courierId = $user->courier?->id;
            $query->where('courier_id', $courierId);
        }

        return $this->paginated($query->latest()->paginate(20), 'Órdenes obtenidas exitosamente.');
    }

    /**
     * Create a new shipment and auto-generate pickup + delivery stops.
     *
     * POST /api/v1/shipments
     */
    public function store(CreateShipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Shipment::class);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $shipment = DB::transaction(function () use ($request, $user): Shipment {
            // Determine branch_id and store_id
            if ($user->hasRole('store')) {
                $store = $user->store;
                $branchId = $store->branch_id;
                $storeId = $store->id;
            } else {
                // Admin/root must provide store_id
                $request->validate(['store_id' => ['required', 'exists:stores,id']]);
                $storeId = (int) $request->input('store_id');
                $store = \App\Models\Store::withoutGlobalScopes()->findOrFail($storeId);
                $branchId = $store->branch_id;

                // Security: admin can only create shipments for stores within their active branch
                if ($user->hasRole('admin')) {
                    $activeBranchId = $user->getActiveBranchId();
                    if ($activeBranchId !== null && $activeBranchId !== $branchId) {
                        abort(403, 'La tienda no pertenece a tu sucursal activa.');
                    }
                }
            }

            // 1. Create shipment
            $shipment = Shipment::create(array_merge($request->safe()->except(['pickup_address', 'store_id']), [
                'store_id' => $storeId,
                'branch_id' => $branchId,
                'status' => 'pending',
            ]));

            // 2. Create pickup stop (store address)
            Stop::create([
                'shipment_id' => $shipment->id,
                'type' => 'pickup',
                'suggested_order' => 1,
                'status' => 'pending',
            ]);

            // 3. Create delivery stop (recipient address)
            Stop::create([
                'shipment_id' => $shipment->id,
                'type' => 'delivery',
                'suggested_order' => 2,
                'status' => 'pending',
            ]);

            // 4. Record status history: null → pending
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => null,
                'to_status' => 'pending',
                'changed_by' => $user->id,
                'role_at_time' => $user->getRoleNames()->first() ?? 'unknown',
            ]);

            // 5. Notify the admin(s) of the branch
            $branchAdmins = $store->branch->admins;
            foreach ($branchAdmins as $admin) {
                $admin->notify(new ShipmentCreatedNotification($shipment));
            }

            return $shipment;
        });

        // Broadcast: notify admin dashboard in real-time
        ShipmentCreatedEvent::dispatch($shipment);

        return $this->created(
            $shipment->load(['store:id,name', 'stops', 'branch:id,name']),
            'Orden creada exitosamente.'
        );
    }

    /**
     * Show shipment details: stops, status history, rating, photo.
     *
     * GET /api/v1/shipments/{id}
     */
    public function show(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $shipment->load([
            'store:id,name,phone,whatsapp',
            'courier:id,name,phone',
            'branch:id,name',
            'stops',
            'statusHistory.changedBy:id,name',
            'rating.store:id,name',
        ]);

        return $this->success($shipment, 'Orden obtenida exitosamente.');
    }

    /**
     * Update a shipment (only if status is pending).
     *
     * PUT /api/v1/shipments/{id}
     */
    public function update(UpdateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $shipment->update($request->validated());

        return $this->success($shipment->fresh(), 'Orden actualizada exitosamente.');
    }

    /**
     * Get the status change timeline of a shipment.
     *
     * GET /api/v1/shipments/{id}/history
     */
    public function history(Shipment $shipment): JsonResponse
    {
        $this->authorize('view', $shipment);

        $history = $shipment->statusHistory()
            ->with(['changedBy:id,name'])
            ->orderBy('created_at')
            ->get();

        return $this->success($history, 'Historial de estados obtenido exitosamente.');
    }

    /**
     * Assign a courier to a shipment and create/update the day's route.
     *
     * POST /api/v1/shipments/{id}/assign
     */
    public function assign(AssignShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('assign', $shipment);

        /** @var \App\Models\User $user */
        $user = $request->user();

        DB::transaction(function () use ($request, $shipment, $user): void {
            $courierId = (int) $request->input('courier_id');
            $stopOrder = $request->input('stop_order', []);

            /** @var Courier $courier */
            $courier = Courier::withoutGlobalScopes()->findOrFail($courierId);

            $previousStatus = $shipment->status;

            // Update shipment
            $shipment->update([
                'courier_id' => $courierId,
                'status' => 'assigned',
            ]);

            // Record status history
            ShipmentStatusHistory::create([
                'shipment_id' => $shipment->id,
                'from_status' => $previousStatus,
                'to_status' => 'assigned',
                'changed_by' => $user->id,
                'role_at_time' => $user->getRoleNames()->first() ?? 'admin',
                'notes' => "Asignado al mensajero: {$courier->name}",
            ]);

            // Find or create today's route for this courier
            $route = Route::firstOrCreate(
                ['courier_id' => $courierId, 'date' => today()->toDateString()],
                ['branch_id' => $shipment->branch_id, 'status' => 'open']
            );

            // Attach stops to route with their order
            foreach ($stopOrder as $order => $stopId) {
                $route->stops()->syncWithoutDetaching([
                    $stopId => ['suggested_order' => $order + 1],
                ]);

                // Also update the stop's suggested_order
                Stop::where('id', $stopId)->update(['suggested_order' => $order + 1]);
            }

            // Notify the courier via push notification
            if ($courier->user) {
                $courier->user->notify(new NewShipmentAssignedPushNotification($shipment));
            }

            // Notify the store
            $shipment->store->user?->notify(new ShipmentAssignedNotification($shipment));
        });

        // Broadcast: real-time update to courier, store, and admin dashboard
        $assignedCourier = Courier::withoutGlobalScopes()->find((int) $request->input('courier_id'));
        if ($assignedCourier) {
            ShipmentAssignedEvent::dispatch($shipment->fresh(), $assignedCourier);
        }

        return $this->success(
            $shipment->fresh()->load(['courier:id,name', 'stops']),
            'Orden asignada exitosamente.'
        );
    }

    /**
     * Rate a delivered shipment. Only the owning store can rate.
     *
     * POST /api/v1/shipments/{id}/rate
     */
    public function rate(RateShipmentRequest $request, Shipment $shipment): JsonResponse
    {
        $this->authorize('rate', $shipment);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $store = $user->store;

        $rating = ShipmentRating::create([
            'shipment_id' => $shipment->id,
            'store_id' => $store->id,
            'courier_id' => $shipment->courier_id,
            'score' => $request->input('score'),
            'comment' => $request->input('comment'),
        ]);

        // Recalculate the courier's average rating
        if ($shipment->courier) {
            $shipment->courier->recalculateRating();
        }

        // Notify admin
        $admins = $shipment->branch->admins;
        foreach ($admins as $admin) {
            $admin->notify(new \App\Notifications\CourierRatingReceivedNotification($rating));
        }

        return $this->created($rating, 'Calificación registrada exitosamente.');
    }
}
