<?php

declare(strict_types=1);

namespace App\Modules\Card\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardOfferVersion extends Model
{
    use HasUlids;

    protected $fillable = [
        'offer_id', 'version', 'status', 'price_minor', 'currency', 'duration_days',
        'supports_virtual', 'supports_physical', 'services', 'effective_from', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'duration_days' => 'integer',
            'supports_virtual' => 'boolean',
            'supports_physical' => 'boolean',
            'services' => 'array',
            'effective_from' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CardOffer::class, 'offer_id');
    }
}
