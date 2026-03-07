<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Stop extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'type',
        'suggested_order',
        'status',
        'completed_at',
        'photo_s3_key',
        'amount_collected',
        'fail_reason',
    ];

    protected $casts = [
        'suggested_order' => 'integer',
        'status' => 'string',
        'type' => 'string',
        'completed_at' => 'datetime',
        'amount_collected' => 'decimal:2',
    ];

    /** @return BelongsTo<Shipment, self> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsToMany<Route> */
    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'route_stops')
            ->withPivot('suggested_order')
            ->withTimestamps();
    }

    /** @return HasOne<RouteStop> */
    public function routeStop(): HasOne
    {
        return $this->hasOne(RouteStop::class);
    }
}
