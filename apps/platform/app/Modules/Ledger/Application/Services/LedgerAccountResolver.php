<?php

declare(strict_types=1);

namespace App\Modules\Ledger\Application\Services;

use App\Modules\Ledger\Domain\ValueObjects\LedgerAccountReference;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccount;
use App\Modules\Ledger\Infrastructure\Models\LedgerAccountType;
use RuntimeException;

final class LedgerAccountResolver
{
    public function resolve(LedgerAccountReference $reference): LedgerAccount
    {
        $accountType = LedgerAccountType::query()->where('code', $reference->accountTypeCode)->first();

        if ($accountType === null) {
            throw new RuntimeException("Unknown ledger account type code: {$reference->accountTypeCode}. Run ledger:seed-catalog.");
        }

        return LedgerAccount::query()->firstOrCreate(
            [
                'code' => $reference->code,
                'owner_type' => $reference->ownerType,
                'owner_id' => $reference->ownerId,
            ],
            [
                'account_type_id' => $accountType->id,
                'currency' => $reference->currency,
            ],
        );
    }
}
