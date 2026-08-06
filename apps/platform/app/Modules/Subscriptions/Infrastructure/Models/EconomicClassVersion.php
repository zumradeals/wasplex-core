<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EconomicClassVersion extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'economic_class_versions';

    protected $fillable = [
        'economic_class_id', 'quota_monthly', 'weight_percent', 'coefficient',
        'country_code', 'currency', 'effective_from', 'effective_to',
    ];

    protected function casts(): array
    {
        return [
            'quota_monthly' => 'integer',
            'weight_percent' => 'decimal:2',
            'coefficient' => 'decimal:4',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
        ];
    }

    public function economicClass(): BelongsTo
    {
        return $this->belongsTo(EconomicClass::class, 'economic_class_id');
    }
}
