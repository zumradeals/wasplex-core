<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Docs/chantiers/P002-CHANTIER.md Inclus: "audit des consultations et
 * journalisation des divergences". Ledger does not own a table in
 * Identity's account_audit_events (docs/CLAUDE.md #6: module data
 * ownership) and P002's migrations list is fixed to exactly 7 ledger_*
 * tables, so this audit trail is written to the structured JSON log
 * channel established in P000 (config/logging.php, "structured") rather
 * than to a new table.
 */
final class LedgerAuditLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $action, array $context = [], ?Request $request = null): void
    {
        Log::channel('structured')->info($action, [
            ...$context,
            'domain' => 'ledger',
            'trace_id' => $request?->attributes->get('trace_id'),
        ]);
    }
}
