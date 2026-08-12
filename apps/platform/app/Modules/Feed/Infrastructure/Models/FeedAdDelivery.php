<?php

declare(strict_types=1);

namespace App\Modules\Feed\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FeedAdDelivery extends Model
{
    use HasUlids;

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_STARTED = 'started';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_HELD = 'held';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REPLAY = 'replay';

    protected $table = 'feed_ad_deliveries';

    protected $fillable = [
        'feed_session_id', 'account_id', 'campaign_id', 'organization_id', 'campaign_envelope_consumption_id',
        'economic_class', 'is_replay', 'gain_minor', 'required_duration_ms', 'visible_duration_ms',
        'quota_units', 'requires_media_end', 'media_ended_at',
        'last_heartbeat_at', 'risk_signal_count', 'last_risk_signal_code',
        'progress_percent', 'status', 'reserved_at', 'started_at', 'completed_at',
        'ledger_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'is_replay' => 'boolean',
            'gain_minor' => 'integer',
            'required_duration_ms' => 'integer',
            'visible_duration_ms' => 'integer',
            'quota_units' => 'integer',
            'requires_media_end' => 'boolean',
            'media_ended_at' => 'immutable_datetime',
            'risk_signal_count' => 'integer',
            'progress_percent' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'last_heartbeat_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Précision milliseconde explicite : le format de sauvegarde par défaut
     * d'Eloquent ('Y-m-d H:i:s', voir Grammar::getDateFormat()) tronque les
     * fractions de seconde à l'écriture quelle que soit la précision de la
     * colonne — inacceptable pour une preuve d'attention fondée sur des
     * écarts de quelques centaines de millisecondes
     * (docs/chantiers/P010-CHANTIER.md §3/§5). Sans effet sur les colonnes
     * restées en précision seconde (`reserved_at`, `completed_at`,
     * `created_at`, `updated_at`) : la fraction surnuméraire y est
     * simplement tronquée par PostgreSQL à l'écriture.
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.v';
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FeedSession::class, 'feed_session_id');
    }

    public function holds(): HasMany
    {
        return $this->hasMany(FeedAdDeliveryHold::class);
    }
}
