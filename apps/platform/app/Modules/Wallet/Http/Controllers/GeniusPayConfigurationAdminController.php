<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Modules\Identity\Domain\Enums\SpaceKind;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Application\Contracts\PaymentGatewayContract;
use App\Modules\Wallet\Application\Services\GeniusPayConfigurationService;
use App\Modules\Wallet\Application\Services\WalletAuditor;
use App\Modules\Wallet\Application\Services\WalletDepositService;
use App\Modules\Wallet\Domain\Enums\DepositStatus;
use App\Modules\Wallet\Infrastructure\Models\WalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\WalletProviderEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final readonly class GeniusPayConfigurationAdminController
{
    public function __construct(
        private GeniusPayConfigurationService $settings,
        private PaymentGatewayContract $gateway,
        private WalletDepositService $deposits,
        private WalletAuditor $auditor,
    ) {}

    public function index(Request $request): Response
    {
        [$account, $space] = $this->context($request);
        $lastEvent = WalletProviderEvent::query()
            ->where('provider', 'geniuspay')
            ->latest('occurred_at')
            ->first();
        $runtime = $this->settings->runtime();

        $this->audit(
            request: $request,
            account: $account,
            space: $space,
            action: 'wallet.geniuspay.configuration_viewed',
            outcome: 'success',
        );

        return Inertia::render('Admin/GeniusPayConfiguration', [
            ...$this->baseProps($request, $account, $space),
            'environments' => [
                $this->settings->summary('sandbox'),
                $this->settings->summary('production'),
            ],
            'activeEnvironment' => (string) $runtime['environment'],
            'productionActivationAllowed' => $this->settings->productionActivationAllowed(),
            'webhookUrl' => $this->settings->webhookUrl(),
            'webhookHealth' => [
                'lastAcceptedEvent' => $lastEvent === null ? null : [
                    'name' => $lastEvent->event_name,
                    'status' => $lastEvent->status->value,
                    'attempts' => $lastEvent->attempts,
                    'occurredAt' => $lastEvent->occurred_at?->toIso8601String(),
                    'processedAt' => $lastEvent->processed_at?->toIso8601String(),
                    'lastError' => $lastEvent->last_error,
                ],
                'paymentsQueueSize' => Queue::size('payments'),
            ],
            'pendingDeposits' => WalletDeposit::query()
                ->whereIn('status', [
                    DepositStatus::AwaitingPayment->value,
                    DepositStatus::PaymentDetected->value,
                    DepositStatus::Confirmed->value,
                ])
                ->whereNotNull('provider_reference')
                ->count(),
        ]);
    }

    public function update(Request $request, string $environment): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        /** @var array{api_key?:string|null,api_secret?:string|null,webhook_secret?:string|null} $validated */
        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'string', 'max:2000'],
            'webhook_secret' => ['nullable', 'string', 'max:2000'],
        ]);

        $configuration = $this->settings->save($environment, $validated, $account);
        $this->audit(
            request: $request,
            account: $account,
            space: $space,
            action: 'wallet.geniuspay.configuration_updated',
            outcome: 'success',
            subjectId: $configuration->id,
            context: ['environment' => $configuration->environment],
        );

        return back()->with(
            'success',
            "Les identifiants GeniusPay {$configuration->environment} ont été enregistrés et chiffrés.",
        );
    }

    public function test(Request $request, string $environment): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $result = $this->settings->test($environment, $account);
        $this->audit(
            request: $request,
            account: $account,
            space: $space,
            action: 'wallet.geniuspay.connection_tested',
            outcome: $result['ok'] ? 'success' : 'failure',
            context: [
                'environment' => $environment,
                'http_status' => $result['httpStatus'],
                'provider_environment' => $result['environment'],
            ],
        );

        if (! $result['ok']) {
            return back()->withErrors(['connection' => $result['message']]);
        }

        return back()->with('success', $result['message']);
    }

    public function activate(Request $request, string $environment): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $configuration = $this->settings->activate($environment, $account);
        $this->audit(
            request: $request,
            account: $account,
            space: $space,
            action: 'wallet.geniuspay.environment_activated',
            outcome: 'success',
            subjectId: $configuration->id,
            context: ['environment' => $configuration->environment],
        );

        return back()->with(
            'success',
            "L’environnement GeniusPay {$configuration->environment} est maintenant actif.",
        );
    }

    public function reconcilePending(Request $request): RedirectResponse
    {
        [$account, $space] = $this->context($request);
        $candidates = WalletDeposit::query()
            ->whereIn('status', [
                DepositStatus::AwaitingPayment->value,
                DepositStatus::PaymentDetected->value,
                DepositStatus::Confirmed->value,
            ])
            ->whereNotNull('provider_reference')
            ->oldest()
            ->limit(50)
            ->get();
        $credited = 0;
        $stillPending = 0;
        $failed = 0;

        foreach ($candidates as $deposit) {
            try {
                $reference = (string) $deposit->provider_reference;
                $payment = $this->gateway->fetchPayment($reference);
                $synchronized = $this->deposits->synchronizePayment($payment);

                if ($synchronized->status === DepositStatus::Credited) {
                    $credited++;
                } else {
                    $stillPending++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->audit(
            request: $request,
            account: $account,
            space: $space,
            action: 'wallet.geniuspay.pending_reconciled',
            outcome: $failed === 0 ? 'success' : 'warning',
            context: [
                'checked' => $candidates->count(),
                'credited' => $credited,
                'pending' => $stillPending,
                'failed' => $failed,
            ],
        );

        return back()->with(
            'success',
            "Revérification terminée : {$credited} crédité(s), {$stillPending} encore en attente, {$failed} erreur(s).",
        );
    }

    /** @return array{0:Account,1:UserSpace} */
    private function context(Request $request): array
    {
        $account = $request->user();
        $space = $request->attributes->get('active_space');
        abort_unless($account instanceof Account, 403);
        abort_unless($space instanceof UserSpace && $space->kind === SpaceKind::Administration, 403);

        return [$account, $space];
    }

    /** @return array<string, mixed> */
    private function baseProps(Request $request, Account $account, UserSpace $activeSpace): array
    {
        $account->loadMissing(['profile', 'spaces.organization']);

        return [
            'account' => [
                'id' => $account->id,
                'displayName' => $account->profile?->display_name,
            ],
            'activeSpace' => [
                'id' => $activeSpace->id,
                'kind' => $activeSpace->kind->value,
                'label' => $activeSpace->label,
            ],
            'spaces' => $account->spaces->map(fn (UserSpace $candidate): array => [
                'id' => $candidate->id,
                'kind' => $candidate->kind->value,
                'label' => $candidate->label,
                'active' => $activeSpace->id === $candidate->id,
            ])->values(),
            'flash' => [
                'success' => $request->session()->get('success'),
            ],
            'errors' => $request->session()->get('errors')?->getBag('default')->getMessages() ?? [],
        ];
    }

    /** @param array<string, mixed> $context */
    private function audit(
        Request $request,
        Account $account,
        UserSpace $space,
        string $action,
        string $outcome,
        ?string $subjectId = null,
        array $context = [],
    ): void {
        $traceId = $request->attributes->get('trace_id');
        $this->auditor->record(
            action: $action,
            outcome: $outcome,
            actor: $account,
            space: $space,
            subjectType: 'payment_provider_configuration',
            subjectId: $subjectId,
            traceId: is_string($traceId) ? $traceId : null,
            context: $context,
        );
    }
}
