<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\ResetPasswordRequest;
use App\Http\Requests\Api\V1\SwitchBranchRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends ApiController
{
    /**
     * Authenticate the user and return a Sanctum token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $request->email)->first();

        if ($user === null || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        if (! $user->active) {
            return $this->error('Tu cuenta está desactivada. Contacta al administrador.', 403);
        }

        // Revoke all previous tokens on login
        $user->tokens()->delete();

        $token = $user->createToken('api-token')->plainTextToken;

        $role = $user->getRoleNames()->first();
        $branches = null;

        if ($user->hasRole('admin')) {
            $branches = $user->branches()->select(['branches.id', 'branches.name', 'branches.city'])->get();
        }

        return $this->success([
            'token' => $token,
            'user' => new UserResource($user),
            'role' => $role,
            'branches' => $branches,
        ], 'Sesión iniciada exitosamente.');
    }

    /**
     * Revoke the current token (logout).
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Sesión cerrada exitosamente.');
    }

    /**
     * Return the authenticated user with their role and permissions.
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $role = $user->getRoleNames()->first();
        $permissions = $user->getAllPermissions()->pluck('name');
        $branches = null;
        $activeBranchId = null;

        if ($user->hasRole('admin')) {
            $branches = $user->branches()->select(['branches.id', 'branches.name', 'branches.city'])->get();
            $activeBranchId = $user->getActiveBranchId();
        }

        return $this->success([
            'user' => new UserResource($user),
            'role' => $role,
            'permissions' => $permissions,
            'branches' => $branches,
            'active_branch_id' => $activeBranchId,
        ]);
    }

    /**
     * Switch the active branch for an admin user.
     * Stores the branch in the token name as "branch:{id}".
     *
     * POST /api/v1/auth/switch-branch
     */
    public function switchBranch(SwitchBranchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $branchId = (int) $request->validated('branch_id');

        // Ensure the admin has this branch assigned
        $hasBranch = $user->branches()->where('branches.id', $branchId)->exists();

        if (! $hasBranch) {
            return $this->error('No tienes acceso a esta sucursal.', 403);
        }

        // Update the current token name to reflect the active branch
        $currentToken = $user->currentAccessToken();
        $currentToken->forceFill(['name' => 'branch:' . $branchId])->save();

        return $this->success([
            'active_branch_id' => $branchId,
        ], 'Sucursal activa actualizada exitosamente.');
    }

    /**
     * Send a password reset link to the user's email.
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Always send the reset link silently — never reveal whether the email exists.
        Password::sendResetLink($request->only('email'));

        return $this->success(
            null,
            'Si el correo está registrado, recibirás un enlace de recuperación en breve.'
        );
    }

    /**
     * Reset the user's password.
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->success(null, 'Contraseña restablecida exitosamente.');
        }

        return $this->error(
            match ($status) {
                Password::INVALID_TOKEN => 'Token inválido o expirado.',
                Password::INVALID_USER => 'Usuario no encontrado.',
                default => 'No se pudo restablecer la contraseña.',
            },
            400
        );
    }
}
