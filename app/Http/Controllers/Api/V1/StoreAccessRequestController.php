<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\ApproveDenyAccessRequest;
use App\Http\Requests\Api\V1\StoreAccessRequestFormRequest;
use App\Models\StoreAccessRequest;
use App\Models\Store;
use App\Models\User;
use App\Notifications\NewAccessRequestNotification;
use App\Notifications\StoreApprovedNotification;
use App\Notifications\StoreRejectedNotification;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

class StoreAccessRequestController extends ApiController
{
    /**
     * Public endpoint: Submit a store access request.
     *
     * POST /api/v1/access-requests
     */
    public function submit(StoreAccessRequestFormRequest $request): JsonResponse
    {
        // Check honeypot
        if ($request->isHoneypotTriggered()) {
            // Silently accept (don't reveal the honeypot)
            return $this->success(null, 'Tu solicitud fue recibida. Te contactaremos pronto.');
        }

        $accessRequest = StoreAccessRequest::create($request->validated());

        // Notify all root users
        $rootUsers = User::role('root')->get();
        foreach ($rootUsers as $rootUser) {
            $rootUser->notify(new NewAccessRequestNotification($accessRequest));
        }

        return $this->created(
            ['id' => $accessRequest->id],
            'Tu solicitud fue recibida. Te contactaremos a la brevedad.'
        );
    }

    /**
     * List all access requests with filters. Only root.
     *
     * GET /api/v1/access-requests
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StoreAccessRequest::class);

        $query = StoreAccessRequest::query()
            ->with(['branch:id,name,city', 'reviewer:id,name'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('branch_id'), fn($q) => $q->where('branch_id', $request->input('branch_id')))
            ->when($request->filled('from'), fn($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->latest();

        return $this->paginated($query->paginate(20), 'Solicitudes obtenidas exitosamente.');
    }

    /**
     * Show a single access request. Only root.
     *
     * GET /api/v1/access-requests/{id}
     */
    public function show(StoreAccessRequest $storeAccessRequest): JsonResponse
    {
        $this->authorize('view', $storeAccessRequest);

        $storeAccessRequest->load(['branch', 'reviewer:id,name,email']);

        return $this->success($storeAccessRequest, 'Solicitud obtenida exitosamente.');
    }

    /**
     * Approve an access request: create user, store, assign branch, send email.
     *
     * POST /api/v1/access-requests/{id}/approve
     */
    public function approve(ApproveDenyAccessRequest $request, StoreAccessRequest $storeAccessRequest): JsonResponse
    {
        $this->authorize('approve', $storeAccessRequest);

        if ($storeAccessRequest->status === 'approved') {
            return $this->error('Esta solicitud ya fue aprobada.', 422);
        }

        DB::transaction(function () use ($request, $storeAccessRequest): void {
            // 1. Create user with temporary password
            $tempPassword = Str::password(12, symbols: false);

            /** @var User $user */
            $user = User::create([
                'name' => $storeAccessRequest->contact_name,
                'email' => $storeAccessRequest->email,
                'password' => $tempPassword, // Will be hashed via cast
                'phone' => $storeAccessRequest->phone,
                'active' => true,
                'email_verified_at' => now(),
            ]);

            // 2. Assign 'store' role
            $user->assignRole('store');

            // 3. Create store record
            Store::create([
                'user_id' => $user->id,
                'branch_id' => $storeAccessRequest->branch_id,
                'name' => $storeAccessRequest->business_name,
                'rnc' => $storeAccessRequest->rnc,
                'phone' => $storeAccessRequest->phone,
                'email' => $storeAccessRequest->email,
                'address' => $storeAccessRequest->address,
                'contact_person' => $storeAccessRequest->contact_name,
                'active' => true,
            ]);

            // 4. Mark request as approved
            $storeAccessRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()?->id,
                'review_notes' => $request->input('review_notes'),
            ]);

            // 5. Send approval notification with credentials
            $user->notify(new StoreApprovedNotification($user, $tempPassword));
        });

        ApiCache::bumpMany(['stores-index', 'branches-index']);

        return $this->success(null, 'Solicitud aprobada. Las credenciales fueron enviadas al correo del solicitante.');
    }

    /**
     * Reject an access request.
     *
     * POST /api/v1/access-requests/{id}/reject
     */
    public function reject(ApproveDenyAccessRequest $request, StoreAccessRequest $storeAccessRequest): JsonResponse
    {
        $this->authorize('reject', $storeAccessRequest);

        if ($storeAccessRequest->status === 'approved') {
            return $this->error('No se puede rechazar una solicitud ya aprobada.', 422);
        }

        $storeAccessRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()?->id,
            'review_notes' => $request->input('review_notes'),
        ]);

        // Notify the requester by email.
        // Use on-demand routing: the applicant has no saved User record,
        // so passing an unsaved model to a ShouldQueue notification would
        // cause the queue worker to fail deserialization (null primary key).
        NotificationFacade::route('mail', [
            $storeAccessRequest->email => $storeAccessRequest->contact_name,
        ])->notify(new StoreRejectedNotification(
            $storeAccessRequest->contact_name,
            $storeAccessRequest->email,
            $request->input('review_notes') ?? ''
        ));

        return $this->success(null, 'Solicitud rechazada exitosamente.');
    }
}
