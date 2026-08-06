<?php

declare(strict_types=1);

namespace App\Modules\Matching\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class MatchingDecision extends Model
{
    use HasUlids;

    public const DECISION_ELIGIBLE = 'eligible';

    public const DECISION_INELIGIBLE = 'ineligible';

    public const DECISION_WITHHELD = 'withheld';

    protected $table = 'matching_decisions';

    protected $fillable = [
        'campaign_id', 'campaign_version_id', 'account_id', 'matching_configuration_id',
        'decision', 'reason_codes', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_codes' => 'array',
            'decided_at' => 'immutable_datetime',
        ];
    }
}
