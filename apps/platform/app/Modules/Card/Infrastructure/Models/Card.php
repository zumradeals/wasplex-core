<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Card extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'account_id', 'offer_version_id', 'public_identifier', 'status', 'issued_at',
        'activated_at', 'expires_at', 'suspended_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'suspended_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function offerVersion(): BelongsTo
    {
        return $this->belongsTo(CardOfferVersion::class, 'offer_version_id');
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(CardQrToken::class, 'card_id');
    }
}
