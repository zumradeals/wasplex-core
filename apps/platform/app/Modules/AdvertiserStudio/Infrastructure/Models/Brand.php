<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Brand extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'advertiser_profile_id', 'name', 'legal_name', 'sector', 'description', 'slogan',
        'status', 'country_code', 'website', 'primary_logo_asset_id', 'secondary_logo_asset_id',
        'verified_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }

    public function advertiserProfile(): BelongsTo
    {
        return $this->belongsTo(AdvertiserProfile::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(BrandColor::class);
    }

    public function typographies(): HasMany
    {
        return $this->hasMany(BrandTypography::class);
    }

    public function guidelines(): HasOne
    {
        return $this->hasOne(BrandGuideline::class);
    }

    public function creativeAssets(): HasMany
    {
        return $this->hasMany(CreativeAsset::class);
    }
}
