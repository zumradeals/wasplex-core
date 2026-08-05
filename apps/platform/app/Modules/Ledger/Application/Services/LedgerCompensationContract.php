<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;

/**
 * Docs/chantiers/P002-CHANTIER.md Inclus: "contrat interne de posting,
 * consultation et compensation" — the only way to correct a posted
 * transaction is a new, linked, inverse-direction transaction (invariant:
 * "une correction inverse les sens dans une nouvelle transaction"), gated
 * by two-person separation of duties (capacités wallet.correction.propose
 * / wallet.correction.approve, docs/06 §38).
 */
interface LedgerCompensationContract
{
    /**
     * Creates a pending_approval reversal of $originalTransactionId. Not yet
     * economically real — invisible to balance computations until approved.
     */
    public function proposeReversal(string $originalTransactionId, string $reason, string $proposedBy): LedgerTransaction;

    /**
     * Posts the pending reversal, making it permanent and immutable.
     *
     * @throws LedgerCorrectionSelfApprovalException if $approvedBy === the proposer
     */
    public function approve(string $pendingTransactionId, string $approvedBy): LedgerTransaction;

    public function reject(string $pendingTransactionId, string $rejectedBy, string $reason): LedgerTransaction;
}
