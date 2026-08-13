<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FundOrder extends Model
{
    use HasUlids;

    public const STATUS_ISSUED = 'issued';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'beneficiary_confirmed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(FundWishQuote::class, 'quote_id');
    }

    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'provider_organization_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(FundOrderMilestone::class, 'fund_order_id')->orderBy('ordinal');
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(FundOrderProof::class, 'fund_order_id')->latest('submitted_at');
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(FundOrderDispute::class, 'fund_order_id')->latest('opened_at');
    }
}
