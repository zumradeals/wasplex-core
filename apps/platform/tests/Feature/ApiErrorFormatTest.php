<?php

declare(strict_types=1);

test('an unknown API route returns the standard Wasplex error shape', function (): void {
    $response = $this->getJson('/api/does-not-exist');

    $response->assertStatus(404)->assertJsonStructure(['code', 'message', 'details', 'trace_id']);
});
