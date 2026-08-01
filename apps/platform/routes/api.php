<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'data' => [
            'service' => 'wasplex-platform',
            'status' => 'ok',
            'time' => now()->toIso8601String(),
            'trace_id' => request()->attributes->get('trace_id'),
        ],
    ]);
})->name('api.health');
