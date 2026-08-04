<?php

namespace App\Modules\Advertising\Infrastructure\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $domain
 * @property string $status
 * @property string|null $current_version_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read AdvertisingPurposeVersion|null $currentVersion
 * @property-read Collection<int, AdvertisingPurposeVersion> $versions
 * @property-read Collection<int, AdvertisingConsent> $consents
 */
final class AdvertisingPurpose extends Model
{
    use HasUlids;

    protected $fillable = [
        'code',
        'domain',
        'status',
        'current_version_id',
    ];

    /** @return BelongsTo<AdvertisingPurposeVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AdvertisingPurposeVersion::class, 'current_version_id');
    }

    /** @return HasMany<AdvertisingPurposeVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(AdvertisingPurposeVersion::class)->latest('version');
    }

    /** @return HasMany<AdvertisingConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(AdvertisingConsent::class);
    }
}
