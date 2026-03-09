<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'user_email' => ['required', 'email', 'unique:users,email'],
            'user_name' => ['required', 'string', 'max:255'],
            'user_phone' => ['nullable', 'string', 'max:20'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'rnc' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'sector' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'default_notification_message' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'maps_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
