<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('wasplex:about', function (): void {
    $this->info('Wasplex platform foundation is ready.');
})->purpose('Display the Wasplex platform foundation status');

Schedule::command('wallet:expire-reservations')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();
