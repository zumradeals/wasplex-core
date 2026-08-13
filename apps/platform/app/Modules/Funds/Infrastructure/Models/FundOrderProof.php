<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundOrderProof extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(FundOrder::class, 'fund_order_id');
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(FundOrderMilestone::class, 'fund_order_milestone_id');
    }
}
