<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentStatusHistory extends Model
{
    protected $table = 'shipment_status_history';

    public const UPDATED_AT = null;

    protected $fillable = [
        'shipment_id',
        'from_status',
        'to_status',
        'changed_by',
        'role_at_time',
        'notes',
    ];

    /** @return BelongsTo<Shipment, self> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<User, self> */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
