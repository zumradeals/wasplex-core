<?php

declare(strict_types=1);

use App\Modules\Funds\Application\Services\FundCollectionService;
use App\Modules\Funds\Application\Services\FundCollectionWaveService;
use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundCollectionDebit;
use App\Modules\Funds\Infrastructure\Models\FundCollectionParticipant;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMandate;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWish;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\Organization;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Money\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function p014wAccount(string $email): Account
{
    registerAndLogin($email);
    $account = Account::query()
        ->whereHas('identifiers', fn ($query) => $query->where('value', $email))
        ->firstOrFail();
    $account->update(['country_code' => 'CI']);
    test()->postJson('/api/logout')->assertNoContent();

    return $account->refresh();
}

function p014wSubscription(Account $account): UserSubscription
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();

    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'P014W-'.Str::upper(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);
    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'p014w-'.Str::lower(Str::random(8)),
        'name' => 'Abonnement P014-F vague',
        'created_at' => now(),
    ]);
    DB::table('subscription_plan_versions')->insert([
        'id' => $planVersionId,
        'plan_id' => $planId,
        'status' => 'published',
        'price_minor' => 5000,
        'currency' => 'XOF',
        'duration_days' => 30,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
    ]);

    $subscription = UserSubscription::query()->create([
        'account_id' => $account->id,
        'plan_version_id' => $planVersionId,
        'economic_class_id' => $economicClassId,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now()->subDays(15),
        'current_period_end' => now()->addDays(15),
    ]);
    DB::table('subscription_entitlements')->insert([
        'id' => (string) Str::ulid(),
        'user_subscription_id' => $subscription->id,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => true,
        'created_at' => now(),
    ]);

    return $subscription;
}

function p014wProgram(): array
{
    $program = FundProgram::query()->create([
        'code' => 'p014w-'.Str::lower(Str::random(8)),
        'name' => 'Fonds P014-F vagues',
        'status' => FundProgram::STATUS_ACTIVE,
    ]);
    $version = FundProgramVersion::query()->create([
        'fund_program_id' => $program->id,
        'version' => 1,
        'currency' => 'XOF',
        'duration_days' => 365,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 2,
        'personal_contribution_percent' => 10,
        'min_debit_minor' => 1,
        'max_debit_minor' => 100000,
        'daily_cap_minor' => 100000,
        'monthly_cap_minor' => 500000,
        'annual_cap_minor' => 5000000,
        'wasplex_fee_minor' => 10,
        'notice_hours' => 1,
        'grace_period_days' => 7,
        'arrears_grace_days' => 7,
        'max_simultaneous_collections' => 1,
        'status' => 'published',
        'published_at' => now(),
    ]);

    return [$program, $version];
}

function p014wMembership(Account $account, FundProgram $program, FundProgramVersion $version): FundMembership
{
    $subscription = p014wSubscription($account);
    $membership = FundMembership::query()->create([
        'account_id' => $account->id,
        'fund_program_id' => $program->id,
        'program_version_id' => $version->id,
        'user_subscription_id' => $subscription->id,
        'status' => FundMembership::STATUS_ACTIVE,
        'started_at' => now(),
    ]);
    FundMandate::query()->create([
        'fund_membership_id' => $membership->id,
        'personal_cap_minor' => 100000,
        'is_active' => true,
        'terms_version' => 'P014-F-wave-test',
        'accepted_at' => now(),
        'accepted_snapshot' => ['personal_cap_minor' => 100000],
    ]);

    return $membership->load(['account', 'version', 'mandate', 'subscription.entitlements']);
}

function p014wWish(Account $beneficiary, FundMembership $membership): FundWish
{
    $category = FundWishCategory::query()->create([
        'code' => 'p014w-cat-'.Str::lower(Str::random(8)),
        'name' => 'Mobilité vague',
        'icon' => '🏍️',
        'is_active' => true,
    ]);
    $provider = Organization::query()->create([
        'name' => 'Prestataire P014-F vague',
        'type' => 'professional',
        'country_code' => 'CI',
        'status' => 'active',
        'created_by' => $beneficiary->id,
    ]);
    $wish = FundWish::query()->create([
        'account_id' => $beneficiary->id,
        'fund_membership_id' => $membership->id,
        'category_id' => $category->id,
        'status' => FundWish::STATUS_APPROVED,
        'title' => 'Moto P014-F seconde vague',
        'description' => 'Vœu utilisé pour tester le remplacement sécurisé d’un déficit de collecte.',
        'country_code' => 'CI',
        'city' => 'Abidjan',
        'estimated_amount_minor' => 200,
        'validated_amount_minor' => 200,
        'currency' => 'XOF',
        'required_personal_contribution_minor' => 100,
        'provider_organization_id' => $provider->id,
        'reviewed_at' => now(),
        'cost_validated_at' => now(),
    ]);

    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'P014_F_WAVE_TEST_PERSONAL',
        sourceModule: 'tests',
        idempotencyKey: 'p014f-wave-personal-'.$wish->id,
        entries: [
            LedgerEntryInput::debit(
                LedgerAccountReference::system('p014f.wave.test.personal.clearing', 'ASSET', 'WP'),
                Money::of(100, 'WP'),
            ),
            LedgerEntryInput::credit(
                LedgerAccountReference::forIdentityAccount(
                    'fund.wish.personal.'.$wish->id.'.wp',
                    $beneficiary->id,
                    'LIABILITY_USER',
                    'WP',
                ),
                Money::of(100, 'WP'),
            ),
        ],
    ));

    return $wish->refresh();
}

function p014wFund(Account $account, int $amount): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'P014_F_WAVE_TEST_FUND',
        sourceModule: 'tests',
        idempotencyKey: 'p014f-wave-fund-'.$account->id.'-'.$amount.'-'.Str::random(5),
        entries: [
            LedgerEntryInput::debit(
                LedgerAccountReference::system('p014f.wave.test.fund.clearing', 'ASSET', 'WP'),
                Money::of($amount, 'WP'),
            ),
            LedgerEntryInput::credit(
                LedgerAccountReference::forIdentityAccount(
                    FundContributionService::FUND_BALANCE_ACCOUNT_CODE,
                    $account->id,
                    'LIABILITY_USER',
                    'WP',
                ),
                Money::of($amount, 'WP'),
            ),
        ],
    ));
}

it('creates a replacement wave with a fresh notice, unique fee and exact global target', function (): void {
    [$program, $version] = p014wProgram();
    $beneficiary = p014wAccount('p014w-beneficiary@wasplex.test');
    $beneficiaryMembership = p014wMembership($beneficiary, $program, $version);
    $wish = p014wWish($beneficiary, $beneficiaryMembership);

    $memberA = p014wAccount('p014w-a@wasplex.test');
    p014wMembership($memberA, $program, $version);
    $memberB = p014wAccount('p014w-b@wasplex.test');
    p014wMembership($memberB, $program, $version);

    p014wFund($memberA, 60);
    p014wFund($memberB, 25);

    $collections = app(FundCollectionService::class);
    $snapshot = $collections->createSnapshot($wish, $beneficiary->id);
    $snapshot->update(['scheduled_at' => now()->subMinute()]);
    $first = $collections->execute($snapshot->refresh(), $beneficiary->id);

    expect($first->status)->toBe(FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED)
        ->and((int) $first->participants->sum('solidarity_paid_minor'))->toBe(65)
        ->and((int) $first->participants->sum('fee_paid_minor'))->toBe(20);

    $memberC = p014wAccount('p014w-c@wasplex.test');
    p014wMembership($memberC, $program, $version);
    p014wFund($memberA, 100);
    p014wFund($memberB, 100);
    p014wFund($memberC, 100);

    $waves = app(FundCollectionWaveService::class);
    $wave = $waves->createSecondWave($first->refresh(), $beneficiary->id);

    expect($wave['wave_number'])->toBe(2)
        ->and($wave['wave_target_minor'])->toBe(35)
        ->and($wave['solidarity_due_minor'])->toBe(35)
        ->and($wave['fees_due_minor'])->toBe(10)
        ->and(strlen((string) $wave['wave_hash']))->toBe(64)
        ->and($first->refresh()->status)->toBe(FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED)
        ->and(FundArrear::query()->where('snapshot_id', $snapshot->id)->where('status', FundArrear::STATUS_WAIVED)->count())->toBeGreaterThanOrEqual(1);

    $waveParticipants = FundCollectionParticipant::query()
        ->where('snapshot_id', $snapshot->id)
        ->where('wave_number', 2)
        ->get();
    expect((int) $waveParticipants->whereIn('account_id', [$memberA->id, $memberB->id])->sum('fee_due_minor'))->toBe(0)
        ->and((int) $waveParticipants->firstWhere('account_id', $memberC->id)->fee_due_minor)->toBe(10);

    $sameWave = $waves->createSecondWave($snapshot->refresh(), $beneficiary->id);
    expect($sameWave['wave_number'])->toBe(2)
        ->and($sameWave['wave_hash'])->toBe($wave['wave_hash']);
    expect(fn () => $collections->execute($snapshot->refresh(), $beneficiary->id))
        ->toThrow(RuntimeException::class, 'Une vague complémentaire existe');
    expect(fn () => $waves->executeWave($snapshot->refresh(), 2, $beneficiary->id))
        ->toThrow(RuntimeException::class, 'Le préavis de cette vague n’est pas encore terminé.');

    FundCollectionParticipant::query()
        ->where('snapshot_id', $snapshot->id)
        ->where('wave_number', 2)
        ->update(['wave_scheduled_at' => now()->subMinute()]);

    $executed = $waves->executeWave($snapshot->refresh(), 2, $beneficiary->id);

    expect($executed['snapshot_status'])->toBe(FundCollectionSnapshot::STATUS_FUNDED)
        ->and($executed['global_collected_minor'])->toBe(100)
        ->and($executed['global_remaining_minor'])->toBe(0)
        ->and((int) FundCollectionParticipant::query()->where('snapshot_id', $snapshot->id)->sum('solidarity_paid_minor'))->toBe(100)
        ->and((int) FundCollectionDebit::query()->where('snapshot_id', $snapshot->id)->sum('debited_solidarity_minor'))->toBe(100)
        ->and((int) FundCollectionDebit::query()->where('snapshot_id', $snapshot->id)->sum('debited_fee_minor'))->toBe(30);

    $stable = $collections->execute($snapshot->refresh(), $beneficiary->id);
    expect($stable->status)->toBe(FundCollectionSnapshot::STATUS_FUNDED)
        ->and((int) FundCollectionDebit::query()->where('snapshot_id', $snapshot->id)->sum('debited_solidarity_minor'))->toBe(100);

    $participant = FundCollectionParticipant::query()->where('snapshot_id', $snapshot->id)->where('wave_number', 2)->firstOrFail();
    expect(fn () => DB::table('fund_collection_debits')->insert([
        'id' => (string) Str::ulid(),
        'snapshot_id' => $snapshot->id,
        'participant_id' => $participant->id,
        'account_id' => $participant->account_id,
        'idempotency_key' => 'p014f-wave-overcollect-'.Str::random(8),
        'requested_solidarity_minor' => 1,
        'requested_fee_minor' => 0,
        'debited_solidarity_minor' => 1,
        'debited_fee_minor' => 0,
        'arrears_minor' => 0,
        'ledger_transaction_id' => null,
        'status' => FundCollectionDebit::STATUS_SUCCESS,
        'failure_code' => null,
        'attempted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
