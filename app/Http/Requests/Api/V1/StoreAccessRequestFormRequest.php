<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\StoreAccessRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreAccessRequestFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint — anyone can submit
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'business_name'   => ['required', 'string', 'max:255'],
            'contact_name'    => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['required', 'string', 'regex:/^\+?[0-9\s\-\(\)]{7,20}$/'],
            'branch_id'       => ['required', 'integer', 'exists:branches,id'],
            'address'         => ['nullable', 'string', 'max:500'],
            'rnc'             => ['nullable', 'string', 'max:20'],
            'description'     => ['nullable', 'string', 'max:2000'],
            'volume_estimate' => ['nullable', 'in:1-10,10-50,50-100,100+'],
            // Simple honeypot field — if filled, reject silently
            'website_url'     => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * Prevent the same email from submitting multiple pending requests for the same branch.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator): void {
            $email    = $this->input('email');
            $branchId = $this->input('branch_id');

            if (! $email || ! $branchId) {
                return;
            }

            $alreadyPending = StoreAccessRequest::where('email', $email)
                ->where('branch_id', $branchId)
                ->where('status', 'pending')
                ->exists();

            if ($alreadyPending) {
                $validator->errors()->add(
                    'email',
                    'Ya existe una solicitud pendiente con este correo para la sucursal seleccionada.'
                );
            }
        });
    }

    /**
     * Determine if the honeypot was triggered.
     */
    public function isHoneypotTriggered(): bool
    {
        return filled($this->input('website_url'));
    }
}
