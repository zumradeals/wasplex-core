<?php

namespace App\Modules\Ledger\Application\Data;

use App\Modules\Ledger\Domain\Enums\EntryDirection;
use App\Modules\Ledger\Domain\Exceptions\InvalidLedgerPosting;

final readonly class PostingEntry
{
    public function __construct(
        public string $accountId,
        public EntryDirection $direction,
        public int $amountMinor,
        public string $description,
        public ?string $externalReference = null,
    ) {
        if ($this->amountMinor <= 0) {
            throw new InvalidLedgerPosting('Le montant d’une écriture doit être un entier strictement positif.');
        }

        if (trim($this->accountId) === '') {
            throw new InvalidLedgerPosting('Toute écriture doit désigner un compte comptable.');
        }

        if (trim($this->description) === '') {
            throw new InvalidLedgerPosting('Toute écriture doit posséder une description.');
        }

        if (strlen($this->description) > 500) {
            throw new InvalidLedgerPosting('La description d’une écriture dépasse 500 caractères.');
        }

        if ($this->externalReference !== null && strlen($this->externalReference) > 160) {
            throw new InvalidLedgerPosting('La référence externe d’une écriture dépasse 160 caractères.');
        }
    }
}
