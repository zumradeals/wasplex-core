<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Shared\Http\AppException;

/**
 * Docs/06-wallet-et-grand-livre-wasplex.md §38: "celui qui crée une
 * correction ne l'approuve pas" — separation of duties between
 * wallet.correction.propose and wallet.correction.approve.
 */
final class LedgerCorrectionSelfApprovalException extends AppException
{
    public function __construct()
    {
        parent::__construct(
            'LEDGER_CORRECTION_SELF_APPROVAL_DENIED',
            "Le compte ayant proposé la correction ne peut pas l'approuver.",
            [],
            403,
        );
    }
}
