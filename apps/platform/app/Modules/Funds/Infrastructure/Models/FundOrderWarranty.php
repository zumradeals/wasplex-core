<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FundOrderWarranty extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FundOrder::class, 'fund_order_id');
    }

    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'provider_organization_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(FundWarrantyClaim::class, 'fund_order_warranty_id')->latest('opened_at');
    }
}
