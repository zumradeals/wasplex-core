<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Identity\Application\Services\CapabilityService;
use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletCatalog;
use App\Modules\Wallet\Application\Services\WalletDepositService;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use App\Modules\Wallet\Domain\Exceptions\WalletException;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletOperation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final readonly class WalletController
{
    public function __construct(
        private WalletCatalog $catalog,
        private WalletDepositService $deposits,
        private WalletPresenter $presenter,
        private CapabilityService $capabilities,
    ) {}

    public function show(Request $request): Response
    {
        $wallet = $this->activeWallet($request, history: true);

        return Inertia::render('Wallet/Show', [
            'wallet' => $this->presenter->wallet($wallet),
            'operations' => WalletOperation::query()->where('wallet_id', $wallet->id)->latest('occurred_at')->limit(20)->get()
                ->map(fn (WalletOperation $operation): array => $this->presenter->operation($operation))->values(),
            'deposits' => WalletDeposit::query()->where('wallet_id', $wallet->id)->latest()->limit(10)->get()
                ->map(fn (WalletDeposit $deposit): array => $this->presenter->deposit($deposit))->values(),
            'provider' => [
                'name' => 'GeniusPay',
                'environment' => 'sandbox',
                'configured' => config('services.geniuspay.api_key') !== null
                    && (string) config('services.geniuspay.api_key') !== ''
                    && (string) config('services.geniuspay.api_secret') !== ''
                    && (string) config('services.geniuspay.webhook_secret') !== '',
                'minimum' => (int) config('services.geniuspay.minimum_deposit_minor', 200),
                'maximum' => (int) config('services.geniuspay.maximum_deposit_minor', 5000000),
            ],
            'returnState' => $request->string('paiement')->toString(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->presenter->wallet($this->activeWallet($request))]);
    }

    public function operations(Request $request): JsonResponse
    {
        $wallet = $this->activeWallet($request, history: true);
        $operations = WalletOperation::query()->where('wallet_id', $wallet->id)->latest('occurred_at')->paginate(30);

        return response()->json([
            'data' => $operations->getCollection()->map(fn (WalletOperation $operation): array => $this->presenter->operation($operation)),
            'meta' => ['current_page' => $operations->currentPage(), 'last_page' => $operations->lastPage()],
        ]);
    }

    public function storeDeposit(Request $request): JsonResponse|RedirectResponse|SymfonyResponse
    {
        $validated = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'min:16', 'max:160', 'regex:/^[A-Za-z0-9:_-]+$/'],
        ]);
        $wallet = $this->activeWallet($request, funding: true);
        /** @var Account $account */
        $account = $request->user();
        /** @var UserSpace $space */
        $space = $request->attributes->get('active_space');
        $traceId = $request->attributes->get('trace_id');
        $traceId = is_string($traceId) ? $traceId : null;
        $idempotencyKey = (string) ($request->header('Idempotency-Key') ?: ($validated['idempotency_key'] ?? Str::uuid()));
        if (strlen($idempotencyKey) > 160 || preg_match('/^[A-Za-z0-9:_-]{16,160}$/', $idempotencyKey) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'La clé de sécurité du dépôt est invalide.']);
        }
        $walletRoute = $space->kind === SpaceKind::Advertiser ? 'studio.wallet' : 'wallet.show';

        try {
            $deposit = $this->deposits->create(
                wallet: $wallet,
                amountMinor: (int) $validated['amount_minor'],
                idempotencyKey: $idempotencyKey,
                successUrl: route($walletRoute, ['paiement' => 'retour']),
                errorUrl: route($walletRoute, ['paiement' => 'annule']),
                actor: $account,
                space: $space,
                traceId: $traceId,
            );
        } catch (WalletException $exception) {
            throw ValidationException::withMessages([
                'amount_minor' => $exception->getMessage(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $this->presenter->deposit($deposit)], 201);
        }

        if ($deposit->checkout_url !== null) {
            return Inertia::location($deposit->checkout_url);
        }

        return redirect()->route($walletRoute)->with('status', 'Le paiement sandbox est en cours de préparation.');
    }

    public function deposits(Request $request): JsonResponse
    {
        $wallet = $this->activeWallet($request, history: true);
        $deposits = WalletDeposit::query()->where('wallet_id', $wallet->id)->latest()->paginate(20);

        return response()->json([
            'data' => $deposits->getCollection()->map(fn (WalletDeposit $deposit): array => $this->presenter->deposit($deposit)),
            'meta' => ['current_page' => $deposits->currentPage(), 'last_page' => $deposits->lastPage()],
        ]);
    }

    public function deposit(Request $request, string $depositId): JsonResponse
    {
        $wallet = $this->activeWallet($request, history: true);
        $deposit = WalletDeposit::query()->where('wallet_id', $wallet->id)->findOrFail($depositId);

        return response()->json(['data' => $this->presenter->deposit($deposit)]);
    }

    private function activeWallet(Request $request, bool $funding = false, bool $history = false): Wallet
    {
        $space = $request->attributes->get('active_space');
        abort_unless($space instanceof UserSpace, 403);
        $account = $request->user();
        abort_unless($account instanceof Account, 403);

        $capability = match ($space->kind) {
            SpaceKind::User => $funding
                ? 'wallet.deposit.create.self'
                : ($history ? 'wallet.history.view.self' : 'wallet.view.self'),
            SpaceKind::Advertiser => $funding ? 'advertiser.wallet.fund' : 'advertiser.wallet.view',
            default => null,
        };
        abort_unless($capability !== null && $this->capabilities->allows($account, $capability, $space), 403);

        return $this->catalog->forSpace($space);
    }
}
