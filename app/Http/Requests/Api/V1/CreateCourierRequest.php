<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;

class CreateCourierRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:20'],
            'vehicle' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'user_email' => ['required', 'email', 'unique:users,email'],
            'user_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Additional validation after basic rules pass.
     * Ensures admin can only create couriers in their active branch.
     */
    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Contracts\Validation\Validator $validator): void {
            $user = $this->user();

            if ($user === null || $user->hasRole('root')) {
                return;
            }

            if ($user->hasRole('admin')) {
                $activeBranchId = $user->getActiveBranchId();
                $requestedBranchId = (int) $this->input('branch_id');

                if ($activeBranchId !== $requestedBranchId) {
                    $validator->errors()->add(
                        'branch_id',
                        'Solo puedes crear mensajeros en tu sucursal activa.'
                    );
                }
            }
        });
    }
}
