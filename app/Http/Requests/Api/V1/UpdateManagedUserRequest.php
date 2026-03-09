<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole('root');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var User|null $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($managedUser?->id),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
