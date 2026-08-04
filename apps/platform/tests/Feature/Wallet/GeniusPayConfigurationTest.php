<?php

use App\Modules\Identity\Domain\Enums\AccountStatus;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\GeniusPayConfigurationService;
use App\Modules\Wallet\Infrastructure\Models\PaymentProviderConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.geniuspay.mode', 'sandbox');
    config()->set('services.geniuspay.base_url', 'https://geniuspay.ci/api/v1/merchant');
    config()->set('services.geniuspay.checkout_hosts', ['geniuspay.ci', 'pay.genius.ci']);
    config()->set('services.geniuspay.api_key', '');
    config()->set('services.geniuspay.api_secret', '');
    config()->set('services.geniuspay.webhook_secret', '');
    config()->set('services.geniuspay.allow_production_activation', false);
});

it('stores GeniusPay secrets encrypted and only exposes masks', function (): void {
    $account = geniusPayConfigurationAccount();
    $service = app(GeniusPayConfigurationService::class);

    $configuration = $service->save('sandbox', [
        'api_key' => 'pk_sandbox_secret_1234',
        'api_secret' => 'sk_sandbox_secret_5678',
        'webhook_secret' => 'whsec_sandbox_secret_9012',
    ], $account);

    $raw = DB::table('payment_provider_configurations')->where('id', $configuration->id)->first();
    $summary = $service->summary('sandbox');

    expect($raw)->not->toBeNull()
        ->and($raw?->api_key)->not->toBe('pk_sandbox_secret_1234')
        ->and($raw?->api_secret)->not->toBe('sk_sandbox_secret_5678')
        ->and($raw?->webhook_secret)->not->toBe('whsec_sandbox_secret_9012')
        ->and($summary['configured'])->toBeTrue()
        ->and($summary['apiKeyMask'])->toBe('••••••••1234')
        ->and($summary['apiSecretMask'])->toBe('••••••••5678')
        ->and($summary['webhookSecretMask'])->toBe('••••••••9012')
        ->and($configuration->toArray())->not->toHaveKeys(['api_key', 'api_secret', 'webhook_secret']);
});

it('tests then activates the sandbox configuration', function (): void {
    $account = geniusPayConfigurationAccount();
    $service = app(GeniusPayConfigurationService::class);
    $service->save('sandbox', [
        'api_key' => 'pk_sandbox_admin',
        'api_secret' => 'sk_sandbox_admin',
        'webhook_secret' => 'whsec_sandbox_admin',
    ], $account);

    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/account' => Http::response([
            'success' => true,
            'data' => [
                'status' => 'active',
                'environment' => 'sandbox',
            ],
        ]),
    ]);

    $result = $service->test('sandbox', $account);
    $active = $service->activate('sandbox', $account);
    $runtime = $service->runtime();

    expect($result['ok'])->toBeTrue()
        ->and($active->is_active)->toBeTrue()
        ->and($active->last_test_status)->toBe('success')
        ->and($runtime['environment'])->toBe('sandbox')
        ->and($runtime['api_key'])->toBe('pk_sandbox_admin')
        ->and($runtime['webhook_secret'])->toBe('whsec_sandbox_admin');
});

it('allows production credentials to be prepared but blocks activation', function (): void {
    $account = geniusPayConfigurationAccount();
    $service = app(GeniusPayConfigurationService::class);
    $service->save('production', [
        'api_key' => 'pk_live_admin',
        'api_secret' => 'sk_live_admin',
        'webhook_secret' => 'whsec_live_admin',
    ], $account);

    Http::fake([
        'https://geniuspay.ci/api/v1/merchant/account' => Http::response([
            'success' => true,
            'data' => [
                'status' => 'active',
                'environment' => 'live',
            ],
        ]),
    ]);

    expect($service->test('production', $account)['ok'])->toBeTrue()
        ->and(fn () => $service->activate('production', $account))
        ->toThrow(ValidationException::class, 'activation reste verrouillée');

    expect(PaymentProviderConfiguration::query()
        ->where('environment', 'production')
        ->where('is_active', true)
        ->exists())->toBeFalse();
});

it('keeps the existing environment configuration active until database activation', function (): void {
    config()->set('services.geniuspay.api_key', 'pk_sandbox_fallback');
    config()->set('services.geniuspay.api_secret', 'sk_sandbox_fallback');
    config()->set('services.geniuspay.webhook_secret', 'whsec_sandbox_fallback');

    $summary = app(GeniusPayConfigurationService::class)->summary('sandbox');

    expect($summary['configured'])->toBeTrue()
        ->and($summary['isActive'])->toBeTrue()
        ->and($summary['storedInDatabase'])->toBeFalse();
});

function geniusPayConfigurationAccount(): Account
{
    return Account::query()->create([
        'status' => AccountStatus::Active,
        'password_hash' => str_repeat('x', 60),
        'locale' => 'fr',
        'timezone' => 'Africa/Abidjan',
    ]);
}
