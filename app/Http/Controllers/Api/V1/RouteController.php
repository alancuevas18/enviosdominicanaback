<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ReorderRouteRequest;
use App\Models\Route;
use App\Models\Stop;
use App\Events\RouteReorderedEvent;
use App\Notifications\RouteUpdatedPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RouteController extends ApiController
{
    /**
     * Courier: Get today's route with all stops ordered.
     *
     * GET /api/v1/routes/today
     */
    public function today(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $courier = $user->courier;

        if ($courier === null) {
            return $this->error('No tienes un perfil de mensajero asignado.', 404);
        }

        $route = Route::withoutGlobalScopes()
            ->where('courier_id', $courier->id)
            ->where('date', today()->toDateString())
            ->with([
                'courier:id,name,phone',
                'stops' => function ($q): void {
                    $q->orderBy('route_stops.suggested_order')
                        ->with([
                            'shipment' => function ($sq): void {
                                $sq->with(['store:id,name,phone,whatsapp', 'branch:id,name'])
                                    ->select(['id', 'store_id', 'branch_id', 'courier_id', 'recipient_name', 'recipient_phone', 'address', 'maps_url', 'sector', 'amount_to_collect', 'payment_method', 'payer', 'status', 'notes', 'custom_notification_message']);
                            },
                        ]);
                },
            ])
            ->first();

        if ($route === null) {
            return $this->success(null, 'No tienes una ruta asignada para hoy.');
        }

        return $this->success($route, 'Ruta del día obtenida exitosamente.');
    }

    /**
     * Admin: List today's routes for the active branch.
     *
     * GET /api/v1/routes
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Route::query()
            ->with([
                'courier:id,name,phone,rating_avg',
                'branch:id,name',
            ])
            ->withCount(['stops'])
            ->when($request->filled('date'), fn($q) => $q->where('date', $request->input('date')))
            ->when(! $request->filled('date'), fn($q) => $q->where('date', today()->toDateString()))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->latest();

        // Root can optionally filter by branch
        if ($user->hasRole('root') && $request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        return $this->paginated($query->paginate(20), 'Rutas obtenidas exitosamente.');
    }

    /**
     * Admin: Reorder the stops of a route.
     *
     * PUT /api/v1/routes/{id}/reorder
     */
    public function reorder(ReorderRouteRequest $request, Route $route): JsonResponse
    {
        DB::transaction(function () use ($request, $route): void {
            $stopOrder = $request->input('stop_order', []);

            foreach ($stopOrder as $order => $stopId) {
                // Update route_stops pivot table
                $route->routeStops()
                    ->where('stop_id', $stopId)
                    ->update(['suggested_order' => $order + 1]);

                // Also update the stop itself
                Stop::where('id', $stopId)->update(['suggested_order' => $order + 1]);
            }
        });

        // Notify the courier via push notification
        if ($route->courier?->user) {
            $route->courier->user->notify(new RouteUpdatedPushNotification($route));
        }

        // Broadcast: courier app reflects new stop order in real-time
        RouteReorderedEvent::dispatch($route->fresh());

        return $this->success(
            $route->fresh()->load(['stops' => fn($q) => $q->orderBy('route_stops.suggested_order')]),
            'Ruta reordenada exitosamente.'
        );
    }
}
