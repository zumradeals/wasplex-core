<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Registers and logs an account in through the real HTTP endpoints so the
 * accompanying account_sessions row exists (EnsureSessionNotRevoked reads
 * it). Two things are required for the session cookie to actually survive
 * across separate postJson()/getJson() calls within one test, mirroring a
 * browser's cookie jar:
 *
 *  - withCredentials(): *Json() request helpers omit cookies entirely
 *    unless this is called (it mirrors XHR's withCredentials flag).
 *  - the cookie must be replayed via withUnencryptedCookie() with the
 *    already-encrypted Set-Cookie value verbatim — withCookie() would
 *    encrypt it a second time, and SESSION_DRIVER=array (Laravel's stock
 *    testing default) would drop it anyway since it never survives across
 *    requests, hence phpunit.xml pins SESSION_DRIVER=redis for tests.
 */
function registerAndLogin(string $identifierValue, string $password = 'Password123!', string $country = 'CI'): void
{
    test()->withCredentials();

    test()->postJson('/api/register', [
        'identifier_type' => 'email',
        'identifier_value' => $identifierValue,
        'password' => $password,
        'country_code' => $country,
    ])->assertCreated();

    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifierValue,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');

    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }
}
