<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $this */
        $roles = $this->getRoleNames();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'active' => $this->active,
            'roles' => $roles,
            'role' => $roles->first(),
            'branches' => $this->whenLoaded('branches', fn() => $this->branches->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'city' => $b->city,
            ])),
            'store' => $this->whenLoaded('store', fn() => $this->store !== null ? [
                'id' => $this->store->id,
                'branch_id' => $this->store->branch_id,
                'name' => $this->store->name,
                'active' => $this->store->active,
            ] : null),
            'courier' => $this->whenLoaded('courier', fn() => $this->courier !== null ? [
                'id' => $this->courier->id,
                'branch_id' => $this->courier->branch_id,
                'name' => $this->courier->name,
                'active' => $this->courier->active,
            ] : null),
            'active_branch_id' => $this->when(
                $request->user()?->is($this->resource),
                fn() => $this->getActiveBranchId()
            ),
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
