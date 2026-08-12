<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FundWishCategory::class, 'category_id');
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FundMembership::class, 'fund_membership_id');
    }
}
