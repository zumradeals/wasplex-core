<?php

declare(strict_types=1);

use App\Modules\Funds\Application\Services\FundRealizationService;
use App\Modules\Funds\Infrastructure\Models\FundCollectionSnapshot;
use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundOrder;
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
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

beforeEach(function (): void {
    $this->artisan('ledger:seed-catalog')->assertSuccessful();
});

function p014eAccount(string $email): Account
{
    registerAndLogin($email);
    $account = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', $email))->firstOrFail();
    $account->update(['country_code' => 'CI']);
    test()->postJson('/api/logout')->assertNoContent();

    return $account->refresh();
}

function p014eMembership(Account $account): array
{
    $economicClassId = (string) Str::ulid();
    $planId = (string) Str::ulid();
    $planVersionId = (string) Str::ulid();
    DB::table('economic_classes')->insert([
        'id' => $economicClassId,
        'code' => 'P014E-'.Str::upper(Str::random(8)),
        'status' => 'active',
        'created_at' => now(),
    ]);
    DB::table('subscription_plans')->insert([
        'id' => $planId,
        'code' => 'p014e-'.Str::lower(Str::random(8)),
        'name' => 'Abonnement P014-E',
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
        'started_at' => now()->subDays(10),
        'current_period_end' => now()->addDays(20),
    ]);

    $program = FundProgram::query()->create([
        'code' => 'p014e-'.Str::lower(Str::random(8)),
        'name' => 'Programme P014-E',
        'status' => FundProgram::STATUS_ACTIVE,
    ]);
    $version = FundProgramVersion::query()->create([
        'fund_program_id' => $program->id,
        'version' => 1,
        'currency' => 'XOF',
        'duration_days' => 365,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 2,
        'personal_contribution_percent' => 30,
        'min_debit_minor' => 1,
        'max_debit_minor' => 100000,
        'wasplex_fee_minor' => 100,
        'notice_hours' => 24,
        'grace_period_days' => 7,
        'arrears_grace_days' => 7,
        'max_simultaneous_collections' => 1,
        'status' => 'published',
        'published_at' => now(),
    ]);
    $membership = FundMembership::query()->create([
        'account_id' => $account->id,
        'fund_program_id' => $program->id,
        'program_version_id' => $version->id,
        'user_subscription_id' => $subscription->id,
        'status' => FundMembership::STATUS_ACTIVE,
        'started_at' => now(),
    ]);

    return [$program, $version, $membership];
}

function p014eFixture(string $snapshotStatus = FundCollectionSnapshot::STATUS_FUNDED): array
{
    $beneficiary = p014eAccount('p014e-'.Str::lower(Str::random(8)).'@wasplex.test');
    [$program, $version, $membership] = p014eMembership($beneficiary);
    $category = FundWishCategory::query()->create([
        'code' => 'p014e-cat-'.Str::lower(Str::random(8)),
        'name' => 'Mobilité',
        'icon' => '🏍️',
        'is_active' => true,
    ]);
    $provider = Organization::query()->create([
        'name' => 'Prestataire P014-E',
        'type' => 'professional',
        'country_code' => 'CI',
        'status' => 'active',
        'created_by' => $beneficiary->id,
    ]);
    $userSpaceId = (string) Str::ulid();
    DB::table('user_spaces')->insert([
        'id' => $userSpaceId,
        'space_type' => 'professional',
        'organization_id' => $provider->id,
        'owner_account_id' => null,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $professionalSpaceId = (string) Str::ulid();
    DB::table('professional_spaces')->insert([
        'id' => $professionalSpaceId,
        'user_space_id' => $userSpaceId,
        'organization_id' => $provider->id,
        'created_by_account_id' => $beneficiary->id,
        'space_kind' => 'service_provider',
        'verification_status' => 'verified',
        'legal_name' => $provider->name,
        'country_code' => 'CI',
        'verified_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $wish = FundWish::query()->create([
        'account_id' => $beneficiary->id,
        'fund_membership_id' => $membership->id,
        'category_id' => $category->id,
        'status' => FundWish::STATUS_APPROVED,
        'title' => 'Moto de travail P014-E',
        'description' => 'Dossier validé pour tester la réalisation réelle d’un vœu Fonds.',
        'country_code' => 'CI',
        'city' => 'Abidjan',
        'estimated_amount_minor' => 100000,
        'validated_amount_minor' => 100000,
        'currency' => 'XOF',
        'required_personal_contribution_minor' => 30000,
        'provider_organization_id' => $provider->id,
        'reviewed_at' => now(),
        'cost_validated_at' => now(),
    ]);
    $requestId = (string) Str::ulid();
    DB::table('fund_quote_requests')->insert([
        'id' => $requestId,
        'fund_wish_id' => $wish->id,
        'professional_space_id' => $professionalSpaceId,
        'organization_id' => $provider->id,
        'requested_by_account_id' => $beneficiary->id,
        'status' => 'responded',
        'requested_at' => now()->subDay(),
        'responded_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $quoteId = (string) Str::ulid();
    DB::table('fund_wish_quotes')->insert([
        'id' => $quoteId,
        'quote_request_id' => $requestId,
        'fund_wish_id' => $wish->id,
        'organization_id' => $provider->id,
        'submitted_by_account_id' => $beneficiary->id,
        'amount_minor' => 100000,
        'currency' => 'XOF',
        'status' => 'selected',
        'submitted_at' => now()->subHour(),
        'selected_by_account_id' => $beneficiary->id,
        'selected_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $wish->update(['selected_quote_id' => $quoteId]);

    $snapshot = FundCollectionSnapshot::query()->create([
        'fund_wish_id' => $wish->id,
        'fund_program_id' => $program->id,
        'program_version_id' => $version->id,
        'country_code' => 'CI',
        'currency' => 'XOF',
        'validated_cost_minor' => 100000,
        'personal_contribution_minor' => 30000,
        'collective_amount_minor' => 70000,
        'participant_count' => 10,
        'solidarity_base_minor' => 7000,
        'solidarity_remainder_minor' => 0,
        'snapshot_hash' => hash('sha256', (string) Str::uuid()),
        'rules_snapshot' => ['test' => true],
        'status' => $snapshotStatus,
        'notice_at' => now()->subDay(),
        'scheduled_at' => now()->subHours(12),
        'started_at' => now()->subHours(10),
        'completed_at' => $snapshotStatus === FundCollectionSnapshot::STATUS_FUNDED ? now()->subHour() : null,
        'created_by_account_id' => $beneficiary->id,
    ]);

    return compact('beneficiary', 'provider', 'wish', 'snapshot');
}

function p014eSeedFinancing(Account $beneficiary, FundWish $wish, FundCollectionSnapshot $snapshot): void
{
    $posting = app(LedgerPostingContract::class);
    $posting->post(new PostLedgerTransaction(
        type: 'P014_E_TEST_PERSONAL',
        sourceModule: 'tests',
        idempotencyKey: 'p014e-personal-'.$wish->id,
        entries: [
            LedgerEntryInput::debit(LedgerAccountReference::system('p014e.test.personal.asset', 'ASSET', 'WP'), Money::of(30000, 'WP')),
            LedgerEntryInput::credit(LedgerAccountReference::forIdentityAccount('fund.wish.personal.'.$wish->id.'.wp', $beneficiary->id, 'LIABILITY_USER', 'WP'), Money::of(30000, 'WP')),
        ],
    ));
    $posting->post(new PostLedgerTransaction(
        type: 'P014_E_TEST_POOL',
        sourceModule: 'tests',
        idempotencyKey: 'p014e-pool-'.$snapshot->id,
        entries: [
            LedgerEntryInput::debit(LedgerAccountReference::system('p014e.test.pool.asset', 'ASSET', 'WP'), Money::of(70000, 'WP')),
            LedgerEntryInput::credit(LedgerAccountReference::system('fund.collection.pool.'.$snapshot->id.'.wp', 'FONDS', 'WP'), Money::of(70000, 'WP')),
        ],
    ));
}

test('P014-E refuse toute commande avant financement complet puis crée une commande unique', function (): void {
    $fixture = p014eFixture(FundCollectionSnapshot::STATUS_PARTIALLY_FUNDED);
    $service = app(FundRealizationService::class);

    expect(fn () => $service->createOrder($fixture['wish'], $fixture['beneficiary']->id))
        ->toThrow(RuntimeException::class, 'entièrement financée');

    $fixture['snapshot']->update(['status' => FundCollectionSnapshot::STATUS_FUNDED, 'completed_at' => now()]);
    $order = $service->createOrder($fixture['wish']->refresh(), $fixture['beneficiary']->id);
    $same = $service->createOrder($fixture['wish']->refresh(), $fixture['beneficiary']->id);

    expect($order->id)->toBe($same->id)
        ->and(FundOrder::query()->count())->toBe(1)
        ->and($order->total_minor)->toBe(100000);
});

test('P014-E exige une preuve avant validation du jalon', function (): void {
    $fixture = p014eFixture();
    $service = app(FundRealizationService::class);
    $order = $service->createOrder($fixture['wish'], $fixture['beneficiary']->id);
    $milestone = $service->addMilestone($order, 'Acompte fournisseur', 30000, true, null);

    expect(fn () => $service->approveMilestone($order, $milestone['id'], $fixture['beneficiary']->id))
        ->toThrow(RuntimeException::class, 'preuve');

    $service->submitProof($order, $fixture['provider']->id, $fixture['beneficiary']->id, $milestone['id'], 'invoice', 'FAC-001', 'Facture fournisseur');
    $approved = $service->approveMilestone($order, $milestone['id'], $fixture['beneficiary']->id);

    expect($approved['status'])->toBe('approved');
});

test('P014-E alloue le jalon une seule fois dans le Ledger et distingue le règlement externe', function (): void {
    $fixture = p014eFixture();
    p014eSeedFinancing($fixture['beneficiary'], $fixture['wish'], $fixture['snapshot']);
    $service = app(FundRealizationService::class);
    $order = $service->createOrder($fixture['wish'], $fixture['beneficiary']->id);
    $milestone = $service->addMilestone($order, 'Commande fournisseur', 100000, false, null);
    $service->approveMilestone($order, $milestone['id'], $fixture['beneficiary']->id);

    $posted = $service->postMilestoneToLedger($order, $milestone['id'], $fixture['beneficiary']->id);
    expect($posted['status'])->toBe('ledger_posted')
        ->and($posted['external_settlement_status'])->toBe('pending')
        ->and(LedgerTransaction::query()->where('type', 'FUND_PROVIDER_MILESTONE_ALLOCATION')->count())->toBe(1);

    $replayed = $service->postMilestoneToLedger($order->refresh(), $milestone['id'], $fixture['beneficiary']->id);
    expect($replayed['ledger_transaction_id'])->toBe($posted['ledger_transaction_id'])
        ->and(LedgerTransaction::query()->where('type', 'FUND_PROVIDER_MILESTONE_ALLOCATION')->count())->toBe(1);

    $settled = $service->confirmExternalSettlement($order->refresh(), $milestone['id'], 'MOMO-REAL-001');
    expect($settled['external_settlement_status'])->toBe('confirmed')
        ->and($settled['external_reference'])->toBe('MOMO-REAL-001');
});

test('P014-E bloque la clôture pendant un litige et clôture après livraison réglée et confirmée', function (): void {
    $fixture = p014eFixture();
    p014eSeedFinancing($fixture['beneficiary'], $fixture['wish'], $fixture['snapshot']);
    $service = app(FundRealizationService::class);
    $order = $service->createOrder($fixture['wish'], $fixture['beneficiary']->id);
    $milestone = $service->addMilestone($order, 'Livraison complète', 100000, false, null);
    $service->approveMilestone($order, $milestone['id'], $fixture['beneficiary']->id);
    $service->postMilestoneToLedger($order, $milestone['id'], $fixture['beneficiary']->id);
    $service->confirmExternalSettlement($order->refresh(), $milestone['id'], 'BANK-001');
    $service->markDelivered($order->refresh(), $fixture['provider']->id, $fixture['beneficiary']->id, 'BL-001', 'Livraison physique');

    $dispute = $service->openDispute($order->refresh(), $fixture['beneficiary']->id, 'La livraison doit être vérifiée avant clôture définitive.');
    $service->confirmBeneficiary($order->refresh(), $fixture['beneficiary']->id);
    expect($order->refresh()->status)->toBe(FundOrder::STATUS_DISPUTED);

    $service->resolveDispute($order->refresh(), $dispute['id'], $fixture['beneficiary']->id, 'accept_delivery', 'Livraison finalement conforme.');
    $service->confirmBeneficiary($order->refresh(), $fixture['beneficiary']->id);
    expect($order->refresh()->status)->toBe(FundOrder::STATUS_COMPLETED)
        ->and($order->refresh()->completed_at)->not->toBeNull();
});
