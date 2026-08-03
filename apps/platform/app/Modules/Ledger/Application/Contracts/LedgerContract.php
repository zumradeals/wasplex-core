<?php

namespace App\Modules\Ledger\Application\Contracts;

use App\Modules\Ledger\Application\Data\PostedTransaction;
use App\Modules\Ledger\Application\Data\PostingRequest;

interface LedgerContract
{
    public function post(PostingRequest $request): PostedTransaction;

    public function reverse(
        string $transactionId,
        string $idempotencyKey,
        string $justification,
        ?string $actorAccountId = null,
        ?string $traceId = null,
    ): PostedTransaction;
}
