<?php

declare(strict_types=1);

namespace App\Models;

use App\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $fillable = [
        'courier_id',
        'branch_id',
        'date',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => 'string',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope());
    }

    /** @return BelongsTo<Courier, self> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return BelongsTo<Branch, self> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsToMany<Stop> */
    public function stops(): BelongsToMany
    {
        return $this->belongsToMany(Stop::class, 'route_stops')
            ->withPivot('suggested_order')
            ->withTimestamps()
            ->orderByPivot('suggested_order');
    }

    /** @return HasMany<RouteStop> */
    public function routeStops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('suggested_order');
    }
}
