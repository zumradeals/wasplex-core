<?php

use App\Http\Controllers\TechnicalPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', TechnicalPageController::class)->name('technical');
