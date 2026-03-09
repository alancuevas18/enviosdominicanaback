<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ProfileController extends ApiController
{
    /**
     * Get the authenticated user's profile with their store or courier.
     *
     * GET /api/v1/profile
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $user->load(['store', 'courier', 'branches:id,name,city']);

        return $this->success([
            'user' => new UserResource($user),
            'store' => $user->store,
            'courier' => $user->courier,
        ], 'Perfil obtenido exitosamente.');
    }

    /**
     * Update the user's name, phone, or password.
     *
     * PUT /api/v1/profile
     */
    public function update(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        // Verify current password if changing
        if ($request->filled('password')) {
            if (! Hash::check($request->input('current_password'), $user->password)) {
                return $this->error('La contraseña actual es incorrecta.', 422);
            }
            $user->password = Hash::make($request->input('password'));
        }

        $user->fill($request->safe()->except(['current_password', 'password', 'password_confirmation'])->toArray());
        $user->save();

        return $this->success(new UserResource($user), 'Perfil actualizado exitosamente.');
    }

    /**
     * Update the store profile (only for store role).
     * Supports logo upload via pre-signed URL.
     *
     * PUT /api/v1/profile/store
     */
    public function updateStore(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->hasRole('store')) {
            return $this->forbidden('Solo las tiendas pueden actualizar su perfil de tienda.');
        }

        $store = $user->store;

        if ($store === null) {
            return $this->notFound('No tienes un perfil de tienda.');
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
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
            'active' => ['sometimes', 'boolean'],
        ]);

        $store->update($validated);
        ApiCache::bumpMany(['stores-index', 'branches-index']);

        return $this->success($store, 'Perfil de tienda actualizado exitosamente.');
    }

    /**
     * Generate a pre-signed URL for uploading the store logo to S3.
     *
     * POST /api/v1/profile/store/logo/presigned-url
     */
    public function logoPresignedUrl(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->hasRole('store')) {
            return $this->forbidden('Solo las tiendas pueden subir su logo.');
        }

        $store = $user->store;

        if ($store === null) {
            return $this->notFound('No tienes un perfil de tienda.');
        }

        $uuid = Str::uuid()->toString();
        $s3Key = "stores/{$store->id}/logo/{$uuid}.jpg";

        $uploadUrl = Storage::disk('s3')->temporaryUploadUrl(
            $s3Key,
            now()->addMinutes(10),
            ['ContentType' => 'image/jpeg']
        );

        return $this->success([
            'upload_url' => $uploadUrl,
            's3_key' => $s3Key,
        ], 'URL pre-firmada para logo generada exitosamente.');
    }

    /**
     * Confirm the logo upload by saving the s3_key.
     *
     * POST /api/v1/profile/store/logo/confirm
     */
    public function confirmLogo(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $store = $user->store;

        if ($store === null) {
            return $this->notFound('No tienes un perfil de tienda.');
        }

        $request->validate([
            's3_key' => ['required', 'string', 'max:500'],
        ]);

        $store->update(['logo_s3_key' => $request->input('s3_key')]);

        return $this->success(['logo_s3_key' => $store->logo_s3_key], 'Logo actualizado exitosamente.');
    }
}
