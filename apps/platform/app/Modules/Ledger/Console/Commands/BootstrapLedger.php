<?php

namespace App\Modules\Ledger\Console\Commands;

use App\Modules\Ledger\Application\Services\LedgerCatalog;
use App\Modules\Ledger\Domain\Enums\EntryDirection;
use Illuminate\Console\Command;

final class BootstrapLedger extends Command
{
    protected $signature = 'ledger:bootstrap-core';

    protected $description = 'Initialise le catalogue comptable minimal et idempotent de P002.';

    public function handle(LedgerCatalog $catalog): int
    {
        $catalog->createAccountType('ASSET', 'Actif', EntryDirection::Debit, 'Ressources contrôlées par Wasplex.');
        $catalog->createAccountType('LIABILITY', 'Passif', EntryDirection::Credit, 'Valeurs dues aux utilisateurs et partenaires.');
        $catalog->createAccountType('REVENUE', 'Revenu', EntryDirection::Credit, 'Revenus reconnus par Wasplex.');
        $catalog->createAccountType('EXPENSE', 'Charge', EntryDirection::Debit, 'Charges et distributions reconnues.');
        $catalog->createAccountType('EQUITY', 'Capitaux propres', EntryDirection::Credit, 'Comptes de capitaux propres.');

        $catalog->createJournal('GENERAL', 'Journal général');
        $catalog->createJournal('CORRECTIONS', 'Corrections et compensations');

        $this->info('Catalogue P002 prêt : 5 types de comptes et 2 journaux vérifiés.');
        $this->warn('Aucun solde et aucune écriture financière réelle n’ont été créés.');

        return self::SUCCESS;
    }
}
