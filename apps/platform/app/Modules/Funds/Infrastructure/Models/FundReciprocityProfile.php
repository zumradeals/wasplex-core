<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundReciprocityProfile extends Model
{
    use HasUlids;

    protected $table = 'fund_reciprocity_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'factors' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
