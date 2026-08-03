<?php

namespace App\Modules\Ledger\Application\Data;

use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;
use DateTimeImmutable;

final readonly class PostingRequest
{
    /**
     * @param  list<PostingEntry>  $entries
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $journalCode,
        public string $type,
        public string $sourceModule,
        public string $businessReference,
        public string $idempotencyKey,
        public string $unit,
        public string $currency,
        public array $entries,
        public ?string $actorAccountId = null,
        public ?string $beneficiaryAccountId = null,
        public ?string $justification = null,
        public ?string $ruleVersion = null,
        public array $metadata = [],
        public ?string $traceId = null,
        public ?DateTimeImmutable $postedAt = null,
    ) {
        foreach ([
            'journalCode' => $this->journalCode,
            'type' => $this->type,
            'sourceModule' => $this->sourceModule,
            'businessReference' => $this->businessReference,
            'idempotencyKey' => $this->idempotencyKey,
            'unit' => $this->unit,
            'currency' => $this->currency,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidLedgerPosting("Le champ {$field} est obligatoire.");
            }
        }

        $this->assertLength('journalCode', $this->journalCode, 64);
        $this->assertLength('type', $this->type, 80);
        $this->assertLength('sourceModule', $this->sourceModule, 80);
        $this->assertLength('businessReference', $this->businessReference, 160);
        $this->assertLength('idempotencyKey', $this->idempotencyKey, 160);
        $this->assertLength('unit', $this->unit, 12);
        $this->assertLength('currency', $this->currency, 12);

        if ($this->ruleVersion !== null && strlen($this->ruleVersion) > 80) {
            throw new InvalidLedgerPosting('La version de règle dépasse 80 caractères.');
        }

        if ($this->traceId !== null && strlen($this->traceId) > 64) {
            throw new InvalidLedgerPosting('La trace technique dépasse 64 caractères.');
        }

        if (count($this->entries) < 2) {
            throw new InvalidLedgerPosting('Une transaction exige au moins deux écritures.');
        }

        if (preg_match('/^[A-Z][A-Z0-9_]{1,11}$/', strtoupper($this->unit)) !== 1) {
            throw new InvalidLedgerPosting('L’unité comptable est invalide.');
        }

        if (preg_match('/^[A-Z][A-Z0-9_]{1,11}$/', strtoupper($this->currency)) !== 1) {
            throw new InvalidLedgerPosting('La devise comptable est invalide.');
        }
    }

    private function assertLength(string $field, string $value, int $maximum): void
    {
        if (strlen($value) > $maximum) {
            throw new InvalidLedgerPosting("Le champ {$field} dépasse {$maximum} caractères.");
        }
    }
}
