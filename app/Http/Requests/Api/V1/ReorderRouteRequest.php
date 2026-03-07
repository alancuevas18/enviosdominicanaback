<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Route;
use Illuminate\Foundation\Http\FormRequest;

class ReorderRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasAnyRole(['root', 'admin'])) {
            return false;
        }

        // Security: admin can only reorder routes belonging to their active branch
        if ($user->hasRole('admin')) {
            /** @var \App\Models\Route|null $route */
            $route = $this->route('route');

            if ($route === null) {
                return false;
            }

            $activeBranchId = $user->getActiveBranchId();

            return $activeBranchId !== null && $activeBranchId === $route->branch_id;
        }

        return true; // root
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'stop_order' => ['required', 'array', 'min:1'],
            'stop_order.*' => ['required', 'integer', 'exists:stops,id'],
        ];
    }

    /**
     * Validate that all stop_ids belong to this route.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator): void {
            /** @var Route|null $route */
            $route = $this->route('route');

            if ($route === null) {
                return;
            }

            $stopOrder = $this->input('stop_order', []);
            $routeStopIds = $route->stops()->pluck('stops.id')->toArray();

            $invalidStopIds = array_diff($stopOrder, $routeStopIds);

            if (! empty($invalidStopIds)) {
                $validator->errors()->add(
                    'stop_order',
                    'Algunas paradas no pertenecen a esta ruta: ' . implode(', ', $invalidStopIds)
                );
            }
        });
    }
}
