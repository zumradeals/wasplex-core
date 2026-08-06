<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationEntry extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const RESULT_MATCHED = 'matched';

    public const RESULT_PARTIALLY_MATCHED = 'partially_matched';

    public const RESULT_UNMATCHED = 'unmatched';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_AMOUNT_MISMATCH = 'amount_mismatch';

    public const RESULT_STATUS_MISMATCH = 'status_mismatch';

    public const RESULT_MANUAL_REVIEW = 'manual_review';

    protected $table = 'reconciliation_entries';

    protected $fillable = [
        'run_id', 'source_module', 'source_record_id', 'provider_reference', 'amount_minor',
        'currency', 'internal_status', 'provider_status_raw', 'result_code', 'notes',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ReconciliationRun::class, 'run_id');
    }
}
