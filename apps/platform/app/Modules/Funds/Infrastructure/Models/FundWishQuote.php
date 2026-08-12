<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundWishQuote extends Model
{
    use HasUlids;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SELECTED = 'selected';

    public const STATUS_NOT_SELECTED = 'not_selected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'valid_until' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'selected_at' => 'immutable_datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(FundQuoteRequest::class, 'quote_request_id');
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
