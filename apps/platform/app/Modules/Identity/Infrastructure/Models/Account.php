<?php

namespace App\Modules\Identity\Infrastructure\Models;

use App\Modules\Identity\Domain\Enums\AccountStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property string $id
 * @property AccountStatus $status
 * @property string $password_hash
 * @property string $locale
 * @property string $timezone
 * @property CarbonImmutable|null $last_authenticated_at
 * @property CarbonInterface|null $created_at
 * @property-read PersonalProfile|null $profile
 * @property-read Collection<int, AccountIdentifier> $identifiers
 * @property-read Collection<int, UserSpace> $spaces
 * @property-read Collection<int, AccountSession> $sessions
 * @property-read Collection<int, AccountDevice> $devices
 * @property-read Collection<int, CapabilityGrant> $capabilityGrants
 * @property-read Collection<int, SpaceMembership> $spaceMemberships
 */
class Account extends Authenticatable
{
    use HasUlids;

    protected $fillable = [
        'status',
        'password_hash',
        'locale',
        'timezone',
        'last_authenticated_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    protected function casts(): array
    {
        return [
            'status' => AccountStatus::class,
            'password_hash' => 'hashed',
            'last_authenticated_at' => 'immutable_datetime',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /** @return HasMany<AccountIdentifier, $this> */
    public function identifiers(): HasMany
    {
        return $this->hasMany(AccountIdentifier::class);
    }

    /** @return HasOne<PersonalProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(PersonalProfile::class);
    }

    /** @return HasMany<UserSpace, $this> */
    public function spaces(): HasMany
    {
        return $this->hasMany(UserSpace::class);
    }

    /** @return HasMany<AccountSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(AccountSession::class);
    }

    /** @return HasMany<AccountDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(AccountDevice::class);
    }

    /** @return HasMany<CapabilityGrant, $this> */
    public function capabilityGrants(): HasMany
    {
        return $this->hasMany(CapabilityGrant::class);
    }

    /** @return HasMany<SpaceMembership, $this> */
    public function spaceMemberships(): HasMany
    {
        return $this->hasMany(SpaceMembership::class);
    }
}
