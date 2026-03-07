<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Stop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PhotoController extends ApiController
{
    /**
     * Generate a pre-signed S3 URL for uploading a delivery photo directly from the PWA.
     *
     * POST /api/v1/stops/{id}/photo/presigned-url
     */
    public function presignedUrl(Request $request, Stop $stop): JsonResponse
    {
        $this->authorize('uploadPhoto', $stop);

        $shipment = $stop->shipment;
        $branchId = $shipment->branch_id;
        $date = now()->format('Y-m-d');
        $uuid = Str::uuid()->toString();

        // Path: deliveries/{branch_id}/{date}/{stop_id}/{uuid}.jpg
        $s3Key = "deliveries/{$branchId}/{$date}/{$stop->id}/{$uuid}.jpg";

        // Generate a pre-signed URL valid for 10 minutes
        $uploadUrl = Storage::disk('s3')->temporaryUploadUrl(
            $s3Key,
            now()->addMinutes(10),
            [
                'ContentType' => 'image/jpeg',
                'ACL' => 'private',
            ]
        );

        return $this->success([
            'upload_url' => $uploadUrl,
            's3_key' => $s3Key,
        ], 'URL pre-firmada generada exitosamente.');
    }

    /**
     * Confirm that the photo was uploaded to S3 by saving the s3_key on the stop.
     *
     * POST /api/v1/stops/{id}/photo/confirm
     */
    public function confirm(Request $request, Stop $stop): JsonResponse
    {
        $this->authorize('uploadPhoto', $stop);

        $request->validate([
            's3_key' => ['required', 'string', 'max:500'],
        ]);

        $s3Key = $request->input('s3_key');

        // Security: validate the key belongs strictly to this stop and its branch.
        // Expected pattern: deliveries/{branch_id}/{date}/{stop_id}/{uuid}.jpg
        $branchId = $stop->shipment->branch_id;
        $expectedPrefix = "deliveries/{$branchId}/";

        if (! str_starts_with($s3Key, $expectedPrefix)) {
            return $this->error('Clave S3 inválida para esta sucursal.', 422);
        }

        // Ensure the stop_id segment matches this stop
        // Pattern: deliveries/{branch_id}/{Y-m-d}/{stop_id}/{uuid}.jpg
        $parts = explode('/', $s3Key);
        // parts[0]=deliveries, parts[1]={branch_id}, parts[2]={date}, parts[3]={stop_id}, parts[4]={uuid}.jpg
        if (count($parts) !== 5) {
            return $this->error('Clave S3 con formato inválido.', 422);
        }

        $keyStopId = (int) $parts[3];
        if ($keyStopId !== $stop->id) {
            return $this->error('La clave S3 no corresponde a esta parada.', 422);
        }

        // Validate date format
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $parts[2])) {
            return $this->error('Clave S3 con fecha inválida.', 422);
        }

        // Validate filename ends in .jpg and contains a UUID-like token
        if (! str_ends_with($parts[4], '.jpg')) {
            return $this->error('El archivo debe ser una imagen JPEG.', 422);
        }

        $stop->update(['photo_s3_key' => $s3Key]);

        return $this->success([
            'photo_s3_key' => $s3Key,
            'stop_id' => $stop->id,
        ], 'Foto confirmada. Ya puedes completar esta parada.');
    }
}
