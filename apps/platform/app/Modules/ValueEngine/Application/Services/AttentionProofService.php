<?php

namespace App\Modules\ValueEngine\Application\Services;

use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\ValueEngine\Application\Data\AttentionHeartbeatData;
use App\Modules\ValueEngine\Domain\Enums\AttentionSessionStatus;
use App\Modules\ValueEngine\Domain\Enums\ValueAttemptStatus;
use App\Modules\ValueEngine\Domain\Exceptions\ValueEngineException;
use App\Modules\ValueEngine\Infrastructure\Models\AttentionHeartbeat;
use App\Modules\ValueEngine\Infrastructure\Models\AttentionSession;
use App\Modules\ValueEngine\Infrastructure\Models\ValueAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

final readonly class AttentionProofService
{
    public function __construct(
        private AdvertisingValueAttemptService $attempts,
        private AttentionTokenSigner $tokens,
        private ValueOutbox $outbox,
    ) {}

    public function heartbeat(
        ValueAttempt $attempt,
        Account $account,
        string $attentionToken,
        AttentionHeartbeatData $heartbeat,
    ): ValueAttempt {
        return DB::transaction(function () use ($attempt, $account, $attentionToken, $heartbeat): ValueAttempt {
            $locked = ValueAttempt::query()
                ->whereKey($attempt->id)
                ->with('rule')
                ->lockForUpdate()
                ->firstOrFail();
            $session = AttentionSession::query()
                ->where('value_attempt_id', $locked->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->attempts->guardOwnership($locked, $account);
            $this->tokens->assertValid($session, $attentionToken);
            if ($locked->status === ValueAttemptStatus::Completed) {
                return $locked;
            }
            if ($locked->status->isFinal()) {
                throw new ValueEngineException('Cette tentative de valeur est déjà clôturée.');
            }
            if ($locked->expires_at->isPast()) {
                return $this->attempts->releaseLocked(
                    $locked,
                    ValueAttemptStatus::Expired,
                    'ATTENTION_SESSION_EXPIRED',
                );
            }

            $clientAt = CarbonImmutable::instance($heartbeat->clientOccurredAt);
            $payload = [
                'session_id' => $session->id,
                'sequence' => $heartbeat->sequence,
                'active_duration_ms' => $heartbeat->activeDurationMs,
                'visible' => $heartbeat->visible,
                'client_occurred_at' => $clientAt->toIso8601String(),
            ];
            $payloadHash = hash('sha256', $this->encode($payload));
            $existing = AttentionHeartbeat::query()
                ->where('idempotency_key', $heartbeat->idempotencyKey)
                ->first();
            if ($existing instanceof AttentionHeartbeat) {
                if ($existing->payload_hash !== $payloadHash) {
                    throw new ValueEngineException('Cette clé d’idempotence appartient à un autre heartbeat.');
                }

                return $locked->refresh();
            }

            if ($heartbeat->sequence !== $session->last_sequence + 1) {
                throw new ValueEngineException('La séquence de heartbeat est incohérente.');
            }
            if ($heartbeat->activeDurationMs <= 0) {
                throw new ValueEngineException('La durée active du heartbeat doit être positive.');
            }
            if ($heartbeat->activeDurationMs > $locked->rule->heartbeat_interval_ms * 2) {
                throw new ValueEngineException('La durée annoncée par le heartbeat est impossible.');
            }

            $clockSkew = (int) config('value-engine.heartbeat_clock_skew_seconds');
            if ($clientAt->greaterThan(now()->addSeconds($clockSkew))) {
                throw new ValueEngineException('Le heartbeat est horodaté dans le futur.');
            }
            if (
                $session->last_heartbeat_at !== null
                && $clientAt->lessThan($session->last_heartbeat_at->subSeconds($clockSkew))
            ) {
                throw new ValueEngineException('Le heartbeat remonte anormalement dans le temps.');
            }

            AttentionHeartbeat::query()->create([
                'value_attention_session_id' => $session->id,
                'sequence' => $heartbeat->sequence,
                'idempotency_key' => $heartbeat->idempotencyKey,
                'visible' => $heartbeat->visible,
                'active_duration_ms' => $heartbeat->activeDurationMs,
                'client_occurred_at' => $clientAt,
                'received_at' => now(),
                'payload_hash' => $payloadHash,
                'metadata' => ['client_credit_authorized' => false],
            ]);

            $visibleDuration = $session->visible_duration_ms;
            if ($heartbeat->visible) {
                $visibleDuration += $heartbeat->activeDurationMs;
            }
            $visibleDuration = min($session->required_duration_ms, $visibleDuration);
            $completed = $visibleDuration >= $session->required_duration_ms;
            $session->forceFill([
                'status' => $completed ? AttentionSessionStatus::Completed : AttentionSessionStatus::Active,
                'visible_duration_ms' => $visibleDuration,
                'last_sequence' => $heartbeat->sequence,
                'started_at' => $session->started_at ?? now(),
                'last_heartbeat_at' => now(),
                'completed_at' => $completed ? now() : null,
            ])->save();
            $locked->forceFill([
                'status' => $completed ? ValueAttemptStatus::ProofPending : ValueAttemptStatus::AttentionActive,
                'proof_validated_at' => $completed ? now() : null,
            ])->save();

            if ($completed) {
                $this->outbox->record(
                    'value_attempt',
                    $locked->id,
                    'QUALIFIED_ATTENTION_VALIDATED',
                    'qualified-attention:'.$locked->id,
                    [
                        'value_attempt_id' => $locked->id,
                        'attention_session_id' => $session->id,
                        'validated_duration_ms' => $visibleDuration,
                        'required_duration_ms' => $session->required_duration_ms,
                        'client_credit_authorized' => false,
                    ],
                );
                DB::afterCommit(static fn () => event('value-engine.qualified-attention', [$locked->id]));
            }

            return $locked->refresh();
        }, 5);
    }

    /** @param array<string, mixed> $payload */
    private function encode(array $payload): string
    {
        ksort($payload);

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ValueEngineException('Le heartbeat ne peut pas être canonisé.', 0, $exception);
        }
    }
}
