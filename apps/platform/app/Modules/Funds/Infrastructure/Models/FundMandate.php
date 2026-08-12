<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundMandate extends Model
{
    use HasUlids;

    protected $table = 'fund_mandates';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'accepted_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'accepted_snapshot' => 'array',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(FundMembership::class, 'fund_membership_id');
    }
}
