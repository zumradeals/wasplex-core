<?php

declare(strict_types=1);

namespace App\Modules\Funds\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class FundAuditEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $table = 'fund_audit_events';
    protected $fillable = ['account_id', 'event_type', 'subject_type', 'subject_id', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public static function record(?string $accountId, string $eventType, string $subjectType, string $subjectId, array $payload = []): self
    {
        return self::query()->create([
            'account_id' => $accountId,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
