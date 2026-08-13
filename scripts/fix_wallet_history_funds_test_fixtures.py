from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def patch_fixture(rel: str) -> None:
    path = ROOT / rel
    text = path.read_text(encoding='utf-8')
    old = "'eligible_subscription_classes' => ['PREMIUM'],"
    if old not in text:
        raise RuntimeError(f'Fixture eligibility marker missing in {rel}')
    # Ces fixtures testent le cycle Fonds générique avec des classes aléatoires
    # PREMIUM-xxxx. L’éligibilité commerciale exacte est testée séparément sur
    # le catalogue seedé ; ici on ne doit pas coupler les tests historiques à
    # un code de classe fictif.
    text = text.replace(old, "'eligible_subscription_classes' => [],", 1)
    path.write_text(text, encoding='utf-8')


patch_fixture('apps/platform/tests/Feature/Funds/FundsFoundationTest.php')
patch_fixture('apps/platform/tests/Feature/Funds/FundsPersonalContributionTest.php')

# Ajoute un test métier dédié : on garde une vérification stricte des classes
# commerciales réelles malgré l’assouplissement des deux fixtures historiques.
test_path = ROOT / 'apps/platform/tests/Feature/Wallet/WalletHistoryAndFundsSeedTest.php'
text = test_path.read_text(encoding='utf-8')
marker = "it('keeps standard Wallet history accordions even when a future module has no movement yet'"
if marker not in text:
    raise RuntimeError('WalletHistoryAndFundsSeedTest insertion marker missing')

new_test = r'''it('enforces the seeded program subscription class before charging any membership fee', function (): void {
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

'''
text = text.replace(marker, new_test + marker, 1)
test_path.write_text(text, encoding='utf-8')

print('Legacy Funds fixtures aligned; strict seeded class eligibility remains tested.')
