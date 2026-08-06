<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ConsentPurpose extends Model
{
    use HasUlids;

    public const CODE_ADVERTISING_PERSONALIZATION = 'advertising_personalization';

    public const CODE_SMART_PROFILE_USAGE = 'smart_profile_usage';

    public const CODE_APPROXIMATE_LOCATION_TARGETING = 'approximate_location_targeting';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    protected $table = 'consent_purposes';

    protected $fillable = ['code', 'status'];

    public function versions(): HasMany
    {
        return $this->hasMany(ConsentPurposeVersion::class);
    }

    public function publishedVersion(): ?ConsentPurposeVersion
    {
        return $this->versions()
            ->where('status', ConsentPurposeVersion::STATUS_PUBLISHED)
            ->orderByDesc('version_number')
            ->first();
    }
}
