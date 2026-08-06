<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AdvertiserProfile extends Model
{
    use HasUlids;

    public const TYPE_INDIVIDUAL = 'individual';

    public const TYPE_BUSINESS = 'business';

    public const TYPE_AGENCY = 'agency';

    public const TYPE_INSTITUTIONAL = 'institutional_advertiser';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESTRICTED = 'restricted';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'organization_id', 'advertiser_type', 'legal_name', 'registration_number',
        'address', 'representative_account_id', 'status', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime'];
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * docs/13 §11: a restriction can target campaign creation, funding,
     * diffusion... — draft/pending_verification/verified/active can all
     * still create brands and upload media; only an explicit restriction
     * blocks new creation. Reading existing data is never blocked.
     */
    public function canCreate(): bool
    {
        return ! in_array($this->status, [self::STATUS_RESTRICTED, self::STATUS_SUSPENDED, self::STATUS_CLOSED], true);
    }
}
