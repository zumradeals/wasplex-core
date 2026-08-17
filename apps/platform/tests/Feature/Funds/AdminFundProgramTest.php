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
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

function fundProgramAccountByIdentifier(string $identifierValue): Account
{
    return Account::query()
        ->whereHas('identifiers', fn ($q) => $q->where('value', $identifierValue))
        ->firstOrFail();
}

function fundProgramVerifyRecentMfa(): void
{
    $secret = test()->postJson('/api/me/mfa')->assertOk()->json('secret');
    $code = app(Google2FA::class)->getCurrentOtp($secret);
    test()->putJson('/api/me/mfa', ['code' => $code])->assertOk();
    test()->postJson('/api/me/mfa/verify', ['code' => $code])->assertOk();
}

/** @return array{account_id: string} */
function fundProgramLoginAsAdmin(string $identifier): array
{
    registerAndLogin($identifier);
    $account = fundProgramAccountByIdentifier($identifier);
    grantFounderAccessForTests($account, ['admin.funds.view', 'admin.funds.manage']);
    fundProgramVerifyRecentMfa();

    return ['account_id' => $account->id];
}

/**
 * Switches the test client's session cookie back to an already-registered
 * account (e.g. the admin, after a test briefly logged in as a member) —
 * registerAndLogin() cannot be reused here since it always registers a new
 * account first.
 */
function fundProgramReloginAsAdmin(string $identifier, string $password = 'Password123!'): void
{
    $login = test()->postJson('/api/login', [
        'identifier_value' => $identifier,
        'password' => $password,
    ])->assertOk();

    $sessionCookieName = config('session.cookie');
    foreach ($login->headers->getCookies() as $cookie) {
        if ($cookie->getName() === $sessionCookieName) {
            test()->withUnencryptedCookie($sessionCookieName, $cookie->getValue());
        }
    }

    fundProgramVerifyRecentMfa();
}

/** @return array{version_minimal_payload: array<string, mixed>} */
function fundProgramMinimalVersionPayload(): array
{
    return [
        'currency' => 'XOF',
        'membership_fee_minor' => 2000,
        'duration_days' => 365,
        'max_active_wishes' => 1,
        'max_wishes_per_period' => 1,
        'personal_contribution_percent' => 10,
        'min_debit_minor' => 100,
        'wasplex_fee_minor' => 50,
        'notice_hours' => 24,
        'grace_period_days' => 7,
    ];
}

it('refuses an unauthenticated request to every admin Fonds program endpoint', function (): void {
    test()->postJson('/api/admin/funds/programs', ['code' => 'silver', 'name' => 'Silver'])->assertStatus(401);
    test()->deleteJson('/api/admin/funds/programs/does-not-matter')->assertStatus(401);
});

it('refuses a request without recent MFA even with the right capability', function (): void {
    registerAndLogin('funds-no-mfa@wasplex.test');
    grantFounderAccessForTests(fundProgramAccountByIdentifier('funds-no-mfa@wasplex.test'), ['admin.funds.manage']);

    test()->postJson('/api/admin/funds/programs', ['code' => 'silver', 'name' => 'Silver'])
        ->assertStatus(401)
        ->assertJsonPath('code', 'MFA_REQUIRED');
});

it('refuses a request with recent MFA but without the capability', function (): void {
    registerAndLogin('funds-no-capability@wasplex.test');
    grantFounderAccessForTests(fundProgramAccountByIdentifier('funds-no-capability@wasplex.test'), []);
    fundProgramVerifyRecentMfa();

    test()->postJson('/api/admin/funds/programs', ['code' => 'silver', 'name' => 'Silver'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'CAPABILITY_DENIED');
});

it('retrying the exact same program code returns a clean 422 instead of a 500', function (): void {
    fundProgramLoginAsAdmin('funds-retry-same@wasplex.test');

    test()->postJson('/api/admin/funds/programs', ['code' => 'Silver', 'name' => 'Silver'])->assertCreated();

    // Avant le correctif, cette relance à l'identique passait la
    // validation Rule::unique (comparée en minuscule après coup) puis
    // plantait sur la contrainte d'unicité réelle en base (500).
    $retry = test()->postJson('/api/admin/funds/programs', ['code' => 'Silver', 'name' => 'Silver']);
    $retry->assertStatus(422);
    expect($retry->json('message'))->toBe('Ce code de programme est déjà utilisé.');
});

it('retrying a program code with a different case returns a clean 422 instead of a 500', function (): void {
    fundProgramLoginAsAdmin('funds-retry-case@wasplex.test');

    test()->postJson('/api/admin/funds/programs', ['code' => 'Silver', 'name' => 'Silver'])->assertCreated();

    // C'est le scénario qui provoquait le 500 avant le correctif : la
    // casse diffère ("silver" vs "Silver"), donc Rule::unique validait
    // sur la valeur brute sans collision, puis Str::lower() collidait à
    // l'insertion réelle en base.
    $retry = test()->postJson('/api/admin/funds/programs', ['code' => 'silver', 'name' => 'Silver v2']);
    $retry->assertStatus(422);
    expect($retry->json('message'))->toBe('Ce code de programme est déjà utilisé.');
    expect(FundProgram::query()->where('code', 'silver')->count())->toBe(1);
});

it('retrying the exact same category code returns a clean 422 instead of a 500', function (): void {
    fundProgramLoginAsAdmin('funds-retry-category@wasplex.test');

    test()->postJson('/api/admin/funds/categories', ['code' => 'Sante', 'name' => 'Santé'])->assertCreated();

    $retry = test()->postJson('/api/admin/funds/categories', ['code' => 'sante', 'name' => 'Santé bis']);
    $retry->assertStatus(422);
    expect($retry->json('message'))->toBe('Ce code de catégorie est déjà utilisé.');
    expect(FundWishCategory::query()->where('code', 'sante')->count())->toBe(1);
});

it('refuses a program version with a free (zero) membership fee', function (): void {
    fundProgramLoginAsAdmin('funds-free-fee@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'gratis', 'name' => 'Gratis'])->assertCreated();

    test()->postJson("/api/admin/funds/programs/{$program->json('id')}/versions", [
        ...fundProgramMinimalVersionPayload(),
        'membership_fee_minor' => 0,
    ])->assertUnprocessable();

    expect(FundProgramVersion::query()->where('fund_program_id', $program->json('id'))->count())->toBe(0);
});

it('creates, publishes and stores a positive membership fee and the eligible subscription classes', function (): void {
    fundProgramLoginAsAdmin('funds-full-flow@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'silver', 'name' => 'Silver'])->assertCreated();

    $version = test()->postJson("/api/admin/funds/programs/{$program->json('id')}/versions", [
        ...fundProgramMinimalVersionPayload(),
        'membership_fee_minor' => 2500,
        'eligible_subscription_classes' => ['PREMIUM', 'GOLD'],
    ])->assertCreated();

    test()->postJson("/api/admin/funds/program-versions/{$version->json('id')}/publish")
        ->assertOk()
        ->assertJsonPath('membership_fee_minor', 2500)
        ->assertJsonPath('eligible_subscription_classes', ['PREMIUM', 'GOLD'])
        ->assertJsonPath('status', 'published');
});

it('deletes a draft program that never received a version', function (): void {
    fundProgramLoginAsAdmin('funds-delete-draft@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'abandoned', 'name' => 'Abandoned'])->assertCreated();

    test()->deleteJson("/api/admin/funds/programs/{$program->json('id')}")->assertNoContent();

    expect(FundProgram::query()->whereKey($program->json('id'))->exists())->toBeFalse();

    // Le code redevient disponible immédiatement.
    test()->postJson('/api/admin/funds/programs', ['code' => 'abandoned', 'name' => 'Abandoned v2'])->assertCreated();
});

it('refuses to delete a program that already has a version, even unpublished', function (): void {
    fundProgramLoginAsAdmin('funds-delete-blocked@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'guarded', 'name' => 'Guarded'])->assertCreated();
    test()->postJson("/api/admin/funds/programs/{$program->json('id')}/versions", fundProgramMinimalVersionPayload())->assertCreated();

    test()->deleteJson("/api/admin/funds/programs/{$program->json('id')}")->assertUnprocessable();

    expect(FundProgram::query()->whereKey($program->json('id'))->exists())->toBeTrue();
});

it('refuses to delete a program that is no longer in draft status', function (): void {
    fundProgramLoginAsAdmin('funds-delete-active@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'lively', 'name' => 'Lively'])->assertCreated();
    $version = test()->postJson("/api/admin/funds/programs/{$program->json('id')}/versions", fundProgramMinimalVersionPayload())->assertCreated();
    test()->postJson("/api/admin/funds/program-versions/{$version->json('id')}/publish")->assertOk();

    test()->deleteJson("/api/admin/funds/programs/{$program->json('id')}")->assertUnprocessable();
});

it('updates a program name via PATCH without touching its code', function (): void {
    fundProgramLoginAsAdmin('funds-patch-name@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'renamable', 'name' => 'Old Name'])->assertCreated();

    test()->patchJson("/api/admin/funds/programs/{$program->json('id')}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('name', 'New Name')
        ->assertJsonPath('code', 'renamable');
});

it('refuses to update a program without the capability', function (): void {
    fundProgramLoginAsAdmin('funds-patch-owner@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'untouchable', 'name' => 'Untouchable'])->assertCreated();

    registerAndLogin('funds-patch-no-capability@wasplex.test');
    grantFounderAccessForTests(fundProgramAccountByIdentifier('funds-patch-no-capability@wasplex.test'), []);
    fundProgramVerifyRecentMfa();

    test()->patchJson("/api/admin/funds/programs/{$program->json('id')}", ['name' => 'X'])->assertStatus(403);
});

function fundProgramPaidSubscription(string $accountId): void
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
        'name' => 'Premium test Fonds edit',
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
        'account_id' => $accountId,
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
}

function fundProgramCreditWallet(string $accountId, int $amountMinor, string $key): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_WALLET_CREDIT',
        sourceModule: 'tests',
        idempotencyKey: $key,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of($amountMinor, 'WP'), 'Crédit test'),
            LedgerEntryInput::credit(ledgerUserAvailable($accountId), Money::of($amountMinor, 'WP'), 'Crédit test'),
        ],
    ));
}

it('publishing a new version supersedes the previous one while an already engaged member keeps their accepted version', function (): void {
    Artisan::call('ledger:seed-catalog');

    fundProgramLoginAsAdmin('funds-edit-admin@wasplex.test');
    $program = test()->postJson('/api/admin/funds/programs', ['code' => 'evolving', 'name' => 'Evolving'])->assertCreated();
    $programId = $program->json('id');

    $v1 = test()->postJson("/api/admin/funds/programs/{$programId}/versions", [
        ...fundProgramMinimalVersionPayload(),
        'membership_fee_minor' => 1000,
    ])->assertCreated();
    test()->postJson("/api/admin/funds/program-versions/{$v1->json('id')}/publish")->assertOk();

    registerAndLogin('funds-edit-member@wasplex.test');
    $memberId = fundProgramAccountByIdentifier('funds-edit-member@wasplex.test')->id;
    fundProgramPaidSubscription($memberId);
    fundProgramCreditWallet($memberId, 5000, 'funds-edit-member-credit');

    $membership = test()->postJson('/api/funds/membership', [
        'program_id' => $programId,
        'personal_cap_minor' => 100,
        'accept_mandate' => true,
    ])->assertCreated();

    expect(FundMembership::query()->whereKey($membership->json('id'))->value('program_version_id'))->toBe($v1->json('id'));

    // L'admin republie le programme avec un nouveau prix — c'est la
    // modification demandée par le fondateur, exposée par "Modifier" côté
    // admin, mais elle passe toujours par une nouvelle version.
    fundProgramReloginAsAdmin('funds-edit-admin@wasplex.test');
    $v2 = test()->postJson("/api/admin/funds/programs/{$programId}/versions", [
        ...fundProgramMinimalVersionPayload(),
        'membership_fee_minor' => 3000,
    ])->assertCreated();
    test()->postJson("/api/admin/funds/program-versions/{$v2->json('id')}/publish")
        ->assertOk()
        ->assertJsonPath('membership_fee_minor', 3000)
        ->assertJsonPath('status', 'published');

    expect(FundProgramVersion::query()->whereKey($v1->json('id'))->value('status'))->toBe('retired');
    expect(FundProgramVersion::query()->whereKey($v2->json('id'))->value('status'))->toBe('published');

    // Le membre déjà engagé garde exactement les conditions qu'il a
    // acceptées — la nouvelle version ne s'applique pas rétroactivement.
    expect(FundMembership::query()->whereKey($membership->json('id'))->value('program_version_id'))->toBe($v1->json('id'));
});
