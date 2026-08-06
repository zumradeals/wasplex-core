<?php

declare(strict_types=1);

return [
    // How far back a reconciliation pass looks at already-terminal payments
    // (docs/06 §34 "inversion prestataire" — a provider can reverse a
    // payment after it was already credited; a pass must still be able to
    // catch that within a reasonable window, not only strictly pending ones).
    'lookback_days' => (int) env('RECONCILIATION_LOOKBACK_DAYS', 7),

    // Amounts within this tolerance (minor units) are still considered a
    // match — GeniusPay's sandbox has been observed to round fees
    // differently in rare cases (docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md).
    'amount_tolerance_minor' => (int) env('RECONCILIATION_AMOUNT_TOLERANCE_MINOR', 0),
];
