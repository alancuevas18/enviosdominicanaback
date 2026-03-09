<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\CreateAdminUserRequest;
use App\Http\Requests\Api\V1\SyncUserBranchesRequest;
use App\Http\Requests\Api\V1\UpdateManagedUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserController extends ApiController
{
    /**
     * List all application users for admin panel consumption.
     *
     * GET /api/v1/users
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $perPage = min(100, max(1, (int) $request->input('per_page', 20)));
        $role = (string) $request->input('role', '');
        $branchId = (int) $request->input('branch_id', 0);

        $cacheKey = implode(':', [
            'user',
            (string) $request->user()?->id,
            'role',
            $role,
            'active',
            $request->filled('active') ? (string) (int) $request->boolean('active') : 'all',
            'branch',
            $branchId > 0 ? (string) $branchId : 'all',
            'search',
            md5((string) $request->input('search', '')),
            'per_page',
            (string) $perPage,
            'page',
            (string) (int) $request->input('page', 1),
        ]);

        $ttlSeconds = max(10, (int) env('LIST_CACHE_TTL_SECONDS', 60));

        $users = ApiCache::remember('users-index', $cacheKey, now()->addSeconds($ttlSeconds), function () use ($request, $perPage, $role, $branchId) {
            return User::query()
                ->with([
                    'roles:id,name',
                    'branches:id,name,city',
                    'store:id,user_id,branch_id,name,active',
                    'courier:id,user_id,branch_id,name,active',
                ])
                ->when($role !== '', fn($query) => $query->role($role))
                ->when($request->filled('active'), fn($query) => $query->where('active', $request->boolean('active')))
                ->when($branchId > 0, function ($query) use ($branchId): void {
                    $query->where(function ($subQuery) use ($branchId): void {
                        $subQuery->whereHas('branches', fn($q) => $q->where('branches.id', $branchId))
                            ->orWhereHas('store', fn($q) => $q->where('stores.branch_id', $branchId))
                            ->orWhereHas('courier', fn($q) => $q->where('couriers.branch_id', $branchId));
                    });
                })
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $search = (string) $request->input('search');

                    $query->where(function ($subQuery) use ($search): void {
                        $subQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate($perPage);
        });

        $users->setCollection(
            $users->getCollection()->map(
                fn(User $user): array => UserResource::make($user)->resolve($request)
            )
        );

        return $this->paginated($users, 'Usuarios obtenidos exitosamente.');
    }

    /**
     * Show a single app user with role and profile relations.
     *
     * GET /api/v1/users/{id}
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load([
            'roles:id,name',
            'branches:id,name,city',
            'store:id,user_id,branch_id,name,active',
            'courier:id,user_id,branch_id,name,active',
        ]);

        return $this->success(UserResource::make($user)->resolve($request), 'Usuario obtenido exitosamente.');
    }

    /**
     * Create a new admin user and assign one or many branches.
     *
     * POST /api/v1/users/admins
     */
    public function storeAdmin(CreateAdminUserRequest $request): JsonResponse
    {
        $this->authorize('createAdmin', User::class);

        $tempPassword = '';

        $user = DB::transaction(function () use ($request, &$tempPassword): User {
            $tempPassword = Str::password(12, symbols: false);

            /** @var User $adminUser */
            $adminUser = User::create([
                'name' => (string) $request->input('name'),
                'email' => (string) $request->input('email'),
                'password' => $tempPassword,
                'phone' => $request->input('phone'),
                'active' => $request->boolean('active', true),
            ]);

            $adminUser->assignRole('admin');

            /** @var array<int, int|string> $branchIds */
            $branchIds = $request->validated('branch_ids');

            $adminUser->branches()->sync(array_map(fn($id): int => (int) $id, $branchIds));

            return $adminUser;
        });

        $user->load(['roles:id,name', 'branches:id,name,city']);

        ApiCache::bump('users-index');

        return $this->created([
            'user' => UserResource::make($user)->resolve($request),
            'temp_password' => $tempPassword,
        ], 'Admin creado exitosamente. Comparte la contraseña temporal con el usuario.');
    }

    /**
     * Update a managed user basic data and active status.
     *
     * PATCH /api/v1/users/{id}
     */
    public function update(UpdateManagedUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();

        if (array_key_exists('active', $validated) && ! (bool) $validated['active'] && $user->hasRole('root')) {
            $activeRootsCount = User::query()->role('root')->where('active', true)->count();

            if ($activeRootsCount <= 1) {
                return $this->error('No puedes desactivar el único usuario root activo.', 422);
            }
        }

        $user->update($validated);
        $user->load(['roles:id,name', 'branches:id,name,city', 'store:id,user_id,branch_id,name,active', 'courier:id,user_id,branch_id,name,active']);

        ApiCache::bump('users-index');

        return $this->success(UserResource::make($user)->resolve($request), 'Usuario actualizado exitosamente.');
    }

    /**
     * Sync branch assignments for an admin user.
     *
     * PUT /api/v1/users/{id}/branches
     */
    public function syncBranches(SyncUserBranchesRequest $request, User $user): JsonResponse
    {
        $this->authorize('syncBranches', $user);

        if (! $user->hasRole('admin')) {
            return $this->error('Solo se pueden asignar sucursales a usuarios con rol admin.', 422);
        }

        /** @var array<int, int|string> $branchIds */
        $branchIds = $request->validated('branch_ids');

        $user->branches()->sync(array_map(fn($id): int => (int) $id, $branchIds));
        $user->load(['roles:id,name', 'branches:id,name,city']);

        ApiCache::bump('users-index');

        return $this->success(UserResource::make($user)->resolve($request), 'Sucursales del admin actualizadas exitosamente.');
    }

    /**
     * Deactivate (soft-delete) a managed user.
     *
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return $this->error('No puedes eliminar tu propia cuenta.', 422);
        }

        // Prevent deactivating the last active root
        if ($user->hasRole('root')) {
            $activeRootsCount = User::query()->role('root')->where('active', true)->count();
            if ($activeRootsCount <= 1) {
                return $this->error('No puedes eliminar el único usuario root activo.', 422);
            }
        }

        DB::transaction(function () use ($user): void {
            $user->update(['active' => false]);
            $user->tokens()->delete();
            $user->delete();
        });

        ApiCache::bump('users-index');

        return $this->success(null, 'Usuario eliminado exitosamente.');
    }
}
