<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $phone
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsToMany<Branch> */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withTimestamps();
    }

    /** @return HasOne<Store> */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }

    /** @return HasOne<Courier> */
    public function courier(): HasOne
    {
        return $this->hasOne(Courier::class);
    }

    /** @return HasMany<PushSubscription> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /**
     * Get the active branch id from the current Sanctum token.
     * Stored in personal_access_tokens.name as "branch:{id}" prefix.
     */
    public function getActiveBranchId(): ?int
    {
        $token = $this->currentAccessToken();
        if ($token && str_starts_with((string) $token->name, 'branch:')) {
            return (int) str_replace('branch:', '', (string) $token->name);
        }

        // If admin has only one branch, return it automatically
        if ($this->hasRole('admin')) {
            $branches = $this->branches;
            if ($branches->count() === 1) {
                return (int) $branches->first()->id;
            }
        }

        return null;
    }
}
