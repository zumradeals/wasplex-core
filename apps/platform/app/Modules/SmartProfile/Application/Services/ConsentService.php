<?php

declare(strict_types=1);

namespace App\Modules\SmartProfile\Application\Services;

use App\Modules\SmartProfile\Application\Contracts\AdvertisingConsentContract;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentEvent;
use App\Modules\SmartProfile\Infrastructure\Models\ConsentPurpose;
use App\Modules\SmartProfile\Infrastructure\Models\UserConsent;
use Illuminate\Support\Carbon;

/**
 * docs/17-donnees-permissions-consentements-techniques-wasplex.md
 * §12-19 : décision explicite (accord/refus/retrait), preuve de version,
 * historique immuable, retrait immédiatement opposable. Seul point de
 * lecture pour Matching (docs/chantiers/P008-CHANTIER.md §6).
 */
final class ConsentService implements AdvertisingConsentContract
{
    public function __construct(private readonly ConsentPurposeService $purposes) {}

    /**
     * @return array<int, array{code: string, name: string, description: string, status: string, granted_at: ?string}>
     */
    public function listForAccount(string $accountId): array
    {
        $activePurposes = $this->purposes->listActive();

        $currentByPurpose = UserConsent::query()
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('consent_purpose_id');

        return $activePurposes->map(function (ConsentPurpose $purpose) use ($currentByPurpose) {
            $version = $purpose->versions->first();
            $current = $currentByPurpose->get($purpose->id);

            return [
                'code' => $purpose->code,
                'name' => $version->name,
                'description' => $version->description,
                'status' => $current?->status ?? 'not_decided',
                'granted_at' => $current?->granted_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    public function grant(string $accountId, string $purposeCode, string $channel = 'web'): UserConsent
    {
        $purpose = $this->activePurpose($purposeCode);
        $version = $purpose->publishedVersion();

        if ($version === null) {
            throw new ConsentPurposeNotPublishedException;
        }

        $consent = UserConsent::query()->updateOrCreate(
            ['account_id' => $accountId, 'consent_purpose_id' => $purpose->id],
            [
                'consent_purpose_version_id' => $version->id,
                'status' => UserConsent::STATUS_GRANTED,
                'channel' => $channel,
                'granted_at' => Carbon::now('UTC'),
                'withdrawn_at' => null,
            ],
        );

        $this->logEvent($consent, ConsentEvent::TYPE_GRANTED);

        return $consent;
    }

    public function withdraw(string $accountId, string $purposeCode): void
    {
        $purpose = $this->activePurpose($purposeCode);

        $consent = UserConsent::query()
            ->where('account_id', $accountId)
            ->where('consent_purpose_id', $purpose->id)
            ->where('status', UserConsent::STATUS_GRANTED)
            ->first();

        if ($consent === null) {
            return;
        }

        $consent->update(['status' => UserConsent::STATUS_WITHDRAWN, 'withdrawn_at' => Carbon::now('UTC')]);

        $this->logEvent($consent, ConsentEvent::TYPE_WITHDRAWN);
    }

    /**
     * @return array<int, array{purpose_code: string, event_type: string, occurred_at: string}>
     */
    public function history(string $accountId): array
    {
        return ConsentEvent::query()
            ->where('account_id', $accountId)
            ->with('purpose')
            ->orderByDesc('occurred_at')
            ->get()
            ->map(fn (ConsentEvent $event) => [
                'purpose_code' => $event->purpose?->code,
                'event_type' => $event->event_type,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    public function stateFor(string $accountId, string $purposeCode): string
    {
        $purpose = ConsentPurpose::query()->where('code', $purposeCode)->first();

        if ($purpose === null) {
            return self::STATE_NOT_DECIDED;
        }

        $consent = UserConsent::query()
            ->where('account_id', $accountId)
            ->where('consent_purpose_id', $purpose->id)
            ->first();

        if ($consent === null) {
            return self::STATE_NOT_DECIDED;
        }

        return match ($consent->status) {
            UserConsent::STATUS_GRANTED => self::STATE_ACTIVE,
            UserConsent::STATUS_SUPERSEDED => self::STATE_NOT_DECIDED,
            default => self::STATE_REFUSED,
        };
    }

    private function activePurpose(string $purposeCode): ConsentPurpose
    {
        $purpose = ConsentPurpose::query()
            ->where('code', $purposeCode)
            ->where('status', ConsentPurpose::STATUS_ACTIVE)
            ->first();

        if ($purpose === null) {
            throw new ConsentPurposeNotFoundException($purposeCode);
        }

        return $purpose;
    }

    private function logEvent(UserConsent $consent, string $eventType): void
    {
        ConsentEvent::query()->create([
            'user_consent_id' => $consent->id,
            'account_id' => $consent->account_id,
            'consent_purpose_id' => $consent->consent_purpose_id,
            'consent_purpose_version_id' => $consent->consent_purpose_version_id,
            'event_type' => $eventType,
            'occurred_at' => Carbon::now('UTC'),
        ]);
    }
}
