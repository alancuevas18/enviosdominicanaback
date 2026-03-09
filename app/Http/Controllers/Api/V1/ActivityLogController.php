<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends ApiController
{
    /**
     * List activity log entries (root only).
     *
     * GET /api/v1/activity-log
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 100);

        $query = Activity::with(['causer', 'subject'])
            ->orderByDesc('created_at');

        // Filter by log_name (e.g. "default", "shipment", "store" …)
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        // Filter by event (created, updated, deleted …)
        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        // Filter by subject type (short class name or full morph)
        if ($request->filled('subject_type')) {
            $type = $request->input('subject_type');
            // Accept either "Store" (short) or "App\Models\Store" (full)
            if (!str_contains($type, '\\')) {
                $type = 'App\\Models\\' . ucfirst($type);
            }
            $query->where('subject_type', $type);
        }

        // Filter by causer (user) id
        if ($request->filled('causer_id')) {
            $query->where('causer_id', (int) $request->input('causer_id'))
                ->where('causer_type', 'App\\Models\\User');
        }

        // Full-text search on description
        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('description', 'like', $search);
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $results = $query->paginate($perPage);

        // Transform each record for the frontend
        $results->getCollection()->transform(function (Activity $item) {
            return [
                'id'           => $item->id,
                'log_name'     => $item->log_name,
                'event'        => $item->event,
                'description'  => $item->description,
                'subject_type' => $item->subject_type
                    ? class_basename($item->subject_type)
                    : null,
                'subject_id'   => $item->subject_id,
                'subject_label' => $this->resolveSubjectLabel($item),
                'causer'       => $item->causer ? [
                    'id'    => $item->causer->id,
                    'name'  => $item->causer->name,
                    'email' => $item->causer->email,
                    'role'  => $item->causer->role ?? null,
                ] : null,
                'properties'   => $item->properties?->toArray(),
                'batch_uuid'   => $item->batch_uuid ?? null,
                'created_at'   => $item->created_at?->toISOString(),
            ];
        });

        return $this->paginated($results, 'Registros de actividad obtenidos exitosamente.');
    }

    /**
     * Get distinct log_name values (for filter dropdown).
     *
     * GET /api/v1/activity-log/log-names
     */
    public function logNames(): JsonResponse
    {
        $names = Activity::select('log_name')
            ->distinct()
            ->whereNotNull('log_name')
            ->orderBy('log_name')
            ->pluck('log_name');

        return $this->success($names, 'Log names obtenidos.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function resolveSubjectLabel(Activity $item): ?string
    {
        if (!$item->subject) {
            return $item->subject_id ? "#{$item->subject_id}" : null;
        }

        $subject = $item->subject;

        // Try common name-like attributes
        foreach (['name', 'title', 'order_number', 'email'] as $attr) {
            if (!empty($subject->{$attr})) {
                return $subject->{$attr};
            }
        }

        return "#{$subject->id}";
    }
}
