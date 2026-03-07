<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shipment = $this->route('shipment');

        return $shipment !== null && $this->user()?->can('update', $shipment) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recipient_name'             => ['sometimes', 'required', 'string', 'max:255'],
            'recipient_phone'            => ['sometimes', 'required', 'string', 'regex:/^\+1[0-9]{10}$/'],
            'address'                    => ['sometimes', 'required', 'string'],
            'maps_url'                   => ['nullable', 'url', 'max:2048'],
            'sector'                     => ['nullable', 'string', 'max:255'],
            'amount_to_collect'          => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'payment_method'             => ['nullable', 'in:cash,transfer,card'],
            'payer'                      => ['nullable', 'in:store,customer'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
            'custom_notification_message' => ['nullable', 'string', 'max:2000'],
            'weight_size'                => ['nullable', 'in:small,medium,large'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'recipient_phone.regex' => 'El teléfono del destinatario debe tener el formato +1XXXXXXXXXX.',
            'maps_url.url'          => 'El enlace de Google Maps debe ser una URL válida.',
        ];
    }
}
