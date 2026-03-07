<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that ensures admin users have an active branch selected
 * before accessing endpoints that require branch context.
 *
 * If an admin has multiple branches assigned and hasn't selected one,
 * this returns a 403 response asking them to select a branch.
 *
 * Root users and non-admin users are always allowed through.
 */
class EnsureBranchContext
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Root bypasses branch context requirement
        if ($user->hasRole('root')) {
            return $next($request);
        }

        // Only applies to admin role
        if ($user->hasRole('admin')) {
            $branches = $user->branches;

            // If admin has more than one branch, they must select one
            if ($branches->count() > 1) {
                $activeBranchId = $user->getActiveBranchId();

                if ($activeBranchId === null) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Debes seleccionar una sucursal activa para continuar.',
                    ], Response::HTTP_FORBIDDEN);
                }

                // Verify the active branch is actually one of their branches
                $hasBranch = $branches->contains('id', $activeBranchId);
                if (! $hasBranch) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'La sucursal seleccionada no está asignada a tu usuario.',
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        return $next($request);
    }
}
