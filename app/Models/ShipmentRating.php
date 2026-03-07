<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentRating extends Model
{
    protected $fillable = [
        'shipment_id',
        'store_id',
        'courier_id',
        'score',
        'comment',
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    /** @return BelongsTo<Shipment, self> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<Store, self> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Courier, self> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }
}
