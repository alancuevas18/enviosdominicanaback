<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureBranchContext;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequests;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'force.json' => ForceJsonResponse::class,
            'log.api' => LogApiRequests::class,
            'verified' => EnsureEmailVerified::class,
            'ensure.branch.context' => EnsureBranchContext::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $jsonError = fn(int $status, string $message, array $errors = []): JsonResponse => response()->json(
            array_filter([
                'success' => false,
                'message' => $message,
                'errors' => $errors ?: null,
            ]),
            $status
        );

        $exceptions->render(function (AuthenticationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Acción no autorizada.',
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e): JsonResponse {
            $model = class_basename($e->getModel());

            return response()->json([
                'success' => false,
                'message' => "{$model} no encontrado.",
            ], 404);
        });

        $exceptions->render(function (ValidationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Demasiadas solicitudes. Intenta más tarde.',
            ], 429);
        });

        $exceptions->render(function (InvalidSignatureException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Enlace inválido o expirado.',
            ], 403);
        });

        $exceptions->render(function (HttpException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Error en la solicitud.',
            ], $e->getStatusCode());
        });

        $exceptions->render(function (Throwable $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'Error interno del servidor.'
                    : $e->getMessage(),
            ], 500);
        });
    })->create();
