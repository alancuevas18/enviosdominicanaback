<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ApproveDenyAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('root') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string'],
        ];
    }
}
