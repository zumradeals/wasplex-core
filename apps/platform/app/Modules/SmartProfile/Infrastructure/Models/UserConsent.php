<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserConsent extends Model
{
    use HasUlids;

    public const STATUS_GRANTED = 'granted';

    public const STATUS_DENIED = 'denied';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $table = 'user_consents';

    protected $fillable = [
        'account_id', 'consent_purpose_id', 'consent_purpose_version_id',
        'status', 'channel', 'granted_at', 'withdrawn_at',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'immutable_datetime',
            'withdrawn_at' => 'immutable_datetime',
        ];
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(ConsentPurpose::class, 'consent_purpose_id');
    }

    public function purposeVersion(): BelongsTo
    {
        return $this->belongsTo(ConsentPurposeVersion::class, 'consent_purpose_version_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_GRANTED;
    }
}
