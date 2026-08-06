<?php

declare(strict_types=1);

use App\Modules\AdvertiserWallet\Application\Services\AdvertiserWalletQueryService;
use App\Modules\AdvertiserWallet\Application\Services\DepositService;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDepositEvent;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function fakeGeniusPayCreate(string $reference, ?string $status = null): void
{
    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => $reference,
            'checkout_url' => "https://geniuspay.ci/checkout/{$reference}",
            'status' => $status,
            'environment' => 'sandbox',
        ], 201),
    ]);
}

function fakeGeniusPayStatus(string $reference, ?string $status): void
{
    Http::fake([
        "geniuspay.ci/api/v1/merchant/payments/{$reference}" => Http::response([
            'reference' => $reference,
            'checkout_url' => "https://geniuspay.ci/checkout/{$reference}",
            'status' => $status,
            'environment' => 'sandbox',
        ], 200),
    ]);
}

function postGeniusPayWebhook(string $payload, array $headers)
{
    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    return test()->call('POST', '/api/webhooks/geniuspay', server: $server, content: $payload);
}

test('creating a deposit returns a sandbox checkout URL even when the provider status is null', function (): void {
    fakeGeniusPayCreate('SANDBOX_create1');

    registerAndLogin('advertiser1@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $deposit = app(DepositService::class)->createDeposit($organizationId, 'account-1', 17500, 'XOF');

    expect($deposit->status)->toBe(AdvertiserWalletDeposit::STATUS_AWAITING_PAYMENT);
    expect($deposit->checkout_url)->toBe('https://geniuspay.ci/checkout/SANDBOX_create1');
    expect($deposit->provider_reference)->toBe('SANDBOX_create1');
});

test('a valid signed webhook confirmed by server-side re-verification credits the wallet exactly once', function (): void {
    fakeGeniusPayCreate('SANDBOX_credit1');

    registerAndLogin('advertiser2@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $deposit = app(DepositService::class)->createDeposit($organizationId, 'account-2', 17500, 'XOF');

    fakeGeniusPayStatus('SANDBOX_credit1', 'success');

    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SANDBOX_credit1',
        'amount' => 17500,
        'currency' => 'XOF',
        'status' => 'success',
        'created_at' => time(),
    ]);

    postGeniusPayWebhook($payload, $headers)->assertOk();

    $deposit->refresh();
    expect($deposit->status)->toBe(AdvertiserWalletDeposit::STATUS_CREDITED);
    expect($deposit->ledger_transaction_id)->not->toBeNull();
    expect(LedgerTransaction::query()->where('id', $deposit->ledger_transaction_id)->value('status'))->toBe('posted');

    $balance = app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId);
    expect($balance)->toBe(17500);
});

test('a replayed webhook for an already-credited deposit does not double-credit', function (): void {
    fakeGeniusPayCreate('SANDBOX_replay1');

    registerAndLogin('advertiser3@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $deposit = app(DepositService::class)->createDeposit($organizationId, 'account-3', 5000, 'XOF');

    fakeGeniusPayStatus('SANDBOX_replay1', 'success');

    $webhook = geniusPaySignedWebhook([
        'reference' => 'SANDBOX_replay1',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'success',
        'created_at' => time(),
    ]);

    postGeniusPayWebhook($webhook['payload'], $webhook['headers'])->assertOk();
    postGeniusPayWebhook($webhook['payload'], $webhook['headers'])->assertOk();

    expect(LedgerTransaction::query()->count())->toBe(1);
    expect(app(AdvertiserWalletQueryService::class)->availableBalanceMinor($organizationId))->toBe(5000);
    expect(AdvertiserWalletDepositEvent::query()->where('status', AdvertiserWalletDepositEvent::STATUS_DUPLICATED)->count())->toBe(1);
});

test('a webhook with an invalid signature is rejected without crediting anything', function (): void {
    fakeGeniusPayCreate('SANDBOX_badsig1');

    registerAndLogin('advertiser4@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    app(DepositService::class)->createDeposit($organizationId, 'account-4', 5000, 'XOF');

    $webhook = geniusPaySignedWebhook(['reference' => 'SANDBOX_badsig1', 'amount' => 5000, 'currency' => 'XOF', 'status' => 'success', 'created_at' => time()]);
    $tamperedHeaders = $webhook['headers'];
    $tamperedHeaders['X-Webhook-Signature'] = 'not-the-real-signature';

    postGeniusPayWebhook($webhook['payload'], $tamperedHeaders)->assertStatus(401);

    expect(LedgerTransaction::query()->count())->toBe(0);
    expect(AdvertiserWalletDepositEvent::query()->where('signature_valid', false)->count())->toBe(1);
});

test('a webhook referencing an unknown deposit is acknowledged without any credit', function (): void {
    registerAndLogin('advertiser5@wasplex.test');
    createAdvertiserOrganization();

    $webhook = geniusPaySignedWebhook(['reference' => 'SANDBOX_unknown', 'amount' => 1000, 'currency' => 'XOF', 'status' => 'success', 'created_at' => time()]);

    postGeniusPayWebhook($webhook['payload'], $webhook['headers'])->assertOk();

    expect(LedgerTransaction::query()->count())->toBe(0);
    expect(AdvertiserWalletDepositEvent::query()->whereNull('deposit_id')->count())->toBe(1);
});

test('a rejected provider status marks the deposit rejected without crediting', function (): void {
    fakeGeniusPayCreate('SANDBOX_rejected1');

    registerAndLogin('advertiser6@wasplex.test');
    $organizationId = createAdvertiserOrganization();

    $deposit = app(DepositService::class)->createDeposit($organizationId, 'account-6', 5000, 'XOF');

    fakeGeniusPayStatus('SANDBOX_rejected1', 'failed');

    $webhook = geniusPaySignedWebhook(['reference' => 'SANDBOX_rejected1', 'amount' => 5000, 'currency' => 'XOF', 'status' => 'failed', 'created_at' => time()]);
    postGeniusPayWebhook($webhook['payload'], $webhook['headers'])->assertOk();

    expect($deposit->refresh()->status)->toBe(AdvertiserWalletDeposit::STATUS_REJECTED);
    expect(LedgerTransaction::query()->count())->toBe(0);
});
