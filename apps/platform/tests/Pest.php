<?php

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\CapabilityGrant;
use App\Modules\Identity\Infrastructure\Models\SpaceMembership;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
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

/**
 * Grants an admin-space membership plus the given capabilities to $account,
 * self-granted the same way SeedFounderCommand bootstraps the real founder
 * (documented bootstrap exception). Shared across Identity's and Ledger's
 * admin-console tests since both gate on EnsureRecentMfa + EnsureCapability.
 *
 * @param  array<int, string>  $capabilities
 */
function grantFounderAccessForTests(Account $account, array $capabilities = ['admin.dashboard.view', 'admin.audit.view']): void
{
    $adminSpace = UserSpace::create(['space_type' => UserSpace::TYPE_ADMIN, 'status' => 'active']);

    SpaceMembership::create([
        'user_space_id' => $adminSpace->id,
        'account_id' => $account->id,
        'status' => 'active',
        'is_default' => false,
        'joined_at' => now(),
    ]);

    foreach ($capabilities as $capability) {
        CapabilityGrant::create([
            'account_id' => $account->id,
            'capability_code' => $capability,
            'status' => 'active',
            'starts_at' => now(),
            'granted_by' => $account->id,
        ]);
    }
}

/** Shared across Ledger tests (docs/chantiers/P002-CHANTIER.md): the two system/owned accounts most tests post between. */
function ledgerSuspense(): LedgerAccountReference
{
    return LedgerAccountReference::system('wasplex.suspense', 'CLEARING', 'WP');
}

function ledgerUserAvailable(string $identityAccountId = 'user-1'): LedgerAccountReference
{
    return LedgerAccountReference::forIdentityAccount('user.available.wp', $identityAccountId, 'LIABILITY_USER', 'WP');
}
