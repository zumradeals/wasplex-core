<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ConsentEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const TYPE_GRANTED = 'granted';

    public const TYPE_DENIED = 'denied';

    public const TYPE_WITHDRAWN = 'withdrawn';

    public const TYPE_EXPIRED = 'expired';

    public const TYPE_SUPERSEDED = 'superseded';

    protected $table = 'consent_events';

    protected $fillable = [
        'user_consent_id', 'account_id', 'consent_purpose_id',
        'consent_purpose_version_id', 'event_type', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'consent_purpose_id');
    }
}
