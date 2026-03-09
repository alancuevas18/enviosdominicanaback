<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\CreateStoreRequest;
use App\Models\Store;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StoreController extends ApiController
{
    /**
     * List all stores filtered by branch context.
     *
     * GET /api/v1/stores
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Store::class);

        $cacheKey = implode(':', [
            'user',
            (string) $request->user()?->id,
            'branch_filter',
            (string) $request->input('branch_id', 'all'),
            'with_inactive',
            (string) (int) $request->boolean('with_inactive', false),
            'active_filter',
            (string) $request->input('active', ''),
            'search',
            md5((string) $request->input('search', '')),
            'page',
            (string) (int) $request->input('page', 1),
        ]);

        $ttlSeconds = max(10, (int) env('LIST_CACHE_TTL_SECONDS', 60));

        $stores = ApiCache::remember('stores-index', $cacheKey, now()->addSeconds($ttlSeconds), function () use ($request) {
            return Store::query()
                ->with(['branch:id,name,city', 'user:id,name,email'])
                ->when($request->boolean('with_inactive', false), fn($q) => $q, function ($q) use ($request): void {
                    // No with_inactive: respect explicit active param or default to active only
                    if ($request->filled('active')) {
                        $q->where('active', (bool) (int) $request->input('active'));
                    } else {
                        $q->where('active', true);
                    }
                })
                ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', (int) $request->input('branch_id')))
                ->when($request->filled('search'), function ($q) use ($request): void {
                    $search = $request->input('search');
                    $q->where(function ($sub) use ($search): void {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(20);
        });

        return $this->paginated($stores, 'Tiendas obtenidas exitosamente.');
    }

    /**
     * Create a new store manually (without an access request).
     *
     * POST /api/v1/stores
     */
    public function store(CreateStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Store::class);

        $tempPassword = '';

        $result = DB::transaction(function () use ($request, &$tempPassword): Store {
            $tempPassword = Str::password(12, symbols: false);

            /** @var User $user */
            $user = User::create([
                'name' => $request->input('user_name'),
                'email' => $request->input('user_email'),
                'password' => $tempPassword,
                'phone' => $request->input('user_phone'),
                'active' => true,
                'email_verified_at' => now(),
            ]);

            $user->assignRole('store');

            $storeData = $request->safe()->except(['user_name', 'user_email', 'user_phone']);

            return Store::create(array_merge($storeData, ['user_id' => $user->id]));
        });

        ApiCache::bumpMany(['stores-index', 'branches-index']);

        return $this->created([
            'store'         => $result->load(['branch:id,name', 'user:id,name,email']),
            'temp_password' => $tempPassword,
        ], 'Tienda creada exitosamente. Comparte la contraseña temporal con el usuario.');
    }

    /**
     * Show store details with shipment history and stats.
     *
     * GET /api/v1/stores/{id}
     */
    public function show(Store $store): JsonResponse
    {
        $this->authorize('view', $store);

        $store->load(['branch:id,name,city', 'user:id,name,email,phone']);

        // Optimize: Single query with aggregates instead of 4 separate queries
        $stats = [
            'total_shipments' => $store->shipments()->count(),
            'delivered' => $store->shipments()->where('status', 'delivered')->count(),
            'pending' => $store->shipments()->where('status', 'pending')->count(),
            'not_delivered' => $store->shipments()->where('status', 'not_delivered')->count(),
        ];

        $recentShipments = $store->shipments()
            ->with(['courier:id,name', 'stops'])
            ->latest()
            ->limit(10)
            ->get();

        return $this->success([
            'store' => $store,
            'stats' => $stats,
            'recent_shipments' => $recentShipments,
        ], 'Tienda obtenida exitosamente.');
    }

    /**
     * Update store data.
     *
     * PUT /api/v1/stores/{id}
     */
    public function update(Request $request, Store $store): JsonResponse
    {
        $this->authorize('update', $store);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'rnc' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'sector' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'default_notification_message' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'maps_url' => ['nullable', 'string', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        // Only root/admin can activate or deactivate another store.
        // Store users can only change their own store's active status.
        if ($user->hasRole('store') && $user->store?->id !== $store->id) {
            unset($data['active']);
        }

        $store->update($data);
        ApiCache::bumpMany(['stores-index', 'branches-index']);

        return $this->success($store, 'Tienda actualizada exitosamente.');
    }

    /**
     * Deactivate a store.
     *
     * DELETE /api/v1/stores/{id}
     */
    public function destroy(Store $store): JsonResponse
    {
        $this->authorize('delete', $store);

        $store->update(['active' => false]);
        $store->delete(); // soft-delete — preserves shipment/rating history
        ApiCache::bumpMany(['stores-index', 'branches-index']);

        return $this->success(null, 'Tienda eliminada exitosamente.');
    }
}
