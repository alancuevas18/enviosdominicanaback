<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'address',
        'phone',
        'active',
        'notes',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /** @return BelongsToMany<User> */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withTimestamps();
    }

    /** @return HasMany<Store> */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /** @return HasMany<Courier> */
    public function couriers(): HasMany
    {
        return $this->hasMany(Courier::class);
    }

    /** @return HasMany<Shipment> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return HasMany<StoreAccessRequest> */
    public function accessRequests(): HasMany
    {
        return $this->hasMany(StoreAccessRequest::class);
    }

    /** @return HasMany<Route> */
    public function routes(): HasMany
    {
        return $this->hasMany(Route::class);
    }
}
