<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Services\WalletAuditor;
use App\Modules\Wallet\Application\Services\WalletPresenter;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletProviderEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class WalletAdminController
{
    public function __construct(private WalletPresenter $presenter, private WalletAuditor $auditor) {}

    public function page(Request $request): Response
    {
        $this->auditAccess($request);

        return Inertia::render('Wallet/Admin', $this->payload());
    }

    public function index(Request $request): JsonResponse
    {
        $this->auditAccess($request);

        return response()->json(['data' => $this->payload()]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        $deposits = WalletDeposit::query()->with('wallet')->latest()->limit(50)->get();

        return [
            'metrics' => [
                'wallets' => Wallet::query()->count(),
                'deposits' => WalletDeposit::query()->count(),
                'credited' => WalletDeposit::query()->where('status', 'credited')->sum('amount_minor'),
                'anomalies' => WalletDeposit::query()->whereIn('status', ['unknown', 'failed'])->count()
                    + WalletProviderEvent::query()->where('status', 'failed')->count(),
            ],
            'deposits' => $deposits->map(fn (WalletDeposit $deposit): array => [
                ...$this->presenter->deposit($deposit),
                'walletKind' => $deposit->wallet->kind->value,
                'walletId' => $deposit->wallet_id,
            ])->values(),
            'sandbox' => true,
        ];
    }

    private function auditAccess(Request $request): void
    {
        $actor = $request->user();
        $space = $request->attributes->get('active_space');
        $traceId = $request->attributes->get('trace_id');
        $this->auditor->record(
            action: 'wallet.admin.deposits_viewed',
            outcome: 'success',
            actor: $actor instanceof Account ? $actor : null,
            space: $space instanceof UserSpace ? $space : null,
            traceId: is_string($traceId) ? $traceId : null,
        );
    }
}
