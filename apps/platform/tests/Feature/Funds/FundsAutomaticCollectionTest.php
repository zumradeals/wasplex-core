<?php

declare(strict_types=1);

use App\Modules\Funds\Application\Services\FundCollectionService;
use App\Modules\Funds\Application\Services\FundContributionService;
use App\Modules\Funds\Infrastructure\Models\FundArrear;
use App\Modules\Funds\Infrastructure\Models\FundCollectionDebit;
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
use App\Modules\Ledger\Infrastructure\Models\LedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function p014dAccount(string $email): Account
{
    registerAndLogin($email);
    $account = Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', $email))->firstOrFail();
    $account->update(['country_code' => 'CI']);
    test()->postJson('/api/logout')->assertNoContent();

    return $account->refresh();
}

function p014dSubscription(Account $account): UserSubscription
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();

    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'P014D-'.Str::upper(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);
    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'p014d-'.Str::lower(Str::random(8)),
        'name' => 'Abonnement P014-D',
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

function p014dProgram(int $fee = 10, int $noticeHours = 24): array
{
    $program = FundProgram::query()->create([
        'code' => 'p014d-'.Str::lower(Str::random(8)),
        'name' => 'Fonds collectif P014-D',
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
        'wasplex_fee_minor' => $fee,
        'notice_hours' => $noticeHours,
        'grace_period_days' => 7,
        'arrears_grace_days' => 7,
        'max_simultaneous_collections' => 1,
        'status' => 'published',
        'published_at' => now(),
    ]);

    return [$program, $version];
}

function p014dMembership(Account $account, FundProgram $program, FundProgramVersion $version, int $cap = 100000): FundMembership
{
    $subscription = p014dSubscription($account);
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
        'personal_cap_minor' => $cap,
        'is_active' => true,
        'terms_version' => 'P014-D-test',
        'accepted_at' => now(),
        'accepted_snapshot' => ['personal_cap_minor' => $cap],
    ]);

    return $membership->load(['account', 'version', 'mandate', 'subscription.entitlements']);
}

function p014dWish(Account $beneficiary, FundMembership $membership, int $validatedCost, int $personalContribution): FundWish
{
    $category = FundWishCategory::query()->create([
        'code' => 'p014d-cat-'.Str::lower(Str::random(8)),
        'name' => 'Mobilité',
        'icon' => '🏍️',
        'is_active' => true,
    ]);
    $provider = Organization::query()->create([
        'name' => 'Prestataire P014-D',
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
        'title' => 'Moto validée P014-D',
        'description' => 'Besoin réel et validé utilisé pour tester la collecte automatique Fonds.',
        'country_code' => 'CI',
        'city' => 'Abidjan',
        'estimated_amount_minor' => $validatedCost,
        'validated_amount_minor' => $validatedCost,
        'currency' => 'XOF',
        'required_personal_contribution_minor' => $personalContribution,
        'provider_organization_id' => $provider->id,
        'reviewed_at' => now(),
        'cost_validated_at' => now(),
    ]);

    if ($personalContribution > 0) {
        app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
            type: 'P014_D_TEST_PERSONAL_CONTRIBUTION',
            sourceModule: 'tests',
            idempotencyKey: 'p014d-personal-'.$wish->id,
            entries: [
                LedgerEntryInput::debit(
                    LedgerAccountReference::system('p014d.test.clearing.personal', 'ASSET', 'WP'),
                    Money::of($personalContribution, 'WP'),
                ),
                LedgerEntryInput::credit(
                    LedgerAccountReference::forIdentityAccount(
                        'fund.wish.personal.'.$wish->id.'.wp',
                        $beneficiary->id,
                        'LIABILITY_USER',
                        'WP',
                    ),
                    Money::of($personalContribution, 'WP'),
                ),
            ],
        ));
    }

    return $wish->refresh();
}

function p014dFundBalance(Account $account, int $amount): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'P014_D_TEST_FUND_BALANCE',
        sourceModule: 'tests',
        idempotencyKey: 'p014d-fund-balance-'.$account->id.'-'.$amount.'-'.Str::random(5),
        entries: [
            LedgerEntryInput::debit(
                LedgerAccountReference::system('p014d.test.clearing.fund', 'ASSET', 'WP'),
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

it('freezes a deterministic snapshot that excludes the beneficiary and never overcollects', function (): void {
    [$program, $version] = p014dProgram();
    $beneficiary = p014dAccount('p014d-beneficiary@wasplex.test');
    $beneficiaryMembership = p014dMembership($beneficiary, $program, $version);
    $wish = p014dWish($beneficiary, $beneficiaryMembership, 400, 100);

    foreach (['a', 'b', 'c'] as $suffix) {
        $member = p014dAccount("p014d-{$suffix}@wasplex.test");
        p014dMembership($member, $program, $version);
    }

    $service = app(FundCollectionService::class);
    $snapshot = $service->createSnapshot($wish, $beneficiary->id);
    $again = $service->createSnapshot($wish, $beneficiary->id);

    expect($snapshot->id)->toBe($again->id)
        ->and($snapshot->participant_count)->toBe(3)
        ->and((int) $snapshot->collective_amount_minor)->toBe(300)
        ->and((int) $snapshot->participants->sum('solidarity_due_minor'))->toBe(300)
        ->and((int) $snapshot->participants->sum('fee_due_minor'))->toBe(30)
        ->and($snapshot->participants->pluck('account_id')->contains($beneficiary->id))->toBeFalse()
        ->and(strlen((string) $snapshot->snapshot_hash))->toBe(64)
        ->and(FundCollectionSnapshot::query()->where('fund_wish_id', $wish->id)->count())->toBe(1);
});

it('removes a participant whose mandate cannot support the theoretical debit then recalculates', function (): void {
    [$program, $version] = p014dProgram(10);
    $beneficiary = p014dAccount('p014d-cap-beneficiary@wasplex.test');
    $wish = p014dWish($beneficiary, p014dMembership($beneficiary, $program, $version), 300, 100);

    $low = p014dAccount('p014d-cap-low@wasplex.test');
    p014dMembership($low, $program, $version, 50);
    $strongA = p014dAccount('p014d-cap-a@wasplex.test');
    p014dMembership($strongA, $program, $version, 500);
    $strongB = p014dAccount('p014d-cap-b@wasplex.test');
    p014dMembership($strongB, $program, $version, 500);

    $snapshot = app(FundCollectionService::class)->createSnapshot($wish, $beneficiary->id);

    expect($snapshot->participant_count)->toBe(2)
        ->and($snapshot->participants->pluck('account_id')->contains($low->id))->toBeFalse()
        ->and($snapshot->participants->pluck('solidarity_due_minor')->sort()->values()->all())->toBe([100, 100]);
});

it('honours advance notice before any automatic debit', function (): void {
    [$program, $version] = p014dProgram(10, 24);
    $beneficiary = p014dAccount('p014d-notice-beneficiary@wasplex.test');
    $wish = p014dWish($beneficiary, p014dMembership($beneficiary, $program, $version), 200, 100);
    $member = p014dAccount('p014d-notice-member@wasplex.test');
    p014dMembership($member, $program, $version);
    p014dFundBalance($member, 500);

    $service = app(FundCollectionService::class);
    $snapshot = $service->createSnapshot($wish, $beneficiary->id);

    expect(fn () => $service->execute($snapshot, $beneficiary->id))
        ->toThrow(RuntimeException::class, 'Le préavis de collecte n’est pas encore terminé.');
    expect(LedgerTransaction::query()->where('type', 'FUND_AUTOMATIC_COLLECTION_DEBIT')->count())->toBe(0);
});

it('charges the fixed fee only once, supports partial debit, creates arrears and finishes after regularization', function (): void {
    [$program, $version] = p014dProgram(10, 1);
    $beneficiary = p014dAccount('p014d-partial-beneficiary@wasplex.test');
    $wish = p014dWish($beneficiary, p014dMembership($beneficiary, $program, $version), 200, 100);
    $full = p014dAccount('p014d-partial-full@wasplex.test');
    p014dMembership($full, $program, $version);
    $partial = p014dAccount('p014d-partial-low@wasplex.test');
    p014dMembership($partial, $program, $version);
    p014dFundBalance($full, 100);
    p014dFundBalance($partial, 25);

    $service = app(FundCollectionService::class);
    $snapshot = $service->createSnapshot($wish, $beneficiary->id);
    $snapshot->update(['scheduled_at' => now()->subMinute()]);
    $first = $service->execute($snapshot->refresh(), $beneficiary->id);

    $partialParticipant = $first->participants->firstWhere('account_id', $partial->id);
    expect($first->status)->toBe(FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED)
        ->and((int) $partialParticipant->solidarity_paid_minor)->toBe(15)
        ->and((int) $partialParticipant->fee_paid_minor)->toBe(10)
        ->and((int) $partialParticipant->arrears_minor)->toBe(35)
        ->and($partialParticipant->arrear->status)->toBe(FundArrear::STATUS_OPEN);

    p014dFundBalance($partial, 35);
    $second = $service->execute($first->refresh(), $beneficiary->id);
    $partialParticipant = $second->participants->firstWhere('account_id', $partial->id);

    expect($second->status)->toBe(FundCollectionSnapshot::STATUS_FUNDED)
        ->and((int) $partialParticipant->solidarity_paid_minor)->toBe(50)
        ->and((int) $partialParticipant->fee_paid_minor)->toBe(10)
        ->and((int) $partialParticipant->arrears_minor)->toBe(0)
        ->and($partialParticipant->arrear->status)->toBe(FundArrear::STATUS_SETTLED)
        ->and((int) FundCollectionDebit::query()->where('account_id', $partial->id)->sum('debited_fee_minor'))->toBe(10);

    $transactionsBefore = LedgerTransaction::query()->where('type', 'FUND_AUTOMATIC_COLLECTION_DEBIT')->count();
    $service->execute($second->refresh(), $beneficiary->id);
    expect(LedgerTransaction::query()->where('type', 'FUND_AUTOMATIC_COLLECTION_DEBIT')->count())->toBe($transactionsBefore);
});

it('never charges a Wasplex fee when no solidarity can be debited', function (): void {
    [$program, $version] = p014dProgram(10, 1);
    $beneficiary = p014dAccount('p014d-zero-beneficiary@wasplex.test');
    $wish = p014dWish($beneficiary, p014dMembership($beneficiary, $program, $version), 150, 100);
    $member = p014dAccount('p014d-zero-member@wasplex.test');
    p014dMembership($member, $program, $version);

    $service = app(FundCollectionService::class);
    $snapshot = $service->createSnapshot($wish, $beneficiary->id);
    $snapshot->update(['scheduled_at' => now()->subMinute()]);
    $result = $service->execute($snapshot->refresh(), $beneficiary->id);
    $participant = $result->participants->first();

    expect((int) $participant->fee_paid_minor)->toBe(0)
        ->and((int) $participant->solidarity_paid_minor)->toBe(0)
        ->and((int) $participant->arrears_minor)->toBe(50)
        ->and((int) FundCollectionDebit::query()->sum('debited_fee_minor'))->toBe(0)
        ->and(LedgerTransaction::query()->where('type', 'FUND_AUTOMATIC_COLLECTION_DEBIT')->count())->toBe(0);
});
