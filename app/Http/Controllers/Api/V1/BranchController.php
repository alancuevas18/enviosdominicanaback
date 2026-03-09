<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\CreateBranchRequest;
use App\Http\Requests\Api\V1\UpdateBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends ApiController
{
    /**
     * List all branches.
     *
     * GET /api/v1/branches
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $cacheKey = implode(':', [
            'user',
            (string) $request->user()?->id,
            'active_only',
            (string) (int) $request->boolean('active_only'),
            'page',
            (string) (int) $request->input('page', 1),
        ]);

        $ttlSeconds = max(10, (int) env('LIST_CACHE_TTL_SECONDS', 60));

        $branches = ApiCache::remember('branches-index', $cacheKey, now()->addSeconds($ttlSeconds), function () use ($request) {
            return Branch::query()
                ->when($request->boolean('active_only'), fn($q) => $q->where('active', true))
                ->withCount(['stores', 'couriers', 'shipments'])
                ->paginate(20);
        });

        return $this->paginated($branches, 'Sucursales obtenidas exitosamente.');
    }

    /**
     * Create a new branch.
     *
     * POST /api/v1/branches
     */
    public function store(CreateBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);

        $branch = Branch::create($request->validated());
        ApiCache::bump('branches-index');

        return $this->created($branch, 'Sucursal creada exitosamente.');
    }

    /**
     * Show branch details with admins, stores, and couriers.
     *
     * GET /api/v1/branches/{id}
     */
    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        $branch->load([
            'admins:id,name,email,phone',
            'stores:id,branch_id,name,active',
            'couriers:id,branch_id,name,phone,active,rating_avg',
        ]);

        return $this->success($branch, 'Sucursal obtenida exitosamente.');
    }

    /**
     * Update a branch.
     *
     * PUT /api/v1/branches/{id}
     */
    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());
        ApiCache::bump('branches-index');

        return $this->success($branch, 'Sucursal actualizada exitosamente.');
    }

    /**
     * Soft-delete a branch.
     *
     * DELETE /api/v1/branches/{id}
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $branch->update(['active' => false]);
        $branch->delete();
        ApiCache::bump('branches-index');

        return $this->success(null, 'Sucursal eliminada exitosamente.');
    }

    /**
     * Assign admin(s) to a branch.
     *
     * POST /api/v1/branches/{id}/admins
     */
    public function assignAdmins(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var array<int> $userIds */
        $userIds = $request->input('user_ids');

        // Only assign users who have admin role
        $adminUsers = User::whereIn('id', $userIds)
            ->role('admin')
            ->pluck('id')
            ->toArray();

        if (count($adminUsers) !== count($userIds)) {
            return $this->error('Algunos usuarios no tienen el rol de admin.', 422);
        }

        // Sync — attach without removing existing ones
        $branch->admins()->syncWithoutDetaching($adminUsers);
        ApiCache::bump('branches-index');

        return $this->success(null, 'Admins asignados exitosamente.');
    }

    /**
     * Remove an admin from a branch.
     *
     * DELETE /api/v1/branches/{id}/admins/{userId}
     */
    public function removeAdmin(Branch $branch, User $user): JsonResponse
    {
        $this->authorize('update', $branch);

        $branch->admins()->detach($user->id);
        ApiCache::bump('branches-index');

        return $this->success(null, 'Admin desasignado exitosamente.');
    }
}
