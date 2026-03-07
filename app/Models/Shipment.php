<?php

declare(strict_types=1);

namespace App\Models;

use App\Scopes\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'store_id',
        'branch_id',
        'courier_id',
        'recipient_name',
        'recipient_phone',
        'address',
        'maps_url',
        'sector',
        'amount_to_collect',
        'payment_method',
        'payer',
        'status',
        'notes',
        'custom_notification_message',
        'weight_size',
    ];

    protected $casts = [
        'amount_to_collect' => 'decimal:2',
        'status' => 'string',
        'payment_method' => 'string',
        'payer' => 'string',
        'weight_size' => 'string',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new BranchScope());
    }

    /** @return BelongsTo<Store, self> */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /** @return BelongsTo<Branch, self> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Courier, self> */
    public function courier(): BelongsTo
    {
        return $this->belongsTo(Courier::class);
    }

    /** @return HasMany<Stop> */
    public function stops(): HasMany
    {
        return $this->hasMany(Stop::class);
    }

    /** @return HasMany<ShipmentStatusHistory> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ShipmentStatusHistory::class)->orderBy('created_at');
    }

    /** @return HasOne<ShipmentRating> */
    public function rating(): HasOne
    {
        return $this->hasOne(ShipmentRating::class);
    }

    /**
     * Scope to filter shipments by branch.
     *
     * @param  Builder<Shipment>  $query
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Get the pickup stop.
     */
    public function getPickupStopAttribute(): ?Stop
    {
        return $this->stops->where('type', 'pickup')->first();
    }

    /**
     * Get the delivery stop.
     */
    public function getDeliveryStopAttribute(): ?Stop
    {
        return $this->stops->where('type', 'delivery')->first();
    }
}
