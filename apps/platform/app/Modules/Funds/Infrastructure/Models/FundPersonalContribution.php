<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FundPersonalContribution extends Model
{
    use HasUlids;

    public const SOURCE_WALLET = 'wallet';

    public const SOURCE_FUND_BALANCE = 'fund_balance';

    public $timestamps = false;

    protected $table = 'fund_personal_contributions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function wish(): BelongsTo
    {
        return $this->belongsTo(FundWish::class, 'fund_wish_id');
    }
}
