<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Shipment;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    /**
     * Return dashboard KPIs, charts, and metrics.
     *
     * GET /api/v1/dashboard
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $date = (string) $request->input('date', today()->toDateString());
        $requestedBranchId = $user->hasRole('root') && $request->filled('branch_id')
            ? (string) $request->input('branch_id')
            : 'all';

        $cacheKey = implode(':', [
            'user',
            (string) $user->id,
            'date',
            $date,
            'branch',
            $requestedBranchId,
        ]);

        $ttlSeconds = max(5, (int) env('DASHBOARD_CACHE_TTL_SECONDS', 60));

        $payload = ApiCache::remember('dashboard', $cacheKey, now()->addSeconds($ttlSeconds), function () use ($request, $user, $date): array {
            $query = Shipment::query();

            if ($user->hasRole('root') && $request->filled('branch_id')) {
                $query->where('branch_id', (int) $request->input('branch_id'));
            }

            $query->whereDate('created_at', $date);

            $kpiData = (clone $query)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $kpis = [
                'total' => $kpiData->sum(),
                'pending' => (int) ($kpiData['pending'] ?? 0),
                'assigned' => (int) ($kpiData['assigned'] ?? 0),
                'in_route' => (int) ($kpiData['in_route'] ?? 0),
                'picked_up' => (int) ($kpiData['picked_up'] ?? 0),
                'delivered' => (int) ($kpiData['delivered'] ?? 0),
                'not_delivered' => (int) ($kpiData['not_delivered'] ?? 0),
            ];

            $donutChart = [
                'labels' => ['Pendiente', 'Asignado', 'Recogido', 'En ruta', 'Entregado', 'No entregado'],
                'data' => [
                    $kpis['pending'],
                    $kpis['assigned'],
                    $kpis['picked_up'],
                    $kpis['in_route'],
                    $kpis['delivered'],
                    $kpis['not_delivered'],
                ],
            ];

            $barChartData = Shipment::withoutGlobalScopes()
                ->when($user->hasRole('admin'), fn($q) => $q->where('branch_id', $user->getActiveBranchId()))
                ->when($user->hasRole('root') && $request->filled('branch_id'), fn($q) => $q->where('branch_id', (int) $request->input('branch_id')))
                ->whereDate('created_at', $date)
                ->whereNotNull('courier_id')
                ->with('courier:id,name')
                ->select('courier_id', 'status', DB::raw('count(*) as total'))
                ->groupBy('courier_id', 'status')
                ->get()
                ->groupBy('courier_id')
                ->map(function ($rows): array {
                    $courier = $rows->first()->courier;

                    return [
                        'courier_name' => $courier?->name ?? 'N/A',
                        'assigned' => $rows->sum('total'),
                        'completed' => (int) ($rows->where('status', 'delivered')->first()?->total ?? 0),
                    ];
                })
                ->values()
                ->toArray();

            $lineChartData = Shipment::withoutGlobalScopes()
                ->when($user->hasRole('admin'), fn($q) => $q->where('branch_id', $user->getActiveBranchId()))
                ->when($user->hasRole('root') && $request->filled('branch_id'), fn($q) => $q->where('branch_id', (int) $request->input('branch_id')))
                ->whereDate('created_at', $date)
                ->where('status', 'delivered')
                ->select(DB::raw("DATE_FORMAT(updated_at, '%H:00') as hour"), DB::raw('count(*) as completed'))
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->map(fn($row): array => ['hour' => $row->hour, 'completed' => (int) $row->completed])
                ->toArray();

            return [
                'kpis' => $kpis,
                'donut_chart' => $donutChart,
                'bar_chart' => $barChartData,
                'line_chart' => $lineChartData,
                'date' => $date,
            ];
        });

        return $this->success($payload, 'Dashboard obtenido exitosamente.');
    }
}
