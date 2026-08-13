<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FundWish extends Model
{
    use HasUlids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_NEEDS_INFORMATION = 'needs_information';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'fund_wishes';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'personal_contribution_completed_at' => 'immutable_datetime',
            'cost_validated_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(FundWishCategory::class, 'category_id'); }
    public function membership(): BelongsTo { return $this->belongsTo(FundMembership::class, 'fund_membership_id'); }
    public function personalContributions(): HasMany { return $this->hasMany(FundPersonalContribution::class, 'fund_wish_id')->orderByDesc('created_at'); }
    public function quoteRequests(): HasMany { return $this->hasMany(FundQuoteRequest::class, 'fund_wish_id')->orderByDesc('requested_at'); }
    public function quotes(): HasMany { return $this->hasMany(FundWishQuote::class, 'fund_wish_id')->orderBy('amount_minor'); }
    public function selectedQuote(): BelongsTo { return $this->belongsTo(FundWishQuote::class, 'selected_quote_id'); }
    public function providerOrganization(): BelongsTo { return $this->belongsTo(Organization::class, 'provider_organization_id'); }
    public function collectionSnapshot(): HasOne { return $this->hasOne(FundCollectionSnapshot::class, 'fund_wish_id'); }
    public function order(): HasOne { return $this->hasOne(FundOrder::class, 'fund_wish_id'); }
}
