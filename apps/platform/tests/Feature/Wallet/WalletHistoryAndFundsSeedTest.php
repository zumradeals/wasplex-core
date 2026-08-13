<?php

declare(strict_types=1);

use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
});

function walletHistoryAccount(string $identifier): Account
{
    return Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))->firstOrFail();
}

function walletHistoryCredit(string $accountId, int $amountMinor, string $key, string $module = 'tests'): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_WALLET_CREDIT',
        sourceModule: $module,
        idempotencyKey: $key,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of($amountMinor, 'WP'), 'Crédit test'),
            LedgerEntryInput::credit(ledgerUserAvailable($accountId), Money::of($amountMinor, 'WP'), 'Crédit test'),
        ],
    ));
}

function walletHistoryMakeEligibleSubscription(Account $account, string $classCode = EconomicClass::CODE_PREMIUM): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($query) => $query->where('code', $classCode))
        ->firstOrFail();

    $subscription = UserSubscription::query()->create([
        'account_id' => $account->id,
        'plan_version_id' => $planVersion->id,
        'economic_class_id' => $economicClass->id,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now(),
        'current_period_end' => now()->addDays(30),
    ]);

    SubscriptionEntitlement::query()->create([
        'user_subscription_id' => $subscription->id,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => true,
    ]);
}

it('seeds three complete fund programs and categories without overwriting admin changes', function (): void {
    Artisan::call('funds:seed-programs');

    expect(FundProgram::query()->count())->toBe(3)
        ->and(FundProgramVersion::query()->count())->toBe(3)
        ->and(FundWishCategory::query()->count())->toBe(10);

    $essential = FundProgram::query()->where('code', 'fonds-essentiel')->firstOrFail();
    $version = $essential->publishedVersion();

    expect($version)->not->toBeNull()
        ->and((int) $version->membership_fee_minor)->toBe(1000)
        ->and((int) $version->max_wishes_per_period)->toBe(2)
        ->and((int) $version->max_wish_amount_minor)->toBe(150000)
        ->and((int) $version->personal_contribution_percent)->toBe(30)
        ->and($version->eligible_subscription_classes)->toBe(['PREMIUM', 'GOLD', 'PLATINUM']);

    $version->update(['membership_fee_minor' => 1234]);
    Artisan::call('funds:seed-programs');

    expect(FundProgram::query()->count())->toBe(3)
        ->and(FundProgramVersion::query()->count())->toBe(3)
        ->and((int) $version->refresh()->membership_fee_minor)->toBe(1234);
});

it('charges the real membership fee from Wallet and classifies it in Fonds history', function (): void {
    Artisan::call('funds:seed-programs');
    registerAndLogin('wallet-funds-membership@example.com');
    $account = walletHistoryAccount('wallet-funds-membership@example.com');
    walletHistoryMakeEligibleSubscription($account);
    walletHistoryCredit($account->id, 1500, 'wallet-history-membership-credit');

    $program = FundProgram::query()->where('code', 'fonds-essentiel')->firstOrFail();

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 500,
        'accept_mandate' => true,
    ])->assertCreated()
        ->assertJsonPath('status', FundMembership::STATUS_ACTIVE);

    expect(app(UserWalletQueryService::class)->balanceMinor($account->id))->toBe(500);

    test()->getJson('/api/me/wallet/history?summary=1')
        ->assertOk()
        ->assertJsonFragment(['key' => 'funds', 'label' => 'Historique Fonds', 'count' => 1]);

    test()->getJson('/api/me/wallet/history?category=funds&per_page=5')
        ->assertOk()
        ->assertJsonPath('history.data.0.type', 'FUND_MEMBERSHIP_FEE')
        ->assertJsonPath('history.data.0.source_module', 'funds')
        ->assertJsonPath('history.data.0.amount_minor', 1000);
});

it('enforces the seeded program subscription class before charging any membership fee', function (): void {
    Artisan::call('funds:seed-programs');
    registerAndLogin('funds-class-eligibility@example.com');
    $account = walletHistoryAccount('funds-class-eligibility@example.com');
    walletHistoryMakeEligibleSubscription($account, EconomicClass::CODE_PREMIUM);
    walletHistoryCredit($account->id, 5000, 'wallet-history-class-credit');

    $plus = FundProgram::query()->where('code', 'fonds-plus')->firstOrFail();

    test()->postJson('/api/funds/membership', [
        'program_id' => $plus->id,
        'personal_cap_minor' => 1000,
        'accept_mandate' => true,
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Votre niveau d’abonnement Wasplex n’est pas éligible à ce programme Fonds.');

    expect(app(UserWalletQueryService::class)->balanceMinor($account->id))->toBe(5000)
        ->and(FundMembership::query()->where('account_id', $account->id)->count())->toBe(0);
});

it('keeps standard Wallet history accordions even when a future module has no movement yet', function (): void {
    registerAndLogin('wallet-history-categories@example.com');
    $account = walletHistoryAccount('wallet-history-categories@example.com');
    walletHistoryCredit($account->id, 300, 'wallet-history-feed-credit', 'feed');
    walletHistoryCredit($account->id, 200, 'wallet-history-wallet-credit', 'wallet');

    $response = test()->getJson('/api/me/wallet/history?summary=1')->assertOk();
    $keys = collect($response->json('categories'))->pluck('key')->all();

    expect($keys)->toContain('advertising', 'wallet', 'funds', 'card', 'live');
    $response->assertJsonFragment(['key' => 'advertising', 'count' => 1]);
    $response->assertJsonFragment(['key' => 'wallet', 'count' => 1]);
    $response->assertJsonFragment(['key' => 'card', 'count' => 0]);
    $response->assertJsonFragment(['key' => 'live', 'count' => 0]);
});
