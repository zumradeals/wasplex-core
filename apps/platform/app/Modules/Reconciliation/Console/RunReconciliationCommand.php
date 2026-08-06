<?php

declare(strict_types=1);

namespace App\Modules\Reconciliation\Console;

use App\Modules\Reconciliation\Application\Services\ReconciliationService;
use Illuminate\Console\Command;

final class RunReconciliationCommand extends Command
{
    protected $signature = 'reconciliation:run';

    protected $description = 'Rapproche les paiements GeniusPay internes (dépôts annonceur, abonnements) avec une revérification serveur (docs/06 §34).';

    public function handle(ReconciliationService $service): int
    {
        $run = $service->run();

        $this->info("Rapprochement {$run->id} terminé : {$run->total_checked} paiement(s) vérifié(s).");

        foreach ($run->summary as $resultCode => $count) {
            $this->line("  {$resultCode} : {$count}");
        }

        return self::SUCCESS;
    }
}
