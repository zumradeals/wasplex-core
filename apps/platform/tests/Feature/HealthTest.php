<?php

it('exposes the liveness endpoint', function (): void {
    $this->get('/up')->assertOk();
});

it('returns structured health data with a trace id', function (): void {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonPath('data.service', 'wasplex-platform')
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonStructure(['data' => ['time', 'trace_id']])
        ->assertHeader('X-Trace-Id')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
