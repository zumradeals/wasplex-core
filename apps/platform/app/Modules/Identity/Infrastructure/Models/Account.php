<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as AuthenticatableUser;

/**
 * @property string $id
 * @property string $status
 */
final class Account extends AuthenticatableUser implements Authenticatable
{
    use HasUlids;

    protected $fillable = [
        'status',
        'password',
        'country_code',
        'language',
        'timezone',
        'restricted_at',
    ];

    public function isRestricted(): bool
    {
        return $this->restricted_at !== null;
    }

    protected $hidden = [
        'password',
        'totp_secret',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'totp_secret' => 'encrypted',
            'totp_enabled_at' => 'datetime',
            'verified_at' => 'datetime',
            'restricted_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(AccountIdentifier::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(PersonalProfile::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(AccountDevice::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(AccountSession::class);
    }

    public function capabilityGrants(): HasMany
    {
        return $this->hasMany(CapabilityGrant::class);
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function primaryIdentifierLabel(): string
    {
        $identifier = $this->identifiers()->where('is_primary', true)->first()
            ?? $this->identifiers()->first();

        return $identifier?->value ?? $this->id;
    }
}
