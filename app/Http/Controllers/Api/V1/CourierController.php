<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\CreateCourierRequest;
use App\Models\Courier;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CourierController extends ApiController
{
    /**
     * List all couriers filtered by branch context.
     *
     * GET /api/v1/couriers
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Courier::class);

        $cacheKey = implode(':', [
            'user',
            (string) $request->user()?->id,
            'branch',
            (string) ($request->user()?->getActiveBranchId() ?? 'all'),
            'active_only',
            (string) (int) $request->boolean('active_only', true),
            'search',
            md5((string) $request->input('search', '')),
            'page',
            (string) (int) $request->input('page', 1),
        ]);

        $ttlSeconds = max(10, (int) env('LIST_CACHE_TTL_SECONDS', 60));

        $couriers = ApiCache::remember('couriers-index', $cacheKey, now()->addSeconds($ttlSeconds), function () use ($request) {
            return Courier::query()
                ->with(['branch:id,name', 'user:id,name,email'])
                ->when($request->boolean('active_only', true), fn($q) => $q->where('active', true))
                ->when($request->filled('search'), function ($q) use ($request): void {
                    $search = $request->input('search');
                    $q->where(function ($sub) use ($search): void {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(20);
        });

        return $this->paginated($couriers, 'Mensajeros obtenidos exitosamente.');
    }

    /**
     * Create a new courier and their associated user account.
     *
     * POST /api/v1/couriers
     */
    public function store(CreateCourierRequest $request): JsonResponse
    {
        $this->authorize('create', Courier::class);

        $tempPassword = '';

        $result = DB::transaction(function () use ($request, &$tempPassword): Courier {
            $tempPassword = Str::password(12, symbols: false);

            /** @var User $user */
            $user = User::create([
                'name' => $request->input('user_name'),
                'email' => $request->input('user_email'),
                'password' => $tempPassword,
                'active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('courier');

            return Courier::create([
                'user_id' => $user->id,
                'branch_id' => $request->input('branch_id'),
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'vehicle' => $request->input('vehicle'),
            ]);
        });

        ApiCache::bumpMany(['couriers-index', 'branches-index']);

        return $this->created([
            'courier'       => $result->load(['branch:id,name', 'user:id,name,email']),
            'temp_password' => $tempPassword,
        ], 'Mensajero creado exitosamente. Comparte la contraseña temporal con el mensajero.');
    }

    /**
     * Show courier details with rating and history.
     *
     * GET /api/v1/couriers/{id}
     */
    public function show(Courier $courier): JsonResponse
    {
        $this->authorize('view', $courier);

        $courier->load(['branch:id,name', 'user:id,name,email']);

        $stats = [
            'total_deliveries' => $courier->shipments()->where('status', 'delivered')->count(),
            'total_assigned' => $courier->shipments()->count(),
            'rating_avg' => $courier->rating_avg,
            'rating_count' => $courier->rating_count,
        ];

        $recentRatings = $courier->ratings()
            ->with(['store:id,name', 'shipment:id,recipient_name'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'courier' => $courier,
            'stats' => $stats,
            'recent_ratings' => $recentRatings,
        ], 'Mensajero obtenido exitosamente.');
    }

    /**
     * Update courier data.
     *
     * PUT /api/v1/couriers/{id}
     */
    public function update(Request $request, Courier $courier): JsonResponse
    {
        $this->authorize('update', $courier);

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $courier->update($request->validated());
        ApiCache::bumpMany(['couriers-index', 'branches-index']);

        return $this->success($courier, 'Mensajero actualizado exitosamente.');
    }

    /**
     * Deactivate a courier.
     *
     * DELETE /api/v1/couriers/{id}
     */
    public function destroy(Courier $courier): JsonResponse
    {
        $this->authorize('delete', $courier);

        $courier->update(['active' => false]);
        $courier->delete(); // soft-delete — preserves delivery/rating history
        ApiCache::bumpMany(['couriers-index', 'branches-index']);

        return $this->success(null, 'Mensajero eliminado exitosamente.');
    }

    /**
     * List all ratings for a courier.
     *
     * GET /api/v1/couriers/{id}/ratings
     */
    public function ratings(Courier $courier): JsonResponse
    {
        $this->authorize('view', $courier);

        $ratings = $courier->ratings()
            ->with(['store:id,name', 'shipment:id,recipient_name,created_at'])
            ->latest()
            ->paginate(20);

        return $this->paginated($ratings, 'Calificaciones obtenidas exitosamente.');
    }
}
