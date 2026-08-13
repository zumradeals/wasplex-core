from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding="utf-8")


def write(rel: str, content: str) -> None:
    path = ROOT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content.rstrip() + "\n", encoding="utf-8")


def replace(rel: str, old: str, new: str) -> None:
    content = read(rel)
    if old not in content:
        raise RuntimeError(f"Motif introuvable dans {rel}: {old[:120]!r}")
    write(rel, content.replace(old, new, 1))


# ---------------------------------------------------------------------------
# Paiements partagés : chaque métier contrôle désormais son URL de retour.
# ---------------------------------------------------------------------------
write(
    "apps/platform/app/Shared/Payments/ValueObjects/CreatePaymentRequest.php",
    r'''<?php

declare(strict_types=1);

namespace App\Shared\Payments\ValueObjects;

/**
 * Requête de création de paiement indépendante du fournisseur.
 *
 * Les URL de retour et les métadonnées appartiennent au contexte métier :
 * un abonnement utilisateur ne doit jamais hériter du retour Studio d'un
 * dépôt annonceur, et inversement.
 *
 * @param array<string, mixed>|null $metadata
 */
final class CreatePaymentRequest
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly string $internalReference,
        public readonly string $idempotencyKey,
        public readonly ?string $successUrl = null,
        public readonly ?string $errorUrl = null,
        public readonly ?string $description = null,
        public readonly ?array $metadata = null,
    ) {}
}
''',
)

replace(
    "apps/platform/app/Shared/Payments/GeniusPayAdapter.php",
    '''        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->authenticationHeaders())
            ->post('/payments', [
                'amount' => $request->amountMinor,
                'currency' => $request->currency,
                'description' => "Recharge Wallet Wasplex {$request->internalReference}",
                'success_url' => rtrim((string) config('app.url'), '/').'/studio?payment=success',
                'error_url' => rtrim((string) config('app.url'), '/').'/studio?payment=failed',
                'metadata' => [
                    'deposit_id' => $request->internalReference,
                    'idempotency_key' => $request->idempotencyKey,
                ],
            ])
            ->throw();''',
    '''        $appUrl = rtrim((string) config('app.url'), '/');
        $metadata = array_merge([
            'internal_reference' => $request->internalReference,
            'idempotency_key' => $request->idempotencyKey,
        ], $request->metadata ?? []);

        $response = Http::baseUrl($this->baseUrl)
            ->withHeaders($this->authenticationHeaders())
            ->post('/payments', [
                'amount' => $request->amountMinor,
                'currency' => $request->currency,
                'description' => $request->description ?? "Paiement Wasplex {$request->internalReference}",
                'success_url' => $request->successUrl ?? $appUrl.'/app',
                'error_url' => $request->errorUrl ?? $appUrl.'/app?payment=failed',
                'metadata' => $metadata,
            ])
            ->throw();''',
)

replace(
    "apps/platform/app/Modules/AdvertiserWallet/Application/Services/DepositService.php",
    '''        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $amountMinor,
            currency: $currency,
            internalReference: $deposit->id,
            idempotencyKey: $deposit->idempotency_key,
        ));''',
    '''        $appUrl = rtrim((string) config('app.url'), '/');
        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $amountMinor,
            currency: $currency,
            internalReference: $deposit->id,
            idempotencyKey: $deposit->idempotency_key,
            successUrl: $appUrl.'/studio?payment=success',
            errorUrl: $appUrl.'/studio?payment=failed',
            description: "Recharge Wallet annonceur Wasplex {$deposit->id}",
            metadata: [
                'payment_context' => 'advertiser_wallet_deposit',
                'deposit_id' => $deposit->id,
            ],
        ));''',
)

# ---------------------------------------------------------------------------
# Abonnements : retour Mon Espace + rapprochement serveur au retour.
# ---------------------------------------------------------------------------
replace(
    "apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionService.php",
    "use App\\Shared\\Payments\\PaymentProviderContract;",
    "use App\\Shared\\Http\\AppException;\nuse App\\Shared\\Payments\\PaymentProviderContract;",
)

replace(
    "apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionService.php",
    '''        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $payment->amount_minor,
            currency: $payment->currency,
            internalReference: $payment->id,
            idempotencyKey: $payment->idempotency_key,
        ));''',
    '''        $appUrl = rtrim((string) config('app.url'), '/');
        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $payment->amount_minor,
            currency: $payment->currency,
            internalReference: $payment->id,
            idempotencyKey: $payment->idempotency_key,
            successUrl: $appUrl.'/app?tab=espace&section=subscription&payment=subscription-success&payment_id='.$payment->id,
            errorUrl: $appUrl.'/app?tab=espace&section=subscription&payment=subscription-failed&payment_id='.$payment->id,
            description: "Abonnement Wasplex {$planVersion->id}",
            metadata: [
                'payment_context' => 'subscription',
                'subscription_payment_id' => $payment->id,
                'user_subscription_id' => $subscriptionId,
                'is_upgrade' => $isUpgrade,
            ],
        ));''',
)

replace(
    "apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionService.php",
    '''    private function createPayment(string $accountId, SubscriptionPlanVersion $planVersion, string $subscriptionId, bool $isUpgrade = false): SubscriptionPayment
    {''',
    '''    /**
     * Réconciliation déclenchée depuis le retour navigateur. Le navigateur
     * ne confirme jamais le paiement : Wasplex relit le statut directement
     * chez GeniusPay avant toute activation.
     */
    public function reconcilePayment(string $paymentId, string $accountId): SubscriptionPayment
    {
        $payment = SubscriptionPayment::query()
            ->whereKey($paymentId)
            ->where('account_id', $accountId)
            ->first();

        if ($payment === null) {
            throw new AppException('SUBSCRIPTION_PAYMENT_NOT_FOUND', 'Paiement d’abonnement introuvable.', [], 404);
        }

        if ($payment->isTerminal()) {
            return $payment;
        }

        if ($payment->provider_reference === null) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_NOT_STARTED',
                'Le paiement n’a pas encore de référence GeniusPay.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        $verified = $this->provider->fetchPaymentStatus($payment->provider_reference);

        if ($verified->amountMinor !== null && $verified->amountMinor !== $payment->amount_minor) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_AMOUNT_MISMATCH',
                'Le montant confirmé par le prestataire ne correspond pas au paiement attendu.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        if ($verified->currency !== null && $verified->currency !== $payment->currency) {
            throw new AppException(
                'SUBSCRIPTION_PAYMENT_CURRENCY_MISMATCH',
                'La devise confirmée par le prestataire ne correspond pas au paiement attendu.',
                ['payment_id' => $payment->id],
                409,
            );
        }

        if ($verified->normalizedStatus === 'confirmed') {
            $payment->update(['status' => SubscriptionPayment::STATUS_CONFIRMED]);
            $this->activateFromConfirmedPayment($payment->refresh());
        } elseif ($verified->normalizedStatus === 'rejected') {
            $payment->update(['status' => SubscriptionPayment::STATUS_REJECTED]);
        } elseif ($verified->normalizedStatus === 'expired') {
            $payment->update(['status' => SubscriptionPayment::STATUS_EXPIRED]);
        } elseif ($verified->normalizedStatus === 'awaiting_payment') {
            $payment->update(['status' => SubscriptionPayment::STATUS_AWAITING_PAYMENT]);
        }

        return $payment->refresh();
    }

    private function createPayment(string $accountId, SubscriptionPlanVersion $planVersion, string $subscriptionId, bool $isUpgrade = false): SubscriptionPayment
    {''',
)

replace(
    "apps/platform/app/Modules/Subscriptions/Http/Controllers/User/SubscriptionsController.php",
    '''    public function renew(Request $request, string $subscription): JsonResponse
    {''',
    '''    public function refreshPayment(Request $request, string $payment): JsonResponse
    {
        $updated = $this->subscriptions->reconcilePayment($payment, (string) $request->user()->id);

        return response()->json(['payment' => $updated]);
    }

    public function renew(Request $request, string $subscription): JsonResponse
    {''',
)

replace(
    "apps/platform/app/Modules/Subscriptions/Http/routes/api.php",
    "        Route::post('/', [SubscriptionsController::class, 'store']);",
    "        Route::post('/', [SubscriptionsController::class, 'store']);\n        Route::post('/payments/{payment}/refresh', [SubscriptionsController::class, 'refreshPayment']);",
)

write(
    "apps/platform/app/Modules/Subscriptions/Application/Services/SubscriptionPaymentWebhookHandler.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Services;

use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;

/**
 * Le webhook n'est qu'un déclencheur. La confirmation économique passe
 * toujours par SubscriptionService::reconcilePayment(), qui relit le
 * paiement directement chez GeniusPay avant activation.
 */
final class SubscriptionPaymentWebhookHandler
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function handle(string $rawPayload, array $headers): void
    {
        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            Log::channel('structured')->warning('subscriptions.webhook.invalid_signature');

            throw new InvalidWebhookSignatureException;
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);

        $payment = $event->providerReference !== null
            ? SubscriptionPayment::query()->where('provider_reference', $event->providerReference)->first()
            : null;

        if ($payment === null) {
            Log::channel('structured')->info('subscriptions.webhook.unknown_reference', [
                'provider_reference' => $event->providerReference,
            ]);

            return;
        }

        if ($payment->isTerminal()) {
            Log::channel('structured')->info('subscriptions.webhook.duplicated', ['payment_id' => $payment->id]);

            return;
        }

        $updated = $this->subscriptions->reconcilePayment($payment->id, $payment->account_id);

        Log::channel('structured')->info('subscriptions.webhook.reconciled', [
            'payment_id' => $payment->id,
            'status' => $updated->status,
        ]);
    }
}
''',
)

# ---------------------------------------------------------------------------
# Wallet utilisateur : modèles, dépôt, transfert et Grand Livre.
# ---------------------------------------------------------------------------
write(
    "apps/platform/app/Modules/Wallet/Database/Migrations/2026_08_13_090000_create_user_wallet_operations.php",
    r'''<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_wallet_deposits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('account_id')->index();
            $table->string('ledger_transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('XOF');
            $table->string('status', 32)->default('created')->index();
            $table->string('provider_code', 32)->default('geniuspay');
            $table->string('provider_reference')->nullable()->unique();
            $table->text('checkout_url')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('user_wallet_transfers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('sender_account_id')->index();
            $table->string('recipient_account_id')->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 8)->default('WP');
            $table->string('status', 32)->default('pending')->index();
            $table->string('ledger_transaction_id')->nullable()->unique();
            $table->string('idempotency_key');
            $table->timestamps();

            $table->unique(
                ['sender_account_id', 'idempotency_key'],
                'user_wallet_transfers_sender_idem_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_wallet_transfers');
        Schema::dropIfExists('user_wallet_deposits');
    }
};
''',
)

write(
    "apps/platform/app/Modules/Wallet/Infrastructure/Models/UserWalletDeposit.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class UserWalletDeposit extends Model
{
    use HasUlids;

    public const STATUS_CREATED = 'created';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CREDITED = 'credited';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public const TERMINAL_STATUSES = [self::STATUS_CREDITED, self::STATUS_REJECTED, self::STATUS_EXPIRED];

    protected $table = 'user_wallet_deposits';

    protected $fillable = [
        'account_id',
        'ledger_transaction_id',
        'amount_minor',
        'currency',
        'status',
        'provider_code',
        'provider_reference',
        'checkout_url',
        'idempotency_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Infrastructure/Models/UserWalletTransfer.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Infrastructure\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class UserWalletTransfer extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_POSTED = 'posted';

    protected $table = 'user_wallet_transfers';

    protected $fillable = [
        'sender_account_id',
        'recipient_account_id',
        'amount_minor',
        'currency',
        'status',
        'ledger_transaction_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletCreditor.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Money\Money;

final class UserWalletCreditor
{
    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly UserWalletQueryService $wallet,
    ) {}

    public function credit(UserWalletDeposit $deposit): UserWalletDeposit
    {
        $description = 'Dépôt Wallet via GeniusPay';
        $transaction = $this->posting->post(new PostLedgerTransaction(
            type: 'USER_WALLET_DEPOSIT',
            sourceModule: 'wallet',
            idempotencyKey: "user-wallet-deposit:{$deposit->id}",
            entries: [
                LedgerEntryInput::debit(
                    LedgerAccountReference::system('wasplex.cash.clearing', 'ASSET', 'WP'),
                    Money::of($deposit->amount_minor, 'WP'),
                    $description,
                ),
                LedgerEntryInput::credit(
                    $this->wallet->availableAccountReference($deposit->account_id),
                    Money::of($deposit->amount_minor, 'WP'),
                    $description,
                ),
            ],
            businessReference: $deposit->provider_reference,
            createdBy: $deposit->account_id,
            metadata: ['deposit_id' => $deposit->id, 'provider' => 'geniuspay'],
        ));

        $deposit->update([
            'status' => UserWalletDeposit::STATUS_CREDITED,
            'ledger_transaction_id' => $transaction->id,
        ]);

        $this->wallet->notifyBalanceChanged(
            $deposit->account_id,
            $deposit->amount_minor,
            'deposit',
            'credit',
            $transaction->id,
        );

        return $deposit->refresh();
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletDepositService.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Http\AppException;
use App\Shared\Payments\PaymentProviderContract;
use App\Shared\Payments\ValueObjects\CreatePaymentRequest;
use Illuminate\Support\Str;

final class UserWalletDepositService
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly UserWalletCreditor $creditor,
    ) {}

    public function create(string $accountId, int $amountMinor): UserWalletDeposit
    {
        $deposit = UserWalletDeposit::query()->create([
            'account_id' => $accountId,
            'amount_minor' => $amountMinor,
            'currency' => 'XOF',
            'status' => UserWalletDeposit::STATUS_CREATED,
            'provider_code' => 'geniuspay',
            'idempotency_key' => (string) Str::ulid(),
        ]);

        $appUrl = rtrim((string) config('app.url'), '/');
        $result = $this->provider->createPayment(new CreatePaymentRequest(
            amountMinor: $deposit->amount_minor,
            currency: $deposit->currency,
            internalReference: $deposit->id,
            idempotencyKey: $deposit->idempotency_key,
            successUrl: $appUrl.'/app?tab=wallet&payment=wallet-deposit-success&deposit_id='.$deposit->id,
            errorUrl: $appUrl.'/app?tab=wallet&payment=wallet-deposit-failed&deposit_id='.$deposit->id,
            description: "Dépôt Wallet Wasplex {$deposit->id}",
            metadata: [
                'payment_context' => 'user_wallet_deposit',
                'deposit_id' => $deposit->id,
                'account_id' => $accountId,
            ],
        ));

        $deposit->update([
            'status' => $this->mapStatus($result->normalizedStatus),
            'provider_reference' => $result->providerReference,
            'checkout_url' => $result->checkoutUrl,
            'metadata' => ['environment' => $result->environment],
        ]);

        return $deposit->refresh();
    }

    public function reconcile(string $depositId, string $accountId): UserWalletDeposit
    {
        $deposit = UserWalletDeposit::query()
            ->whereKey($depositId)
            ->where('account_id', $accountId)
            ->first();

        if ($deposit === null) {
            throw new AppException('WALLET_DEPOSIT_NOT_FOUND', 'Dépôt Wallet introuvable.', [], 404);
        }

        if ($deposit->isTerminal()) {
            return $deposit;
        }

        if ($deposit->provider_reference === null) {
            throw new AppException(
                'WALLET_DEPOSIT_NOT_STARTED',
                'Le dépôt n’a pas encore de référence GeniusPay.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        $verified = $this->provider->fetchPaymentStatus($deposit->provider_reference);

        if ($verified->amountMinor !== null && $verified->amountMinor !== $deposit->amount_minor) {
            throw new AppException(
                'WALLET_DEPOSIT_AMOUNT_MISMATCH',
                'Le montant confirmé par GeniusPay ne correspond pas au dépôt attendu.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        if ($verified->currency !== null && $verified->currency !== $deposit->currency) {
            throw new AppException(
                'WALLET_DEPOSIT_CURRENCY_MISMATCH',
                'La devise confirmée par GeniusPay ne correspond pas au dépôt attendu.',
                ['deposit_id' => $deposit->id],
                409,
            );
        }

        if ($verified->normalizedStatus === 'confirmed') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_CONFIRMED]);

            return $this->creditor->credit($deposit->refresh());
        }

        if ($verified->normalizedStatus === 'rejected') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_REJECTED]);
        } elseif ($verified->normalizedStatus === 'expired') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_EXPIRED]);
        } elseif ($verified->normalizedStatus === 'awaiting_payment') {
            $deposit->update(['status' => UserWalletDeposit::STATUS_AWAITING_PAYMENT]);
        }

        return $deposit->refresh();
    }

    private function mapStatus(string $status): string
    {
        return match ($status) {
            'awaiting_payment' => UserWalletDeposit::STATUS_AWAITING_PAYMENT,
            'confirmed' => UserWalletDeposit::STATUS_CONFIRMED,
            'rejected' => UserWalletDeposit::STATUS_REJECTED,
            'expired' => UserWalletDeposit::STATUS_EXPIRED,
            default => UserWalletDeposit::STATUS_CREATED,
        };
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletGeniusPayWebhookHandler.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Http\AppException;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;

final class UserWalletGeniusPayWebhookHandler
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly UserWalletDepositService $deposits,
    ) {}

    public function handle(string $rawPayload, array $headers): void
    {
        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            throw new AppException('WEBHOOK_SIGNATURE_INVALID', 'Signature de webhook invalide.', [], 401);
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);
        $deposit = $event->providerReference !== null
            ? UserWalletDeposit::query()->where('provider_reference', $event->providerReference)->first()
            : null;

        if ($deposit === null) {
            Log::channel('structured')->info('wallet.deposit.webhook.unknown_reference', [
                'provider_reference' => $event->providerReference,
            ]);

            return;
        }

        if ($deposit->isTerminal()) {
            Log::channel('structured')->info('wallet.deposit.webhook.duplicated', ['deposit_id' => $deposit->id]);

            return;
        }

        $this->deposits->reconcile($deposit->id, $deposit->account_id);
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletTransferService.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Wallet\Infrastructure\Models\UserWallet;
use App\Modules\Wallet\Infrastructure\Models\UserWalletTransfer;
use App\Shared\Http\AppException;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\DB;

final class UserWalletTransferService
{
    public function __construct(
        private readonly LedgerPostingContract $posting,
        private readonly UserWalletQueryService $wallet,
    ) {}

    /** @return array{account_id: string, display_name: string, identifier_hint: string} */
    public function resolveRecipient(string $senderAccountId, string $identifier): array
    {
        $identifier = trim($identifier);
        $match = AccountIdentifier::query()
            ->where('value', $identifier)
            ->first();

        if ($match === null && str_contains($identifier, '@')) {
            $match = AccountIdentifier::query()
                ->whereRaw('LOWER(value) = ?', [mb_strtolower($identifier)])
                ->first();
        }

        if ($match === null) {
            throw new AppException('WALLET_RECIPIENT_NOT_FOUND', 'Aucun membre Wasplex ne correspond à cet identifiant.', [], 404);
        }

        if ($match->account_id === $senderAccountId) {
            throw new AppException('WALLET_TRANSFER_SELF', 'Vous ne pouvez pas vous transférer des WP à vous-même.');
        }

        $recipient = Account::query()->find($match->account_id);
        if ($recipient === null || ! $recipient->isActive()) {
            throw new AppException('WALLET_RECIPIENT_UNAVAILABLE', 'Ce destinataire ne peut pas recevoir de transfert.', [], 409);
        }

        $displayName = PersonalProfile::query()
            ->where('account_id', $recipient->id)
            ->value('display_name');

        return [
            'account_id' => $recipient->id,
            'display_name' => is_string($displayName) && trim($displayName) !== '' ? $displayName : 'Membre Wasplex',
            'identifier_hint' => $this->maskIdentifier($match->value),
        ];
    }

    public function transfer(
        string $senderAccountId,
        string $recipientAccountId,
        int $amountMinor,
        string $idempotencyKey,
    ): UserWalletTransfer {
        if ($senderAccountId === $recipientAccountId) {
            throw new AppException('WALLET_TRANSFER_SELF', 'Vous ne pouvez pas vous transférer des WP à vous-même.');
        }

        $recipient = Account::query()->find($recipientAccountId);
        if ($recipient === null || ! $recipient->isActive()) {
            throw new AppException('WALLET_RECIPIENT_UNAVAILABLE', 'Ce destinataire ne peut pas recevoir de transfert.', [], 409);
        }

        $this->wallet->getOrCreate($senderAccountId);
        $this->wallet->getOrCreate($recipientAccountId);

        $transfer = DB::transaction(function () use ($senderAccountId, $recipientAccountId, $amountMinor, $idempotencyKey): UserWalletTransfer {
            $existing = UserWalletTransfer::query()
                ->where('sender_account_id', $senderAccountId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->recipient_account_id !== $recipientAccountId || $existing->amount_minor !== $amountMinor) {
                    throw new AppException(
                        'WALLET_TRANSFER_IDEMPOTENCY_CONFLICT',
                        'Cette demande de transfert a déjà été utilisée avec un autre montant ou destinataire.',
                        [],
                        409,
                    );
                }

                if ($existing->status === UserWalletTransfer::STATUS_POSTED) {
                    return $existing;
                }
            }

            /** @var UserWallet $senderWallet */
            $senderWallet = UserWallet::query()
                ->where('account_id', $senderAccountId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->wallet->balanceMinor($senderAccountId) < $amountMinor) {
                throw new AppException(
                    'WALLET_INSUFFICIENT_BALANCE',
                    'Votre solde disponible est insuffisant pour ce transfert.',
                    ['available_minor' => $this->wallet->balanceMinor($senderAccountId)],
                    409,
                );
            }

            $record = $existing ?? UserWalletTransfer::query()->create([
                'sender_account_id' => $senderAccountId,
                'recipient_account_id' => $recipientAccountId,
                'amount_minor' => $amountMinor,
                'currency' => 'WP',
                'status' => UserWalletTransfer::STATUS_PENDING,
                'idempotency_key' => $idempotencyKey,
            ]);

            $recipientName = PersonalProfile::query()->where('account_id', $recipientAccountId)->value('display_name');
            $senderName = PersonalProfile::query()->where('account_id', $senderAccountId)->value('display_name');
            $sentDescription = 'Transfert envoyé'.($recipientName ? ' à '.$recipientName : '');
            $receivedDescription = 'Transfert reçu'.($senderName ? ' de '.$senderName : '');

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'USER_TRANSFER',
                sourceModule: 'wallet',
                idempotencyKey: "user-transfer:{$record->id}",
                entries: [
                    LedgerEntryInput::debit(
                        $this->wallet->availableAccountReference($senderAccountId),
                        Money::of($amountMinor, 'WP'),
                        $sentDescription,
                    ),
                    LedgerEntryInput::credit(
                        $this->wallet->availableAccountReference($recipientAccountId),
                        Money::of($amountMinor, 'WP'),
                        $receivedDescription,
                    ),
                ],
                businessReference: $record->id,
                createdBy: $senderAccountId,
                metadata: ['recipient_account_id' => $recipientAccountId],
            ));

            $record->update([
                'status' => UserWalletTransfer::STATUS_POSTED,
                'ledger_transaction_id' => $transaction->id,
            ]);

            return $record->refresh();
        });

        if ($transfer->ledger_transaction_id !== null) {
            $this->wallet->notifyBalanceChanged(
                $senderAccountId,
                $amountMinor,
                'transfer',
                'debit',
                $transfer->ledger_transaction_id,
            );
            $this->wallet->notifyBalanceChanged(
                $recipientAccountId,
                $amountMinor,
                'transfer',
                'credit',
                $transfer->ledger_transaction_id,
            );
        }

        return $transfer;
    }

    private function maskIdentifier(string $value): string
    {
        if (str_contains($value, '@')) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');

            return mb_substr($local, 0, 1).'•••@'.$domain;
        }

        return '••••'.mb_substr($value, -4);
    }
}
''',
)

# Requête Wallet : vraies statistiques serveur, non limitées à la page d'historique.
replace(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletQueryService.php",
    "use App\\Modules\\Wallet\\Infrastructure\\Models\\UserWallet;",
    "use App\\Modules\\Wallet\\Infrastructure\\Models\\UserWallet;\nuse App\\Modules\\Wallet\\Infrastructure\\Models\\UserWalletDeposit;",
)

replace(
    "apps/platform/app/Modules/Wallet/Application/Services/UserWalletQueryService.php",
    '''    /**
     * Best-effort (docs/chantiers/P011-CHANTIER.md §2.4): the Ledger''',
    '''    /** @return array{today_credits_minor: int, month_credits_minor: int, month_debits_minor: int, pending_deposits_minor: int} */
    public function summary(string $accountId): array
    {
        $account = LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();

        $todayCredits = 0;
        $monthCredits = 0;
        $monthDebits = 0;

        if ($account !== null) {
            $todayCredits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_CREDIT)
                ->where('created_at', '>=', now()->startOfDay())
                ->sum('amount_minor');

            $monthCredits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_CREDIT)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount_minor');

            $monthDebits = (int) LedgerEntry::query()
                ->where('account_id', $account->id)
                ->where('direction', LedgerEntry::DIRECTION_DEBIT)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount_minor');
        }

        $pendingDeposits = (int) UserWalletDeposit::query()
            ->where('account_id', $accountId)
            ->whereIn('status', [
                UserWalletDeposit::STATUS_CREATED,
                UserWalletDeposit::STATUS_AWAITING_PAYMENT,
                UserWalletDeposit::STATUS_CONFIRMED,
            ])
            ->sum('amount_minor');

        return [
            'today_credits_minor' => $todayCredits,
            'month_credits_minor' => $monthCredits,
            'month_debits_minor' => $monthDebits,
            'pending_deposits_minor' => $pendingDeposits,
        ];
    }

    /**
     * Best-effort (docs/chantiers/P011-CHANTIER.md §2.4): the Ledger''',
)

write(
    "apps/platform/app/Modules/Wallet/Http/Controllers/User/WalletController.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\Wallet\Http\Controllers\User;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Wallet\Application\Services\UserWalletDepositService;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Application\Services\UserWalletTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WalletController extends Controller
{
    public function __construct(
        private readonly UserWalletQueryService $wallet,
        private readonly UserWalletDepositService $deposits,
        private readonly UserWalletTransferService $transfers,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $this->wallet->getOrCreate($account->id);

        return response()->json([
            'balance_minor' => $this->wallet->balanceMinor($account->id),
            'currency' => 'WP',
            'summary' => $this->wallet->summary($account->id),
            'capabilities' => [
                'deposit' => true,
                'transfer' => true,
                'withdrawal' => false,
                'withdrawal_reason' => 'Le rail de retrait externe n’est pas encore disponible chez le prestataire de paiement.',
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();

        $entries = $this->wallet->history($account->id)->through(fn ($entry) => [
            'id' => $entry->id,
            'direction' => $entry->direction,
            'amount_minor' => $entry->amount_minor,
            'description' => $entry->description,
            'type' => $entry->transaction?->type,
            'created_at' => $entry->created_at?->toIso8601String(),
        ]);

        return response()->json(['history' => $entries]);
    }

    public function createDeposit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['required', 'integer', 'min:200'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $deposit = $this->deposits->create($account->id, (int) $data['amount_minor']);

        return response()->json(['deposit' => $deposit], 201);
    }

    public function refreshDeposit(Request $request, string $deposit): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $updated = $this->deposits->reconcile($deposit, $account->id);

        return response()->json(['deposit' => $updated]);
    }

    public function resolveRecipient(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
        ]);

        /** @var Account $account */
        $account = $request->user();

        return response()->json([
            'recipient' => $this->transfers->resolveRecipient($account->id, (string) $data['identifier']),
        ]);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'recipient_account_id' => ['required', 'string'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        /** @var Account $account */
        $account = $request->user();
        $transfer = $this->transfers->transfer(
            $account->id,
            (string) $data['recipient_account_id'],
            (int) $data['amount_minor'],
            (string) $data['idempotency_key'],
        );

        return response()->json(['transfer' => $transfer]);
    }
}
''',
)

write(
    "apps/platform/app/Modules/Wallet/Http/routes/api.php",
    r'''<?php

declare(strict_types=1);

use App\Modules\Identity\Http\Middleware\EnsureSessionNotRevoked;
use App\Modules\Wallet\Http\Controllers\User\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureSessionNotRevoked::class])
    ->prefix('me/wallet')
    ->group(function (): void {
        Route::get('/', [WalletController::class, 'show']);
        Route::get('/history', [WalletController::class, 'history']);
        Route::post('/deposits', [WalletController::class, 'createDeposit']);
        Route::post('/deposits/{deposit}/refresh', [WalletController::class, 'refreshDeposit']);
        Route::post('/transfers/recipient', [WalletController::class, 'resolveRecipient']);
        Route::post('/transfers', [WalletController::class, 'transfer']);
    });
''',
)

# Webhook GeniusPay unique : route historique /api/webhooks/geniuspay devient le
# point d'entrée commun, mais ne dispatch qu'au module propriétaire de la ref.
write(
    "apps/platform/app/Modules/AdvertiserWallet/Http/Controllers/Webhook/GeniusPayWebhookController.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Http\Controllers\Webhook;

use App\Modules\AdvertiserWallet\Application\Services\GeniusPayWebhookHandler as AdvertiserGeniusPayWebhookHandler;
use App\Modules\AdvertiserWallet\Application\Services\InvalidWebhookSignatureException;
use App\Modules\AdvertiserWallet\Infrastructure\Models\AdvertiserWalletDeposit;
use App\Modules\Subscriptions\Application\Services\SubscriptionPaymentWebhookHandler;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Wallet\Application\Services\UserWalletGeniusPayWebhookHandler;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Point d'entrée GeniusPay unifié. Le webhook configuré dans GeniusPay peut
 * rester /api/webhooks/geniuspay pour les dépôts annonceurs, abonnements et
 * dépôts Wallet utilisateur. Chaque module conserve son propre handler.
 */
final class GeniusPayWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentProviderContract $provider,
        private readonly AdvertiserGeniusPayWebhookHandler $advertiserHandler,
        private readonly SubscriptionPaymentWebhookHandler $subscriptionHandler,
        private readonly UserWalletGeniusPayWebhookHandler $userWalletHandler,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $headers = [
            'X-GeniusPay-Signature' => $request->header('X-GeniusPay-Signature'),
            'X-GeniusPay-Timestamp' => $request->header('X-GeniusPay-Timestamp'),
            'X-Webhook-Signature' => $request->header('X-Webhook-Signature'),
            'X-Webhook-Timestamp' => $request->header('X-Webhook-Timestamp'),
        ];

        if (! $this->provider->verifyWebhookSignature($rawPayload, $headers)) {
            throw new InvalidWebhookSignatureException;
        }

        $event = $this->provider->parseWebhookPayload($rawPayload);
        $reference = $event->providerReference;

        if ($reference !== null && SubscriptionPayment::query()->where('provider_reference', $reference)->exists()) {
            $this->subscriptionHandler->handle($rawPayload, $headers);
        } elseif ($reference !== null && UserWalletDeposit::query()->where('provider_reference', $reference)->exists()) {
            $this->userWalletHandler->handle($rawPayload, $headers);
        } elseif ($reference !== null && AdvertiserWalletDeposit::query()->where('provider_reference', $reference)->exists()) {
            $this->advertiserHandler->handle($rawPayload, $headers);
        } else {
            // Conserve l'audit historique des références inconnues côté
            // AdvertiserWallet sans polluer cet audit pour les références
            // appartenant à un autre module connu.
            $this->advertiserHandler->handle($rawPayload, $headers);
        }

        return response()->json(['received' => true]);
    }
}
''',
)

# Corrige également l'état GeniusPay admin lorsqu'aucune configuration DB
# n'existe encore (l'ancienne méthode référençait une variable $data absente).
write(
    "apps/platform/app/Modules/AdvertiserWallet/Application/Services/GeniusPayConfigurationService.php",
    r'''<?php

declare(strict_types=1);

namespace App\Modules\AdvertiserWallet\Application\Services;

use App\Modules\AdvertiserWallet\Infrastructure\Models\GeniusPayConfiguration;
use App\Shared\Payments\PaymentProviderContract;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class GeniusPayConfigurationService
{
    /** @return array<string, mixed> */
    public function status(): array
    {
        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();

        return [
            'environment' => 'sandbox',
            'configured' => $configuration !== null,
            'api_key_configured' => $configuration?->api_key !== null,
            'api_secret_configured' => $configuration?->api_secret !== null,
            'webhook_secret_configured' => $configuration?->webhook_secret !== null,
            'api_key_hint' => $configuration !== null ? $this->mask($configuration->api_key) : null,
            'updated_at' => $configuration?->updated_at,
            'webhook_url' => rtrim((string) config('app.url'), '/').'/api/webhooks/geniuspay',
            'webhook_scope' => 'advertiser_wallet, subscriptions, user_wallet',
        ];
    }

    /** @param array<string, string|null> $data */
    public function save(array $data, string $actorAccountId): array
    {
        $configuration = GeniusPayConfiguration::query()->latest('updated_at')->first();

        if ($configuration === null && (! $data['api_key'] || ! $data['api_secret'] || ! $data['webhook_secret'])) {
            throw ValidationException::withMessages([
                'api_key' => 'Les trois clés sandbox sont obligatoires lors de la première configuration.',
            ]);
        }

        $values = [
            'environment' => 'sandbox',
            'api_key' => $data['api_key'] ?: $configuration?->api_key,
            'api_secret' => $data['api_secret'] ?: $configuration?->api_secret,
            'webhook_secret' => $data['webhook_secret'] ?: $configuration?->webhook_secret,
            'updated_by' => $actorAccountId,
        ];

        $configuration === null
            ? GeniusPayConfiguration::query()->create($values)
            : $configuration->update($values);

        $this->applyToRuntime(GeniusPayConfiguration::query()->latest('updated_at')->firstOrFail());
        Log::info('geniuspay.sandbox_configuration_updated', ['actor_account_id' => $actorAccountId]);

        return $this->status();
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return app(PaymentProviderContract::class)->testConnection();
    }

    public function applyToRuntime(GeniusPayConfiguration $configuration): void
    {
        config([
            'services.geniuspay.environment' => 'sandbox',
            'services.geniuspay.api_key' => $configuration->api_key,
            'services.geniuspay.api_secret' => $configuration->api_secret,
            'services.geniuspay.webhook_secret' => $configuration->webhook_secret,
        ]);
    }

    private function mask(string $value): string
    {
        return mb_substr($value, 0, min(10, mb_strlen($value))).'••••••••';
    }
}
''',
)

# ---------------------------------------------------------------------------
# UI Abonnement : même onglet, retour Mon Espace et réconciliation serveur.
# ---------------------------------------------------------------------------
replace(
    "apps/platform/resources/js/Components/SubscriptionPanel.vue",
    "import { computed, ref } from 'vue';",
    "import { computed, onMounted, ref } from 'vue';",
)
replace(
    "apps/platform/resources/js/Components/SubscriptionPanel.vue",
    "const error = ref<string | null>(null);\nconst showPlans = ref(false);",
    "const error = ref<string | null>(null);\nconst paymentNotice = ref<string | null>(null);\nconst showPlans = ref(false);",
)
replace(
    "apps/platform/resources/js/Components/SubscriptionPanel.vue",
    '''        if (data.payment?.checkout_url) {
            window.open(data.payment.checkout_url, '_blank', 'noopener,noreferrer');
        }

        await load();
        if (!data.payment?.checkout_url) showPlans.value = false;''',
    '''        if (data.payment?.checkout_url) {
            window.location.assign(data.payment.checkout_url);
            return;
        }

        await load();
        showPlans.value = false;''',
)
replace(
    "apps/platform/resources/js/Components/SubscriptionPanel.vue",
    "void load();",
    '''async function reconcilePaymentReturn(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const paymentState = params.get('payment');
    const paymentId = params.get('payment_id');

    if (paymentState === 'subscription-failed') {
        paymentNotice.value = 'Le paiement n’a pas été finalisé. Votre abonnement n’a pas été modifié.';
    } else if (paymentState === 'subscription-success' && paymentId) {
        try {
            const { data } = await http.post(`/subscriptions/payments/${paymentId}/refresh`);
            paymentNotice.value =
                data.payment?.status === 'activated'
                    ? 'Paiement confirmé. Votre nouvel abonnement est maintenant actif.'
                    : 'Paiement reçu. La confirmation GeniusPay est encore en cours.';
        } catch (e) {
            paymentNotice.value =
                (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
                'Le paiement est en cours de vérification. Vous pouvez actualiser votre abonnement.';
        }
    }

    if (paymentState?.startsWith('subscription-')) {
        params.delete('payment');
        params.delete('payment_id');
        const query = params.toString();
        window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
    }
}

onMounted(async () => {
    await reconcilePaymentReturn();
    await load();
});''',
)
replace(
    "apps/platform/resources/js/Components/SubscriptionPanel.vue",
    "<template>\n    <div>\n",
    '''<template>
    <div>
        <p
            v-if="paymentNotice"
            class="border-wpx-cyan/25 bg-wpx-cyan/10 text-wpx-cyan rounded-wpx-md mb-3 border px-3.5 py-3 text-xs leading-relaxed"
        >
            {{ paymentNotice }}
        </p>
''',
)

# UserShell comprend enfin tab=wallet / tab=espace dans les URL de retour.
replace(
    "apps/platform/resources/js/Pages/Identity/UserShell.vue",
    "const activeTab = ref<(typeof tabs)[number]['key']>('feed');",
    '''type TabKey = (typeof tabs)[number]['key'];
const initialTab = (() : TabKey => {
    if (typeof window === 'undefined') return 'feed';
    const requested = new URLSearchParams(window.location.search).get('tab');

    return tabs.some((tab) => tab.key === requested) ? (requested as TabKey) : 'feed';
})();
const activeTab = ref<TabKey>(initialTab);''',
)
replace(
    "apps/platform/resources/js/Pages/Identity/UserShell.vue",
    "onMounted(loadMe);",
    '''onMounted(async () => {
    await loadMe();
    const section = new URLSearchParams(window.location.search).get('section');
    if (activeTab.value === 'espace' && section === 'subscription') {
        await nextTick();
        scrollToSubscription();
    }
});''',
)

# ---------------------------------------------------------------------------
# Wallet V2 visuel et fonctionnel.
# ---------------------------------------------------------------------------
write(
    "apps/platform/resources/js/Components/WalletPanel.vue",
    r'''<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

interface HistoryEntry {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    created_at: string | null;
}

interface WalletSummary {
    today_credits_minor: number;
    month_credits_minor: number;
    month_debits_minor: number;
    pending_deposits_minor: number;
}

interface Recipient {
    account_id: string;
    display_name: string;
    identifier_hint: string;
}

const props = defineProps<{ accountLabel?: string; phoneNumber?: string | null }>();
const emit = defineEmits<{ goToSubscription: [] }>();

const balance = ref<number | null>(null);
const summary = ref<WalletSummary>({
    today_credits_minor: 0,
    month_credits_minor: 0,
    month_debits_minor: 0,
    pending_deposits_minor: 0,
});
const history = ref<HistoryEntry[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const notice = ref<string | null>(null);
const historyOpen = ref(true);

const showDeposit = ref(false);
const depositAmount = ref<number | null>(null);
const depositBusy = ref(false);
const depositError = ref<string | null>(null);

const showTransfer = ref(false);
const recipientIdentifier = ref('');
const recipient = ref<Recipient | null>(null);
const recipientBusy = ref(false);
const transferAmount = ref<number | null>(null);
const transferBusy = ref(false);
const transferError = ref<string | null>(null);
const transferIdempotency = ref('');

const showWithdrawal = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const page = usePage<{ auth: AuthShared }>();

const availableLabel = computed(() => numberFormatter.format(balance.value ?? 0));

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [walletRes, historyRes] = await Promise.all([http.get('/me/wallet'), http.get('/me/wallet/history')]);
        balance.value = walletRes.data.balance_minor;
        summary.value = walletRes.data.summary ?? summary.value;
        history.value = historyRes.data.history.data ?? historyRes.data.history;
    } catch (e) {
        loadError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le Wallet est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function describe(entry: HistoryEntry): string {
    if (entry.description) return entry.description;
    if (entry.type === 'USER_WALLET_DEPOSIT') return 'Dépôt Wallet';
    if (entry.type === 'USER_TRANSFER') return entry.direction === 'credit' ? 'Transfert reçu' : 'Transfert envoyé';

    return entry.type ?? (entry.direction === 'credit' ? 'Crédit Wallet' : 'Débit Wallet');
}

function openDepositModal(): void {
    depositAmount.value = null;
    depositError.value = null;
    showDeposit.value = true;
}

async function startDeposit(): Promise<void> {
    if (!depositAmount.value || depositAmount.value < 200) {
        depositError.value = 'Le dépôt minimum est de 200 FCFA.';
        return;
    }

    depositBusy.value = true;
    depositError.value = null;
    try {
        const { data } = await http.post('/me/wallet/deposits', { amount_minor: Math.trunc(depositAmount.value) });
        if (!data.deposit?.checkout_url) {
            throw new Error('Checkout GeniusPay indisponible');
        }

        window.location.assign(data.deposit.checkout_url);
    } catch (e) {
        depositError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de démarrer le dépôt.';
    } finally {
        depositBusy.value = false;
    }
}

function openTransferModal(): void {
    recipientIdentifier.value = '';
    recipient.value = null;
    transferAmount.value = null;
    transferError.value = null;
    transferIdempotency.value = '';
    showTransfer.value = true;
}

async function resolveRecipient(): Promise<void> {
    if (!recipientIdentifier.value.trim()) return;

    recipientBusy.value = true;
    transferError.value = null;
    recipient.value = null;
    try {
        const { data } = await http.post('/me/wallet/transfers/recipient', {
            identifier: recipientIdentifier.value.trim(),
        });
        recipient.value = data.recipient;
    } catch (e) {
        transferError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Destinataire introuvable.';
    } finally {
        recipientBusy.value = false;
    }
}

async function submitTransfer(): Promise<void> {
    if (!recipient.value || !transferAmount.value || transferAmount.value <= 0) return;
    if (transferAmount.value > (balance.value ?? 0)) {
        transferError.value = 'Votre solde disponible est insuffisant.';
        return;
    }

    transferBusy.value = true;
    transferError.value = null;
    transferIdempotency.value ||= crypto.randomUUID();
    try {
        await http.post('/me/wallet/transfers', {
            recipient_account_id: recipient.value.account_id,
            amount_minor: Math.trunc(transferAmount.value),
            idempotency_key: transferIdempotency.value,
        });
        notice.value = `${numberFormatter.format(Math.trunc(transferAmount.value))} WP transférés à ${recipient.value.display_name}.`;
        showTransfer.value = false;
        await load();
    } catch (e) {
        transferError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le transfert a échoué.';
    } finally {
        transferBusy.value = false;
    }
}

async function processPaymentReturn(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const paymentState = params.get('payment');
    const depositId = params.get('deposit_id');

    if (paymentState === 'wallet-deposit-failed') {
        notice.value = 'Le paiement n’a pas été finalisé. Aucun WP n’a été ajouté.';
    } else if (paymentState === 'wallet-deposit-success' && depositId) {
        try {
            const { data } = await http.post(`/me/wallet/deposits/${depositId}/refresh`);
            notice.value =
                data.deposit?.status === 'credited'
                    ? `${numberFormatter.format(data.deposit.amount_minor)} WP ajoutés à votre Wallet.`
                    : 'Paiement reçu. La confirmation GeniusPay est encore en cours.';
        } catch (e) {
            notice.value =
                (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
                'Votre paiement est en cours de vérification.';
        }
    }

    if (paymentState?.startsWith('wallet-deposit-')) {
        params.delete('payment');
        params.delete('deposit_id');
        const query = params.toString();
        window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
    }
}

defineExpose({ load });

onMounted(async () => {
    await processPaymentReturn();
    await load();
});

useEcho(`wallet.${page.props.auth.account.id}`, '.wallet.balance.changed', load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <p
            v-if="notice"
            class="border-wpx-cyan/25 bg-wpx-cyan/10 text-wpx-cyan rounded-wpx-md border px-3.5 py-3 text-xs leading-relaxed"
        >
            {{ notice }}
        </p>
        <p v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs">
            {{ loadError }}
        </p>

        <section
            class="rounded-wpx-xl from-wpx-orange via-wpx-gold to-wpx-orange wpx-motion-safe shadow-wpx-card-dark relative overflow-hidden bg-gradient-to-br p-5"
        >
            <div
                class="wpx-motion-safe pointer-events-none absolute inset-y-0 -left-14 w-20 animate-[wpxShine_4.2s_ease-in-out_infinite] bg-gradient-to-r from-white/0 via-white/30 to-white/0"
            />
            <div class="relative">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-navy-950/60 text-[10px] font-extrabold tracking-[0.18em]">WASPLEX WALLET</p>
                        <p v-if="props.accountLabel" class="text-wpx-navy-950 mt-1 text-sm font-bold">{{ props.accountLabel }}</p>
                    </div>
                    <span class="bg-wpx-navy-950/10 text-wpx-navy-950 rounded-full px-2.5 py-1 text-[10px] font-bold">
                        Disponible
                    </span>
                </div>

                <p class="text-wpx-navy-950/65 mt-6 text-xs font-bold">Solde utilisable</p>
                <p class="text-wpx-navy-950 mt-0.5 text-[2.65rem] leading-none font-extrabold tabular-nums">
                    <span v-if="loading">…</span>
                    <template v-else>{{ availableLabel }} <span class="text-xl">WP</span></template>
                </p>
                <p class="text-wpx-navy-950/60 mt-1 text-xs">≈ {{ availableLabel }} FCFA · 1 WP = 1 FCFA</p>
                <p v-if="props.phoneNumber" class="text-wpx-navy-950/55 mt-4 font-mono text-[11px]">{{ props.phoneNumber }}</p>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2.5">
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="openDepositModal"
            >
                <span class="bg-wpx-success/12 text-wpx-success flex h-9 w-9 items-center justify-center rounded-full text-xl">↓</span>
                <span class="text-wpx-success text-xs font-bold">Déposer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="openTransferModal"
            >
                <span class="bg-wpx-blue/12 text-wpx-blue flex h-9 w-9 items-center justify-center rounded-full text-lg">⇄</span>
                <span class="text-wpx-blue text-xs font-bold">Transférer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="showWithdrawal = true"
            >
                <span class="bg-wpx-orange/12 text-wpx-orange flex h-9 w-9 items-center justify-center rounded-full text-xl">↑</span>
                <span class="text-wpx-orange text-xs font-bold">Retirer</span>
                <span class="text-wpx-muted-dark text-[9px]">Bientôt</span>
            </button>
        </section>

        <section class="grid grid-cols-3 gap-2.5">
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-success text-base font-bold">+{{ numberFormatter.format(summary.today_credits_minor) }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Aujourd’hui</p>
            </div>
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-blue text-base font-bold">+{{ numberFormatter.format(summary.month_credits_minor) }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Ce mois</p>
            </div>
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-gold text-base font-bold">{{ numberFormatter.format(summary.pending_deposits_minor) }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">En attente</p>
            </div>
        </section>

        <button
            type="button"
            class="from-wpx-navy-750 to-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex items-center gap-3 border bg-gradient-to-br p-3.5 text-left"
            @click="emit('goToSubscription')"
        >
            <span class="bg-wpx-blue/18 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
                <svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2l6 6-6 14-6-14z" fill="#4FA3FF" /></svg>
            </span>
            <span class="flex-1">
                <span class="text-wpx-white-soft block text-sm font-bold">Mon abonnement</span>
                <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">Gérez votre plan et vos avantages</span>
            </span>
            <span class="text-wpx-blue text-xs font-bold">Voir ›</span>
        </button>

        <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 p-3.5 text-left"
                @click="historyOpen = !historyOpen"
            >
                <span>
                    <span class="text-wpx-white-soft block text-sm font-bold">Activité du Wallet</span>
                    <span class="text-wpx-muted-dark mt-0.5 block text-[10px]">Publicités, dépôts, transferts et autres mouvements</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="bg-wpx-blue/12 text-wpx-blue rounded-full px-2 py-0.5 text-[10px] font-bold">{{ history.length }}</span>
                    <span class="text-wpx-muted-dark text-sm">{{ historyOpen ? '⌃' : '⌄' }}</span>
                </span>
            </button>

            <div v-if="historyOpen" class="border-wpx-border-dark border-t px-3.5 pb-3.5">
                <p v-if="loading" class="text-wpx-muted-dark pt-3 text-sm">Chargement…</p>
                <ul v-else class="divide-wpx-border-dark divide-y">
                    <li v-for="entry in history" :key="entry.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-wpx-white-soft truncate text-sm font-semibold">{{ describe(entry) }}</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                                {{ entry.created_at ? new Date(entry.created_at).toLocaleString('fr-FR') : '' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-bold tabular-nums"
                            :class="entry.direction === 'credit' ? 'text-wpx-success-light' : 'text-wpx-orange'"
                        >
                            {{ entry.direction === 'credit' ? '+' : '−' }}{{ numberFormatter.format(entry.amount_minor) }} WP
                        </span>
                    </li>
                    <li v-if="history.length === 0" class="text-wpx-muted-dark py-5 text-center text-sm">
                        Aucun mouvement pour le moment.
                    </li>
                </ul>
            </div>
        </section>

        <Teleport to="body">
            <div v-if="showDeposit" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="showDeposit = false">
                <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-wpx-white-soft text-lg font-bold">Déposer dans mon Wallet</h2>
                            <p class="text-wpx-muted-dark mt-1 text-xs">Paiement sécurisé via GeniusPay · minimum 200 FCFA</p>
                        </div>
                        <button type="button" class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full" @click="showDeposit = false">×</button>
                    </div>
                    <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase">Montant</label>
                    <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-md mt-2 flex items-center border px-3">
                        <input
                            v-model.number="depositAmount"
                            type="number"
                            min="200"
                            inputmode="numeric"
                            class="text-wpx-white-soft min-w-0 flex-1 bg-transparent py-3.5 text-xl font-bold outline-none"
                            placeholder="5 000"
                        />
                        <span class="text-wpx-gold text-sm font-bold">FCFA</span>
                    </div>
                    <p class="text-wpx-muted-dark mt-2 text-xs">Après confirmation : {{ numberFormatter.format(depositAmount ?? 0) }} WP seront crédités.</p>
                    <p v-if="depositError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md mt-3 p-3 text-xs">{{ depositError }}</p>
                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-wpx-md mt-5 w-full bg-gradient-to-br px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                        :disabled="depositBusy"
                        @click="startDeposit"
                    >
                        {{ depositBusy ? 'Ouverture de GeniusPay…' : 'Continuer vers GeniusPay' }}
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showTransfer" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="showTransfer = false">
                <div class="bg-wpx-navy-950 border-wpx-border-dark max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-wpx-white-soft text-lg font-bold">Transférer des WP</h2>
                            <p class="text-wpx-muted-dark mt-1 text-xs">Le transfert est immédiat et enregistré dans le Grand Livre.</p>
                        </div>
                        <button type="button" class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full" @click="showTransfer = false">×</button>
                    </div>

                    <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase">Téléphone ou e-mail Wasplex</label>
                    <div class="mt-2 flex gap-2">
                        <input
                            v-model="recipientIdentifier"
                            type="text"
                            class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft rounded-wpx-md min-w-0 flex-1 border px-3 py-3 text-sm outline-none"
                            placeholder="+225… ou membre@email.com"
                            @input="recipient = null"
                        />
                        <button
                            type="button"
                            class="bg-wpx-blue/15 text-wpx-blue rounded-wpx-md px-3 text-xs font-bold disabled:opacity-50"
                            :disabled="recipientBusy || !recipientIdentifier.trim()"
                            @click="resolveRecipient"
                        >
                            {{ recipientBusy ? '…' : 'Vérifier' }}
                        </button>
                    </div>

                    <div v-if="recipient" class="border-wpx-success/25 bg-wpx-success/8 rounded-wpx-md mt-3 border p-3">
                        <p class="text-wpx-success-light text-sm font-bold">{{ recipient.display_name }}</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">{{ recipient.identifier_hint }}</p>
                    </div>

                    <template v-if="recipient">
                        <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase">Montant</label>
                        <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-md mt-2 flex items-center border px-3">
                            <input
                                v-model.number="transferAmount"
                                type="number"
                                min="1"
                                :max="balance ?? 0"
                                inputmode="numeric"
                                class="text-wpx-white-soft min-w-0 flex-1 bg-transparent py-3.5 text-xl font-bold outline-none"
                                placeholder="100"
                            />
                            <span class="text-wpx-blue text-sm font-bold">WP</span>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 text-xs">Disponible : {{ availableLabel }} WP</p>
                    </template>

                    <p v-if="transferError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md mt-3 p-3 text-xs">{{ transferError }}</p>

                    <button
                        v-if="recipient"
                        type="button"
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md mt-5 w-full bg-gradient-to-br px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                        :disabled="transferBusy || !transferAmount || transferAmount <= 0"
                        @click="submitTransfer"
                    >
                        {{ transferBusy ? 'Transfert…' : `Confirmer ${numberFormatter.format(transferAmount ?? 0)} WP` }}
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="showWithdrawal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="showWithdrawal = false">
                <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                    <div class="bg-wpx-orange/12 text-wpx-orange flex h-12 w-12 items-center justify-center rounded-full text-xl">↑</div>
                    <h2 class="text-wpx-white-soft mt-4 text-lg font-bold">Retrait en préparation</h2>
                    <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                        Votre Wallet est prêt pour le parcours de retrait, mais GeniusPay ne fournit pas encore dans notre documentation un endpoint de payout vérifiable. Wasplex n’affichera jamais un faux retrait.
                    </p>
                    <p class="text-wpx-muted-dark mt-3 text-xs leading-relaxed">
                        Dès qu’un rail de sortie réel sera disponible, le retrait intégrera KYC, destination vérifiée, frais affichés avant validation et preuve de règlement.
                    </p>
                    <button type="button" class="bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md mt-5 w-full px-4 py-3 text-sm font-bold" @click="showWithdrawal = false">Compris</button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
''',
)

# ---------------------------------------------------------------------------
# Tests de régression et documentation du hotfix.
# ---------------------------------------------------------------------------
write(
    "apps/platform/tests/Feature/Wallet/WalletV2Test.php",
    r'''<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\AccountIdentifier;
use App\Modules\Identity\Infrastructure\Models\PersonalProfile;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPayment;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Modules\Wallet\Infrastructure\Models\UserWalletDeposit;
use App\Modules\Wallet\Infrastructure\Models\UserWalletTransfer;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
});

function walletV2Credit(string $accountId, int $amountMinor, string $key): void
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

function walletV2Recipient(string $email, string $displayName = 'Destinataire Test'): Account
{
    $account = Account::query()->create([
        'status' => 'active',
        'password' => Hash::make('Password123!'),
        'country_code' => 'CI',
        'language' => 'fr',
        'timezone' => 'UTC',
    ]);

    AccountIdentifier::query()->create([
        'account_id' => $account->id,
        'type' => 'email',
        'value' => $email,
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    PersonalProfile::query()->create([
        'account_id' => $account->id,
        'display_name' => $displayName,
    ]);

    return $account;
}

function walletV2PublishPaidPlan(string $code = 'PREMIUM'): SubscriptionPlanVersion
{
    $version = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($query) => $query->where('code', $code))
        ->firstOrFail();
    $version->update([
        'status' => SubscriptionPlanVersion::STATUS_PUBLISHED,
        'price_minor' => 5000,
        'effective_from' => now(),
    ]);

    return $version->refresh();
}

it('creates a user Wallet deposit with a Wallet return URL and credits it only after server verification', function (): void {
    registerAndLogin('wallet-deposit-v2@example.com');
    $accountId = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-deposit-v2@example.com'))->firstOrFail()->id;

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'WALLET_DEP_1',
            'checkout_url' => 'https://geniuspay.ci/checkout/WALLET_DEP_1',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);

    $response = test()->postJson('/api/me/wallet/deposits', ['amount_minor' => 5000])->assertCreated();
    $depositId = $response->json('deposit.id');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_ends_with($request->url(), '/payments')
            && str_contains((string) ($payload['success_url'] ?? ''), '/app?tab=wallet&payment=wallet-deposit-success')
            && ($payload['metadata']['payment_context'] ?? null) === 'user_wallet_deposit';
    });

    expect(app(UserWalletQueryService::class)->balanceMinor($accountId))->toBe(0);

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/WALLET_DEP_1' => Http::response([
            'reference' => 'WALLET_DEP_1',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    test()->postJson("/api/me/wallet/deposits/{$depositId}/refresh")
        ->assertOk()
        ->assertJsonPath('deposit.status', UserWalletDeposit::STATUS_CREDITED);

    test()->postJson("/api/me/wallet/deposits/{$depositId}/refresh")->assertOk();

    expect(app(UserWalletQueryService::class)->balanceMinor($accountId))->toBe(5000);
    expect(UserWalletDeposit::query()->whereKey($depositId)->value('ledger_transaction_id'))->not->toBeNull();
});

it('transfers WP atomically, resolves a minimal recipient identity and is idempotent', function (): void {
    registerAndLogin('wallet-sender-v2@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-sender-v2@example.com'))->firstOrFail();
    $recipient = walletV2Recipient('wallet-recipient-v2@example.com', 'Awa Test');
    walletV2Credit($sender->id, 1000, 'wallet-v2-credit-1');

    test()->postJson('/api/me/wallet/transfers/recipient', ['identifier' => 'wallet-recipient-v2@example.com'])
        ->assertOk()
        ->assertJsonPath('recipient.account_id', $recipient->id)
        ->assertJsonPath('recipient.display_name', 'Awa Test');

    $payload = [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 350,
        'idempotency_key' => 'client-transfer-unique-1',
    ];

    test()->postJson('/api/me/wallet/transfers', $payload)
        ->assertOk()
        ->assertJsonPath('transfer.status', UserWalletTransfer::STATUS_POSTED);
    test()->postJson('/api/me/wallet/transfers', $payload)->assertOk();

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(650);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(350);
    expect(UserWalletTransfer::query()->count())->toBe(1);
});

it('refuses self transfers and transfers above the available balance', function (): void {
    registerAndLogin('wallet-limits-v2@example.com');
    $sender = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'wallet-limits-v2@example.com'))->firstOrFail();
    $recipient = walletV2Recipient('wallet-limits-recipient@example.com');
    walletV2Credit($sender->id, 100, 'wallet-v2-credit-2');

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $sender->id,
        'amount_minor' => 10,
        'idempotency_key' => 'self-transfer',
    ])->assertUnprocessable();

    test()->postJson('/api/me/wallet/transfers', [
        'recipient_account_id' => $recipient->id,
        'amount_minor' => 101,
        'idempotency_key' => 'too-much-transfer',
    ])->assertConflict();

    expect(app(UserWalletQueryService::class)->balanceMinor($sender->id))->toBe(100);
    expect(app(UserWalletQueryService::class)->balanceMinor($recipient->id))->toBe(0);
});

it('returns subscription payments to Mon Espace and activates an upgrade from a server-side refresh', function (): void {
    registerAndLogin('subscription-return-v2@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'subscription-return-v2@example.com'))->firstOrFail();

    test()->getJson('/api/subscriptions/current')->assertOk();
    $premium = walletV2PublishPaidPlan();
    $subscription = UserSubscription::query()->where('account_id', $account->id)->where('status', 'active')->firstOrFail();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'SUB_RETURN_V2',
            'checkout_url' => 'https://geniuspay.ci/checkout/SUB_RETURN_V2',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);

    $response = test()->postJson("/api/subscriptions/{$subscription->id}/upgrade", [
        'plan_version_id' => $premium->id,
    ])->assertOk();
    $paymentId = $response->json('payment.id');

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return str_ends_with($request->url(), '/payments')
            && str_contains((string) ($payload['success_url'] ?? ''), '/app?tab=espace&section=subscription')
            && ($payload['metadata']['payment_context'] ?? null) === 'subscription';
    });

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/SUB_RETURN_V2' => Http::response([
            'reference' => 'SUB_RETURN_V2',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    test()->postJson("/api/subscriptions/payments/{$paymentId}/refresh")
        ->assertOk()
        ->assertJsonPath('payment.status', SubscriptionPayment::STATUS_ACTIVATED);

    expect($subscription->refresh()->plan_version_id)->toBe($premium->id);
});

it('routes the historic GeniusPay webhook URL to subscription payments too', function (): void {
    registerAndLogin('subscription-unified-hook@example.com');
    $account = Account::query()->whereHas('identifiers', fn ($q) => $q->where('value', 'subscription-unified-hook@example.com'))->firstOrFail();
    test()->getJson('/api/subscriptions/current')->assertOk();
    $premium = walletV2PublishPaidPlan('GOLD');
    $subscription = UserSubscription::query()->where('account_id', $account->id)->where('status', 'active')->firstOrFail();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments' => Http::response([
            'reference' => 'SUB_UNIFIED_HOOK',
            'checkout_url' => 'https://geniuspay.ci/checkout/SUB_UNIFIED_HOOK',
            'status' => 'pending',
            'environment' => 'sandbox',
        ], 201),
    ]);
    test()->postJson("/api/subscriptions/{$subscription->id}/upgrade", ['plan_version_id' => $premium->id])->assertOk();

    Http::fake([
        'geniuspay.ci/api/v1/merchant/payments/SUB_UNIFIED_HOOK' => Http::response([
            'reference' => 'SUB_UNIFIED_HOOK',
            'amount' => 5000,
            'currency' => 'XOF',
            'status' => 'completed',
            'environment' => 'sandbox',
        ]),
    ]);

    ['payload' => $payload, 'headers' => $headers] = geniusPaySignedWebhook([
        'reference' => 'SUB_UNIFIED_HOOK',
        'amount' => 5000,
        'currency' => 'XOF',
        'status' => 'completed',
    ]);

    $server = ['CONTENT_TYPE' => 'application/json'];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    test()->call('POST', '/api/webhooks/geniuspay', server: $server, content: $payload)->assertOk();

    expect($subscription->refresh()->plan_version_id)->toBe($premium->id);
});
''',
)

write(
    "docs/chantiers/WALLET-V2-HOTFIX-ABONNEMENT.md",
    r'''# Wallet V2 + Hotfix abonnement GeniusPay

## Objet

Ce chantier précède Carte Wasplex et Live. Il corrige le retour GeniusPay des abonnements et transforme le Wallet utilisateur d'une projection essentiellement passive en un vrai point d'entrée self-service pour les dépôts et transferts.

## Bugs corrigés

- un paiement d'abonnement héritait du `success_url` du Studio annonceur ;
- le webhook GeniusPay historiquement configuré ne distribuait pas les événements vers les abonnements ;
- l'écran abonnement ouvrait le checkout dans un nouvel onglet puis rechargeait trop tôt ;
- plusieurs boutons Wallet étaient volontairement branchés sur `comingSoon` ;
- les statistiques « aujourd'hui / ce mois » dépendaient seulement de la page d'historique chargée côté mobile.

## Invariants

- le retour navigateur ne confirme jamais un paiement ;
- abonnement et dépôt sont revérifiés directement auprès de GeniusPay ;
- un dépôt confirmé produit une transaction Grand Livre avant d'apparaître au solde ;
- un transfert est un débit/crédit atomique et idempotent ;
- aucun transfert ne peut rendre le Wallet négatif ;
- aucun transfert vers soi-même ;
- le webhook `/api/webhooks/geniuspay` route l'événement vers le module qui possède réellement la référence ;
- le retrait reste désactivé tant qu'aucun endpoint de payout réel et vérifiable n'est documenté.

## UX

Le Wallet présente maintenant :

- solde disponible ;
- équivalent FCFA ;
- dépôt GeniusPay ;
- transfert entre membres ;
- statut clair du retrait ;
- statistiques calculées côté serveur ;
- historique Wallet unifié ;
- accès direct à l'abonnement.
''',
)

print("Wallet V2 + hotfix abonnement appliqués.")
