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

/**
 * Registers an advertiser organization for the already-logged-in account
 * (call after registerAndLogin()) and switches the active space to it, so
 * EnsureActiveAdvertiserOrganization resolves it on the next request
 * (docs/chantiers/P003-CHANTIER.md). Returns the organization id.
 */
function createAdvertiserOrganization(string $orgName = 'Demo Org', string $countryCode = 'CI'): string
{
    $organizationId = test()->postJson('/api/organizations', [
        'name' => $orgName,
        'type' => 'advertiser',
        'country_code' => $countryCode,
    ])->assertCreated()->json('id');

    $spaces = test()->getJson('/api/me/spaces')->assertOk()->json('spaces');
    $advertiserSpace = collect($spaces)->firstWhere('organization_id', $organizationId);

    test()->postJson("/api/me/spaces/{$advertiserSpace['user_space_id']}/switch")->assertOk();

    return $organizationId;
}

/**
 * Builds a GeniusPay webhook body + signed headers matching the real
 * contract documented in docs/chantiers/HOTFIX-P003-GENIUSPAY-SANDBOX.md:
 * environment at the payload root, business fields under data.*, signature
 * HMAC-SHA256 over "timestamp.payload".
 *
 * @param  array<string, mixed>  $data
 * @return array{payload: string, headers: array<string, string>}
 */
function geniusPaySignedWebhook(array $data, string $eventType = 'payment.succeeded', string $environment = 'sandbox', ?int $timestamp = null): array
{
    $timestamp ??= time();
    $payload = json_encode([
        'environment' => $environment,
        'event_type' => $eventType,
        'timestamp' => $timestamp,
        'data' => $data,
    ], JSON_THROW_ON_ERROR);

    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) config('services.geniuspay.webhook_secret'));

    return [
        'payload' => $payload,
        'headers' => [
            'X-Webhook-Signature' => $signature,
            'X-Webhook-Timestamp' => (string) $timestamp,
        ],
    ];
}
