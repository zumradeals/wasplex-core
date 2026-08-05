<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Domain\ValueObjects;

/**
 * @param  LedgerEntryInput[]  $entries
 * @param  array<string, mixed>|null  $metadata
 */
final class PostLedgerTransaction
{
    public function __construct(
        public readonly string $type,
        public readonly string $sourceModule,
        public readonly string $idempotencyKey,
        public readonly array $entries,
        public readonly ?string $businessReference = null,
        public readonly ?string $createdBy = null,
        public readonly ?array $metadata = null,
        public readonly string $journalCode = 'GRAND_LIVRE_PRINCIPAL',
        public readonly string $ruleVersion = 'v1',
    ) {}
}
