<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardQrToken extends Model
{
    use HasUlids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_USED = 'used';

    public const STATUS_REVOKED = 'revoked';

    public const PURPOSE_PUBLIC_IDENTITY = 'public_identity';

    public const PURPOSE_RECEIVE_PAYMENT = 'receive_payment';

    protected $fillable = [
        'card_id',
        'purpose',
        'token_hash',
        'status',
        'amount_minor',
        'currency',
        'note',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }
}
