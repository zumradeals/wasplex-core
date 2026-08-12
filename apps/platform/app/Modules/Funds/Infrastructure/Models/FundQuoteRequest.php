<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Identity\Infrastructure\Models\ProfessionalSpace;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class FundQuoteRequest extends Model
{
    use HasUlids;

    public const STATUS_REQUESTED = 'requested';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_SELECTED = 'selected';
    public const STATUS_NOT_SELECTED = 'not_selected';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'requirements' => 'array',
            'requested_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'responded_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function professionalSpace(): BelongsTo
    {
        return $this->belongsTo(ProfessionalSpace::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function quote(): HasOne
    {
        return $this->hasOne(FundWishQuote::class, 'quote_request_id');
    }
}
