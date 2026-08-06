<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// docs/03-abonnements-et-classes-economiques-wasplex.md §18-19: les
// déclassements programmés et les expirations ne s'appliquent jamais
// immédiatement, seulement au terme du cycle en cours.
Schedule::command('subscriptions:apply-scheduled-downgrades')->daily();
Schedule::command('subscriptions:expire-overdue')->daily();
