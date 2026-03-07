<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = [
        'route_id',
        'stop_id',
        'suggested_order',
    ];

    protected $casts = [
        'suggested_order' => 'integer',
    ];

    /** @return BelongsTo<Route, self> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    /** @return BelongsTo<Stop, self> */
    public function stop(): BelongsTo
    {
        return $this->belongsTo(Stop::class);
    }
}
