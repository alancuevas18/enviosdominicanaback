<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\CourierController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PhotoController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\RouteController;
use App\Http\Controllers\Api\V1\ShipmentController;
use App\Http\Controllers\Api\V1\StopController;
use App\Http\Controllers\Api\V1\StoreAccessRequestController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

// ──────────────────────────────────────────────────────────────────────────
// Public (unauthenticated)
// ──────────────────────────────────────────────────────────────────────────
// Stricter rate limiting for authentication endpoints (prevent brute force)
Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1') // 5 attempts per 1 minute per IP
    ->name('api.v1.login');

Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:3,1') // 3 attempts per 1 minute per IP
    ->name('password.email');

Route::post('reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:5,1') // 5 attempts per 1 minute per IP
    ->name('password.reset');

// Store access request — public submission (honeypot protected inside FormRequest)
Route::post('access-requests', [StoreAccessRequestController::class, 'submit'])
    ->middleware('throttle:10,1')
    ->name('api.v1.access-requests.submit');

// ──────────────────────────────────────────────────────────────────────────
// Authenticated
// ──────────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function (): void {

    // Auth
    Route::post('logout', [AuthController::class, 'logout'])->name('api.v1.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.v1.me');
    Route::post('switch-branch', [AuthController::class, 'switchBranch'])
        ->middleware('throttle:10,1')
        ->name('api.v1.switch-branch');

    // Profile (all roles)
    Route::prefix('profile')->name('api.v1.profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('store', [ProfileController::class, 'updateStore'])->name('store.update');
        Route::post('logo/presigned-url', [ProfileController::class, 'logoPresignedUrl'])->name('logo.presigned-url');
        Route::post('logo/confirm', [ProfileController::class, 'confirmLogo'])->name('logo.confirm');
    });

    // Notifications (all roles)
    Route::prefix('notifications')->name('api.v1.notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('{id}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::delete('{id}', [NotificationController::class, 'destroy'])->name('destroy');
    });

    // Push subscriptions (all roles, primarily courier)
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'subscribe'])->name('api.v1.push-subscriptions.subscribe');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'unsubscribe'])->name('api.v1.push-subscriptions.unsubscribe');

    // ──────────────────────────────────────────────────────────────────────
    // Root only
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware('role:root')->name('api.v1.root.')->group(function (): void {

        // Branches management
        Route::apiResource('branches', BranchController::class)->names('branches');
        Route::post('branches/{branch}/admins', [BranchController::class, 'assignAdmins'])->name('branches.admins.assign');
        Route::delete('branches/{branch}/admins/{user}', [BranchController::class, 'removeAdmin'])->name('branches.admins.remove');

        // Access requests management
        Route::prefix('access-requests')->name('access-requests.')->group(function (): void {
            Route::get('/', [StoreAccessRequestController::class, 'index'])->name('index');
            Route::get('{accessRequest}', [StoreAccessRequestController::class, 'show'])->name('show');
            Route::post('{accessRequest}/approve', [StoreAccessRequestController::class, 'approve'])->name('approve');
            Route::post('{accessRequest}/reject', [StoreAccessRequestController::class, 'reject'])->name('reject');
        });

        // Users management
        Route::prefix('users')->name('users.')->group(function (): void {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::post('admins', [UserController::class, 'storeAdmin'])->name('admins.store');
            Route::get('{user}', [UserController::class, 'show'])->name('show');
            Route::patch('{user}', [UserController::class, 'update'])->name('update');
            Route::delete('{user}', [UserController::class, 'destroy'])->name('destroy');
            Route::put('{user}/branches', [UserController::class, 'syncBranches'])->name('branches.sync');
        });

        // Activity log (read-only, root only)
        Route::prefix('activity-log')->name('activity-log.')->group(function (): void {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::get('log-names', [ActivityLogController::class, 'logNames'])->name('log-names');
        });
    });

    // ──────────────────────────────────────────────────────────────────────
    // Root + Admin (with mandatory branch context)
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware(['role:root|admin', 'ensure.branch.context'])->name('api.v1.admin.')->group(function (): void {

        // Stores — admin CRUD
        Route::apiResource('stores', StoreController::class)
            ->except(['create', 'edit'])
            ->names('stores');

        // Couriers — admin CRUD
        Route::apiResource('couriers', CourierController::class)
            ->except(['create', 'edit'])
            ->names('couriers');
        Route::get('couriers/{courier}/ratings', [CourierController::class, 'ratings'])->name('couriers.ratings');

        // Shipments — admin management (assign only)
        Route::post('shipments/{shipment}/assign', [ShipmentController::class, 'assign'])->name('shipments.assign');

        // Routes — admin view & reorder
        Route::get('routes', [RouteController::class, 'index'])->name('routes.index');
        Route::post('routes/{route}/reorder', [RouteController::class, 'reorder'])->name('routes.reorder');

        // Dashboard KPIs
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');
    });

    // ──────────────────────────────────────────────────────────────────────
    // Store role
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware('role:store')->name('api.v1.store.')->group(function (): void {
        Route::post('shipments/{shipment}/rate', [ShipmentController::class, 'rate'])->name('shipments.rate');
    });

    // ──────────────────────────────────────────────────────────────────────
    // Shared: Store + Admin can read and create shipments
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware('role:root|admin|store')->name('api.v1.shipments.')->group(function (): void {
        Route::get('shipments', [ShipmentController::class, 'index'])->name('index');
        Route::post('shipments', [ShipmentController::class, 'store'])->name('store');
        Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->name('show');
        Route::get('shipments/{shipment}/history', [ShipmentController::class, 'history'])->name('history');
        // Update allowed for store (own pending) and admin (branch-scoped); policy enforces ownership
        Route::patch('shipments/{shipment}', [ShipmentController::class, 'update'])->name('update');
    });

    // ──────────────────────────────────────────────────────────────────────
    // Courier role
    // ──────────────────────────────────────────────────────────────────────
    Route::middleware('role:courier')->name('api.v1.courier.')->group(function (): void {
        Route::get('routes/today', [RouteController::class, 'today'])->name('routes.today');
        Route::patch('stops/{stop}/status', [StopController::class, 'updateStatus'])->name('stops.update-status');
        Route::post('photos/presigned-url', [PhotoController::class, 'presignedUrl'])->name('photos.presigned-url');
        Route::post('photos/confirm', [PhotoController::class, 'confirm'])->name('photos.confirm');
    });
});
