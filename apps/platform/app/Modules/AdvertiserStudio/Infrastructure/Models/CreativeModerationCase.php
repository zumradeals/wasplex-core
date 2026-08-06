<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserStudio\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CreativeModerationCase extends Model
{
    use HasUlids;

    // No created_at/updated_at columns: decided_at is the single,
    // explicit timestamp for this append-only record (docs/13 §55).
    public $timestamps = false;

    public const DECISION_APPROVE = 'approve';

    public const DECISION_REQUEST_CHANGES = 'request_changes';

    public const DECISION_REJECT = 'reject';

    protected $fillable = ['creative_asset_id', 'decision', 'reason', 'decided_by', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'immutable_datetime'];
    }

    public function creativeAsset(): BelongsTo
    {
        return $this->belongsTo(CreativeAsset::class);
    }
}
