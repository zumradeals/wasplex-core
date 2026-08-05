<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;

/**
 * The only internal contract other modules may use to write to the Grand
 * Livre (docs/chantiers/P002-CHANTIER.md Inclus: "contrat interne de
 * posting"). There is no HTTP route for this — invariant: "les clients
 * HTTP ne disposent d'aucune route de posting".
 */
interface LedgerPostingContract
{
    /**
     * @throws LedgerImbalanceException if debits ≠ credits for any currency
     * @throws LedgerIdempotencyConflictException if the idempotency key was
     *                                            already used with different economic content
     */
    public function post(PostLedgerTransaction $command): LedgerTransaction;
}
