<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Advertising\Infrastructure\Models\AdvertisingMatch;
use App\Modules\Campaign\Infrastructure\Models\Campaign;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\ValueEngine\Application\Data\AttentionHeartbeatData;
use App\Modules\ValueEngine\Application\Data\StartedValueAttempt;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;

final readonly class AdvertisingValueEngine
{
    public function __construct(
        private AdvertisingValueAttemptService $attempts,
        private AttentionProofService $proofs,
        private AdvertisingSettlementService $settlements,
    ) {}

    public function start(
        Account $account,
        Campaign $campaign,
        AdvertisingMatch $match,
        string $deviceSessionId,
        string $idempotencyKey,
        ?string $countryCode = null,
    ): StartedValueAttempt {
        return $this->attempts->start(
            $account,
            $campaign,
            $match,
            $deviceSessionId,
            $idempotencyKey,
            $countryCode,
        );
    }

    public function heartbeat(
        ValueAttempt $attempt,
        Account $account,
        string $attentionToken,
        AttentionHeartbeatData $heartbeat,
    ): ValueAttempt {
        return $this->proofs->heartbeat($attempt, $account, $attentionToken, $heartbeat);
    }

    public function settle(
        ValueAttempt $attempt,
        Account $account,
        string $idempotencyKey,
        ?string $traceId = null,
    ): ValueAttempt {
        return $this->settlements->settle($attempt, $account, $idempotencyKey, $traceId);
    }

    public function release(
        ValueAttempt $attempt,
        Account $account,
        string $reason,
        ValueAttemptStatus $status = ValueAttemptStatus::Released,
    ): ValueAttempt {
        return $this->attempts->release($attempt, $account, $reason, $status);
    }

    public function expireDue(): int
    {
        return $this->attempts->expireDue();
    }
}
