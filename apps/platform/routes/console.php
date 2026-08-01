<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('wasplex:about', function (): void {
    $this->info('Wasplex platform foundation is ready.');
})->purpose('Display the Wasplex platform foundation status');
