<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole(['root', 'admin', 'store']);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'regex:/^\+1[0-9]{10}$/'],
            'address' => ['required', 'string'],
            'maps_url' => ['nullable', 'url', 'max:2048'],
            'sector' => ['nullable', 'string', 'max:255'],
            'amount_to_collect' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'payment_method' => ['nullable', 'in:cash,transfer,card'],
            'payer' => ['nullable', 'in:store,customer'],
            'notes' => ['nullable', 'string'],
            'custom_notification_message' => ['nullable', 'string'],
            'weight_size' => ['nullable', 'in:small,medium,large'],
            // pickup_address is used to create the pickup stop
            'pickup_address' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient_phone.regex' => 'El teléfono del destinatario debe tener el formato +1XXXXXXXXXX.',
            'maps_url.url' => 'El enlace de Google Maps debe ser una URL válida.',
        ];
    }
}
