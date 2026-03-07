<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreAccessRequest extends Model
{
    protected $fillable = [
        'business_name',
        'contact_name',
        'email',
        'phone',
        'branch_id',
        'address',
        'rnc',
        'description',
        'volume_estimate',
        'status',
        'reviewed_by',
        'review_notes',
    ];

    protected $casts = [
        'status' => 'string',
        'volume_estimate' => 'string',
    ];

    /** @return BelongsTo<Branch, self> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, self> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
