<?php

declare(strict_types=1);

namespace App\Modules\Card\Application\Services;

use App\Modules\Card\Infrastructure\Models\Card;
use App\Modules\Card\Infrastructure\Models\CardOfferVersion;
use App\Modules\Identity\Infrastructure\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CardIssuer
{
    public function __construct(private readonly CardAudit $audit) {}

    public function issueBase(Account $account): Card
    {
        return DB::transaction(function () use ($account): Card {
            Account::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

            $existing = Card::query()
                ->with('offerVersion.offer')
                ->where('account_id', $account->id)
                ->where('status', '!=', Card::STATUS_CLOSED)
                ->latest('issued_at')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $version = CardOfferVersion::query()
                ->with('offer')
                ->where('status', 'published')
                ->whereHas('offer', fn ($query) => $query->where('code', 'WASPLEX_BASE')->where('status', 'active'))
                ->orderByDesc('version')
                ->firstOrFail();

            $reference = strtoupper(substr((string) Str::ulid(), -10));
            $card = Card::query()->create([
                'account_id' => $account->id,
                'offer_version_id' => $version->id,
                'public_identifier' => 'WPLX-'.strtoupper($account->country_code).'-'.$reference,
                'status' => Card::STATUS_ACTIVE,
                'issued_at' => now(),
                'activated_at' => now(),
                'expires_at' => $version->duration_days === null ? null : now()->addDays($version->duration_days),
            ]);

            $this->audit->record($card, 'CardIssued', ['offer_code' => $version->offer->code]);

            return $card->load('offerVersion.offer');
        });
    }
}
