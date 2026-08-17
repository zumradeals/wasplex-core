<?php

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
        private readonly UserWalletPinService $pin,
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
        ?string $pin,
    ): UserWalletTransfer {
        if ($senderAccountId === $recipientAccountId) {
            throw new AppException('WALLET_TRANSFER_SELF', 'Vous ne pouvez pas vous transférer des WP à vous-même.');
        }

        // Le PIN est vérifié avant toute ouverture de transaction DB : un
        // PIN absent ou incorrect ne doit produire aucune écriture Ledger
        // ni aucun mouvement de fonds (docs/CLAUDE.md §7, P020 §2.6).
        $this->pin->assertValid($senderAccountId, $pin);

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
