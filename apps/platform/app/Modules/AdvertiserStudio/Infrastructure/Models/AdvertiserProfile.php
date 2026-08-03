<?php

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $advertiser_space_id
 * @property string $organization_id
 * @property string $created_by_account_id
 * @property string|null $updated_by_account_id
 * @property string $display_name
 * @property string|null $legal_name
 * @property string|null $industry
 * @property string|null $website_url
 * @property string $country_code
 * @property string|null $city
 * @property string|null $phone
 * @property string|null $description
 * @property string $status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read UserSpace $space
 * @property-read Organization $organization
 * @property-read Account $creator
 * @property-read Account|null $updater
 */
final class AdvertiserProfile extends Model
{
    use HasUlids;

    protected $fillable = [
        'advertiser_space_id',
        'organization_id',
        'created_by_account_id',
        'updated_by_account_id',
        'display_name',
        'legal_name',
        'industry',
        'website_url',
        'country_code',
        'city',
        'phone',
        'description',
        'status',
    ];

    /** @return BelongsTo<UserSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(UserSpace::class, 'advertiser_space_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'created_by_account_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'updated_by_account_id');
    }
}
