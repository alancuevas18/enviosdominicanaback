<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Stop;
use Illuminate\Foundation\Http\FormRequest;

class UpdateStopStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->hasRole('courier')) {
            return false;
        }

        // Verify the courier owns this stop via their route
        /** @var Stop|null $stop */
        $stop = $this->route('stop');

        if ($stop === null) {
            return false;
        }

        $courier = $user->courier;

        if ($courier === null) {
            return false;
        }

        // Check if the stop belongs to one of the courier's routes
        return $stop->routes()
            ->whereHas('courier', fn($q) => $q->where('couriers.id', $courier->id))
            ->exists();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:complete_pickup,complete_delivery,fail'],
            'amount_collected' => ['nullable', 'numeric', 'min:0'],
            'fail_reason' => ['required_if:action,fail', 'nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fail_reason.required_if' => 'Debes indicar el motivo por el que no se pudo entregar.',
        ];
    }
}
