<?php

namespace App\Modules\Wallet\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Identity\Infrastructure\Models\UserSpace;
use App\Modules\Wallet\Infrastructure\Models\Wallet;
use App\Modules\Wallet\Infrastructure\Models\WalletAuditEvent;

final class WalletAuditor
{
    /** @param array<string, mixed> $context */
    public function record(
        string $action,
        string $outcome,
        ?Wallet $wallet = null,
        ?Account $actor = null,
        ?UserSpace $space = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $traceId = null,
        array $context = [],
    ): WalletAuditEvent {
        return WalletAuditEvent::query()->create([
            'wallet_id' => $wallet?->id,
            'actor_account_id' => $actor?->id,
            'actor_space_id' => $space?->id,
            'action' => $action,
            'outcome' => $outcome,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'trace_id' => $traceId,
            'context' => $context === [] ? null : $context,
            'occurred_at' => now(),
        ]);
    }
}
