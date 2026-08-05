<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Console;

use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccountType;
use App\Modules\Ledger\Infrastructure\Models\LedgerJournal;
use Illuminate\Console\Command;

/**
 * Docs/chantiers/P002-CHANTIER.md Inclus: "commande idempotente
 * d'initialisation du catalogue comptable minimal". Seeds the families
 * listed in docs/06-wallet-et-grand-livre-wasplex.md §7, the single main
 * journal, and the cross-cutting system accounts needed before any module
 * can post a balanced transaction. The catalog is explicitly designed to
 * grow additively as new modules need new account types/system accounts —
 * P003 added LIABILITY_ADVERTISER and wasplex.cash.clearing (docs/06 §9-10)
 * for the advertiser deposit flow, without touching the 9 families already
 * shipped in P002.
 */
final class SeedLedgerCatalogCommand extends Command
{
    protected $signature = 'ledger:seed-catalog';

    protected $description = 'Initialise le catalogue comptable minimal du Grand Livre (idempotent).';

    /** @var array<int, array{code: string, label: string, normal_balance: string, description: string}> */
    private const ACCOUNT_TYPES = [
        ['code' => 'ASSET', 'label' => 'Actifs', 'normal_balance' => 'debit', 'description' => 'Actifs Wasplex.'],
        ['code' => 'LIABILITY_USER', 'label' => 'Passifs utilisateurs', 'normal_balance' => 'credit', 'description' => 'Valeur due aux utilisateurs (ex. user.available.wp).'],
        ['code' => 'LIABILITY_ADVERTISER', 'label' => 'Passifs annonceurs', 'normal_balance' => 'credit', 'description' => 'Budget publicitaire dû aux annonceurs (ex. advertiser.budget.available).'],
        ['code' => 'REVENUE', 'label' => 'Revenus Wasplex', 'normal_balance' => 'credit', 'description' => 'Revenus reconnus par Wasplex.'],
        ['code' => 'EXPENSE', 'label' => 'Charges et distributions', 'normal_balance' => 'debit', 'description' => 'Charges et distributions de valeur.'],
        ['code' => 'RESERVATION', 'label' => 'Comptes de réservation', 'normal_balance' => 'credit', 'description' => 'Valeur temporairement bloquée.'],
        ['code' => 'CLEARING', 'label' => 'Comptes de compensation', 'normal_balance' => 'debit', 'description' => 'Comptes de suspense/rapprochement (ex. wasplex.suspense).'],
        ['code' => 'PARTNER', 'label' => 'Comptes de partenaires', 'normal_balance' => 'credit', 'description' => 'Comptes liés aux partenaires et à la Carte.'],
        ['code' => 'FONDS', 'label' => 'Comptes de Fonds', 'normal_balance' => 'credit', 'description' => 'Sous-domaine comptable Fonds.'],
        ['code' => 'REFUND', 'label' => 'Comptes de remboursement', 'normal_balance' => 'debit', 'description' => 'Obligations de remboursement.'],
    ];

    public function handle(): int
    {
        $journal = LedgerJournal::query()->firstOrCreate(
            ['code' => LedgerJournal::CODE_MAIN],
            ['label' => 'Grand livre principal', 'status' => 'active'],
        );
        $this->info("Journal : {$journal->code}");

        foreach (self::ACCOUNT_TYPES as $type) {
            LedgerAccountType::query()->firstOrCreate(['code' => $type['code']], $type);
        }
        $this->info(count(self::ACCOUNT_TYPES).' types de comptes disponibles.');

        $clearing = LedgerAccountType::query()->where('code', 'CLEARING')->firstOrFail();
        $asset = LedgerAccountType::query()->where('code', 'ASSET')->firstOrFail();

        foreach (['wasplex.suspense' => $clearing, 'wasplex.rounding' => $clearing, 'wasplex.cash.clearing' => $asset] as $code => $type) {
            LedgerAccount::query()->firstOrCreate(
                ['code' => $code, 'owner_type' => LedgerAccount::OWNER_TYPE_SYSTEM, 'owner_id' => LedgerAccount::SYSTEM_OWNER_ID],
                ['account_type_id' => $type->id, 'currency' => 'WP'],
            );
        }
        $this->info('Comptes système : wasplex.suspense, wasplex.rounding, wasplex.cash.clearing.');

        return self::SUCCESS;
    }
}
