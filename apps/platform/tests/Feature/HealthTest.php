<?php

declare(strict_types=1);

test('the technical page responds successfully', function (): void {
    $response = $this->get('/');

    $response->assertOk();
});

test('the framework health endpoint responds', function (): void {
    $this->get('/up')->assertOk();
});

test('the API health endpoint reports database and redis connectivity', function (): void {
    $response = $this->getJson('/api/health');

    $response->assertOk()->assertJsonStructure([
        'status',
        'checks' => ['database' => ['ok'], 'redis' => ['ok']],
        'trace_id',
        'timestamp',
    ]);

    expect($response->json('checks.database.ok'))->toBeTrue();
    expect($response->json('checks.redis.ok'))->toBeTrue();
});

test('every response carries a trace id and it is reused when supplied', function (): void {
    $response = $this->withHeader('X-Trace-Id', 'test-trace-id')->getJson('/api/health');

    $response->assertHeader('X-Trace-Id', 'test-trace-id');
    expect($response->json('trace_id'))->toBe('test-trace-id');
});

test('every response carries baseline security headers', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
});
