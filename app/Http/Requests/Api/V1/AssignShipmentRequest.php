<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Courier;
use App\Models\Shipment;
use App\Models\Stop;
use Illuminate\Foundation\Http\FormRequest;

class AssignShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(['root', 'admin']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'courier_id' => ['required', 'integer', 'exists:couriers,id'],
            'stop_order' => ['required', 'array', 'min:1'],
            'stop_order.*' => ['required', 'integer', 'exists:stops,id'],
        ];
    }

    /**
     * Validate that the courier belongs to the same branch as the shipment.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator): void {
            /** @var Shipment|null $shipment */
            $shipment = $this->route('shipment');

            if ($shipment === null) {
                return;
            }

            $courierId = $this->input('courier_id');

            /** @var Courier|null $courier */
            $courier = Courier::withoutGlobalScopes()->find($courierId);

            if ($courier === null) {
                return;
            }

            if ($courier->branch_id !== $shipment->branch_id) {
                $validator->errors()->add(
                    'courier_id',
                    'El mensajero debe pertenecer a la misma sucursal que la orden.'
                );
            }

            // Security: all stop IDs must belong to this specific shipment
            $stopOrder = $this->input('stop_order', []);
            if (! empty($stopOrder)) {
                $validStopIds = Stop::where('shipment_id', $shipment->id)
                    ->pluck('id')
                    ->toArray();

                $foreignStopIds = array_diff($stopOrder, $validStopIds);

                if (! empty($foreignStopIds)) {
                    $validator->errors()->add(
                        'stop_order',
                        'Las paradas proporcionadas no pertenecen a esta orden.'
                    );
                }
            }
        });
    }
}
