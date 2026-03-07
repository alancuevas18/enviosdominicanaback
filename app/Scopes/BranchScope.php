<?php

declare(strict_types=1);

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global scope that automatically filters models by branch_id
 * when the authenticated user has the 'admin' role.
 *
 * Root users bypass this scope and see all records.
 * Courier and store users also bypass this scope (their own data
 * restrictions are handled at the policy/controller level).
 */
class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! Auth::check()) {
            return;
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Root bypasses all branch scoping
        if ($user->hasRole('root')) {
            return;
        }

        // Admin: apply automatic filtering by their active branch
        if ($user->hasRole('admin')) {
            $activeBranchId = $user->getActiveBranchId();

            if ($activeBranchId !== null) {
                $builder->where($model->getTable() . '.branch_id', $activeBranchId);
            } else {
                // Security: admin with no branch assigned or ambiguous context
                // must see NOTHING rather than everything.
                $builder->whereRaw('1 = 0');
            }
        }

        // For store and courier roles, no automatic branch scope is applied here.
        // Those are filtered at the policy/controller level.
    }
}
