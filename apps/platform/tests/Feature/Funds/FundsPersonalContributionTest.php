<?php

declare(strict_types=1);

use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundPersonalContribution;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function p014bAccount(string $identifier): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))
        ->firstOrFail();
}

function p014bPaidSubscription(Account $account): string
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();
    $subscriptionId = (string) Str::ulid();

    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'PREMIUM-'.Str::lower(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);
    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'premium-'.Str::lower(Str::random(8)),
        'name' => 'Premium test Fonds P014-B',
        'created_at' => now(),
    ]);
    DB::table('subscription_plan_versions')->insert([
        'id' => $planVersionId,
        'plan_id' => $planId,
        'status' => 'published',
        'price_minor' => 500000,
        'currency' => 'XOF',
        'duration_days' => 30,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
    ]);
    DB::table('user_subscriptions')->insert([
        'id' => $subscriptionId,
        'account_id' => $account->id,
        'plan_version_id' => $planVersionId,
        'economic_class_id' => $economicClassId,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->subDays(30),
        'current_period_end' => now()->addDays(30),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('subscription_entitlements')->insert([
        'id' => (string) Str::ulid(),
        'user_subscription_id' => $subscriptionId,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => true,
        'created_at' => now(),
    ]);

    return $subscriptionId;
}

function p014bProgram(): FundProgram
{
    $program = FundProgram::query()->create([
        'code' => 'solidarite-b-'.Str::lower(Str::random(8)),
        'name' => 'Fonds P014-B',
        'status' => FundProgram::STATUS_ACTIVE,
        'sort_order' => 1,
    ]);

    FundProgramVersion::query()->create([
        'fund_program_id' => $program->id,
        'version' => 1,
        'currency' => 'XOF',
        'membership_fee_minor' => 0,
        'duration_days' => 365,
        'minimum_subscription_age_days' => 0,
        'max_active_wishes' => 2,
        'max_wishes_per_period' => 4,
        'max_wish_amount_minor' => 20000000,
        'personal_contribution_percent' => 10,
        'min_debit_minor' => 100,
        'max_debit_minor' => 10000,
        'daily_cap_minor' => 10000,
        'monthly_cap_minor' => 50000,
        'annual_cap_minor' => 500000,
        'wasplex_fee_minor' => 0,
        'notice_hours' => 24,
        'grace_period_days' => 7,
        'eligible_subscription_classes' => ['PREMIUM'],
        'status' => 'published',
        'published_at' => now(),
    ]);

    return $program;
}

function p014bCategory(): FundWishCategory
{
    return FundWishCategory::query()->create([
        'code' => 'projet-b-'.Str::lower(Str::random(8)),
        'name' => 'Projet familial',
        'icon' => 'home',
        'description' => 'Catégorie de test P014-B.',
        'is_active' => true,
        'requires_sensitive_documents' => false,
        'sort_order' => 1,
    ]);
}

function p014bCreditWallet(Account $account, int $amountMinor): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'P014_B_TEST_WALLET_CREDIT',
        sourceModule: 'tests',
        idempotencyKey: 'p014-b-credit-'.$account->id.'-'.$amountMinor.'-'.Str::random(6),
        entries: [
            LedgerEntryInput::debit(
                LedgerAccountReference::system('p014b.test.clearing', 'ASSET', 'WP'),
                Money::of($amountMinor, 'WP'),
            ),
            LedgerEntryInput::credit(
                LedgerAccountReference::forIdentityAccount(
                    UserWalletQueryService::AVAILABLE_ACCOUNT_CODE,
                    $account->id,
                    UserWalletQueryService::ACCOUNT_TYPE_CODE,
                    'WP',
                ),
                Money::of($amountMinor, 'WP'),
            ),
        ],
    ));
}

function p014bJoinAndWish(string $email, int $estimatedAmount = 10000): array
{
    registerAndLogin($email);
    $account = p014bAccount($email);
    $subscriptionId = p014bPaidSubscription($account);
    $program = p014bProgram();
    $category = p014bCategory();

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated();

    $wishId = test()->postJson('/api/funds/wishes', [
        'category_id' => $category->id,
        'title' => 'Projet de contribution P014-B',
        'description' => 'Un besoin concret et vérifiable pour tester les apports personnels progressifs.',
        'city' => 'Abidjan',
        'estimated_amount_minor' => $estimatedAmount,
    ])->assertCreated()->json('id');

    return [$account, $subscriptionId, FundWish::query()->findOrFail($wishId)];
}

it('moves value from available Wallet to Fund Balance through a balanced ledger transaction', function (): void {
    registerAndLogin('funds-balance@wasplex.test');
    $account = p014bAccount('funds-balance@wasplex.test');
    p014bPaidSubscription($account);
    $program = p014bProgram();
    p014bCreditWallet($account, 1000);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated();

    test()->postJson('/api/funds/balance/fund', [
        'amount_minor' => 400,
        'idempotency_key' => 'balance-transfer-1',
    ])->assertOk()
        ->assertJsonPath('wallet_available_minor', 600)
        ->assertJsonPath('fund_balance_minor', 400);

    test()->getJson('/api/funds')->assertOk()
        ->assertJsonPath('balances.wallet_available_minor', 600)
        ->assertJsonPath('balances.fund_balance_minor', 400)
        ->assertJsonPath('balances.wp_to_xof', 1);

    expect(LedgerTransaction::query()->where('type', 'FUND_BALANCE_TRANSFER')->count())->toBe(1);
});

it('never makes Wallet or Fund Balance negative when funds are insufficient', function (): void {
    registerAndLogin('funds-insufficient@wasplex.test');
    $account = p014bAccount('funds-insufficient@wasplex.test');
    p014bPaidSubscription($account);
    $program = p014bProgram();
    p014bCreditWallet($account, 100);

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 5000,
        'accept_mandate' => true,
    ])->assertCreated();

    test()->postJson('/api/funds/balance/fund', [
        'amount_minor' => 101,
        'idempotency_key' => 'balance-too-high',
    ])->assertStatus(422);

    test()->getJson('/api/funds')->assertOk()
        ->assertJsonPath('balances.wallet_available_minor', 100)
        ->assertJsonPath('balances.fund_balance_minor', 0);
});

it('builds a progressive wish contribution from Fund Balance and marks completion', function (): void {
    [$account, , $wish] = p014bJoinAndWish('funds-progress@wasplex.test', 10000);
    p014bCreditWallet($account, 2000);
    test()->postJson("/api/funds/wishes/{$wish->id}/submit")->assertOk();

    test()->postJson('/api/funds/balance/fund', [
        'amount_minor' => 1000,
        'idempotency_key' => 'progress-fund-balance',
    ])->assertOk();

    test()->postJson("/api/funds/wishes/{$wish->id}/contributions", [
        'amount_minor' => 400,
        'source' => FundPersonalContribution::SOURCE_FUND_BALANCE,
        'idempotency_key' => 'wish-progress-1',
    ])->assertOk()
        ->assertJsonPath('required_personal_contribution_minor', 1000)
        ->assertJsonPath('personal_contribution_minor', 400)
        ->assertJsonPath('personal_contribution_remaining_minor', 600)
        ->assertJsonPath('personal_contribution_progress_percent', 40);

    test()->postJson("/api/funds/wishes/{$wish->id}/contributions", [
        'amount_minor' => 600,
        'source' => FundPersonalContribution::SOURCE_FUND_BALANCE,
        'idempotency_key' => 'wish-progress-2',
    ])->assertOk()
        ->assertJsonPath('personal_contribution_minor', 1000)
        ->assertJsonPath('personal_contribution_remaining_minor', 0)
        ->assertJsonPath('personal_contribution_progress_percent', 100);

    expect(FundPersonalContribution::query()->where('fund_wish_id', $wish->id)->count())->toBe(2);
    expect($wish->fresh()->personal_contribution_completed_at)->not->toBeNull();
});

it('is idempotent and refuses overfunding a wish', function (): void {
    [$account, , $wish] = p014bJoinAndWish('funds-idempotent@wasplex.test', 10000);
    p014bCreditWallet($account, 2000);
    test()->postJson("/api/funds/wishes/{$wish->id}/submit")->assertOk();

    $payload = [
        'amount_minor' => 500,
        'source' => FundPersonalContribution::SOURCE_WALLET,
        'idempotency_key' => 'wish-same-operation',
    ];

    test()->postJson("/api/funds/wishes/{$wish->id}/contributions", $payload)->assertOk()
        ->assertJsonPath('personal_contribution_minor', 500);
    test()->postJson("/api/funds/wishes/{$wish->id}/contributions", $payload)->assertOk()
        ->assertJsonPath('personal_contribution_minor', 500);

    expect(FundPersonalContribution::query()->where('fund_wish_id', $wish->id)->count())->toBe(1);
    expect(LedgerTransaction::query()->where('type', 'FUND_WISH_PERSONAL_CONTRIBUTION')->count())->toBe(1);

    test()->postJson("/api/funds/wishes/{$wish->id}/contributions", [
        'amount_minor' => 501,
        'source' => FundPersonalContribution::SOURCE_WALLET,
        'idempotency_key' => 'wish-overfund',
    ])->assertStatus(422);
});

it('allows an existing committed wish to be regularized during subscription grace but not a draft', function (): void {
    [$account, $subscriptionId, $committedWish] = p014bJoinAndWish('funds-grace-contribution@wasplex.test', 10000);
    p014bCreditWallet($account, 2000);
    test()->postJson("/api/funds/wishes/{$committedWish->id}/submit")->assertOk();

    $category = p014bCategory();
    $draftWishId = test()->postJson('/api/funds/wishes', [
        'category_id' => $category->id,
        'title' => 'Brouillon non engagé',
        'description' => 'Ce brouillon existe avant la grâce mais ne constitue pas encore un engagement Fonds.',
        'estimated_amount_minor' => 10000,
    ])->assertCreated()->json('id');

    DB::table('user_subscriptions')->where('id', $subscriptionId)->update([
        'status' => UserSubscription::STATUS_EXPIRED,
        'current_period_end' => now()->subMinute(),
        'updated_at' => now(),
    ]);

    test()->getJson('/api/funds')->assertOk()
        ->assertJsonPath('membership.status', FundMembership::STATUS_GRACE_PERIOD);

    test()->postJson("/api/funds/wishes/{$committedWish->id}/contributions", [
        'amount_minor' => 300,
        'source' => FundPersonalContribution::SOURCE_WALLET,
        'idempotency_key' => 'grace-existing-wish',
    ])->assertOk()->assertJsonPath('personal_contribution_minor', 300);

    test()->postJson("/api/funds/wishes/{$draftWishId}/contributions", [
        'amount_minor' => 100,
        'source' => FundPersonalContribution::SOURCE_WALLET,
        'idempotency_key' => 'grace-draft-blocked',
    ])->assertStatus(422);
});
