<?php

namespace App\Modules\Ledger\Application\Data;

final readonly class PostedTransaction
{
    public function __construct(
        public string $transactionId,
        public bool $replayed = false,
    ) {}
}
