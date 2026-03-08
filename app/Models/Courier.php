<?php

declare(strict_types=1);

namespace App\Models;

use App\Scopes\BranchScope;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Courier extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;
    protected $fillable = [
        'user_id',
        'branch_id',
        'name',
        'phone',
        'vehicle',
        'rating_avg',
        'rating_count',
        'active',
    ];

    protected $casts = [
        'rating_avg' => 'decimal:2',
        'rating_count' => 'integer',
        'active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('courier')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, self> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return HasMany<Shipment> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<Route> */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }

    /** @return HasMany<ShipmentRating> */
    public function ratings(): HasMany
    {
        return $this->hasMany(ShipmentRating::class);
    }

    /**
     * Recalculate rating_avg and rating_count from shipment_ratings.
     */
    public function recalculateRating(): void
    {
        $ratings = $this->ratings();
        $count = $ratings->count();
        $avg = $count > 0 ? $ratings->avg('score') : 0.00;

        $this->update([
            'rating_avg' => round((float) $avg, 2),
            'rating_count' => $count,
        ]);
    }
}
