<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Shipment;
use Illuminate\Foundation\Http\FormRequest;

class RateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasRole('store')) {
            return false;
        }

        /** @var Shipment|null $shipment */
        $shipment = $this->route('shipment');

        if ($shipment === null) {
            return false;
        }

        $store = $user->store;

        // Verify the shipment belongs to this store
        if ($store === null || $shipment->store_id !== $store->id) {
            return false;
        }

        // Verify the shipment is delivered
        if ($shipment->status !== 'delivered') {
            return false;
        }

        // Verify no rating exists yet
        if ($shipment->rating !== null) {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
