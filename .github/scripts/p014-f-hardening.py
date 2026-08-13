from pathlib import Path

ROOT = Path('apps/platform')

def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f'Pattern not found in {path}: {old[:120]!r}')
    path.write_text(text.replace(old, new, 1))

# Service hardening.
p = ROOT / 'app/Modules/Funds/Application/Services/FundPilotageService.php'
text = p.read_text()
text = text.replace(
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundArrear;\n',
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundArrear;\nuse App\\Modules\\Funds\\Infrastructure\\Models\\FundAuditEvent;\n',
    1,
)
text = text.replace(
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundWishQueueEntry;\n',
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundWishQueueEntry;\nuse App\\Modules\\Subscriptions\\Infrastructure\\Models\\SubscriptionEntitlement;\n',
    1,
)
text = text.replace(
    '$seniorityDays = $membership?->started_at?->diffInDays(now()) ?? 0;\n        $seniorityPoints = min(20, intdiv(max(0, $seniorityDays), 30) * 2);',
    '$seniorityDays = (int) ($membership?->started_at?->diffInDays(now()) ?? 0);\n        $seniorityPoints = min(20, intdiv(max(0, $seniorityDays), 30) * 2);',
    1,
)
old = '''            return FundWishQueueEntry::query()->updateOrCreate(
                ['fund_wish_id' => $wish->id],
                [
                    'fund_program_id' => $wish->membership->fund_program_id,
                    'program_version_id' => $wish->membership->program_version_id,
                    'lane' => $lane,
                    'priority_score' => $priorityScore,
                    'status' => FundWishQueueEntry::STATUS_QUEUED,
                    'blocked_reason' => null,
                    'queued_at' => now(),
                    'released_at' => null,
                    'queued_by_account_id' => $actorAccountId,
                    'released_by_account_id' => null,
                ],
            );
'''
new = '''            $entry = FundWishQueueEntry::query()->updateOrCreate(
                ['fund_wish_id' => $wish->id],
                [
                    'fund_program_id' => $wish->membership->fund_program_id,
                    'program_version_id' => $wish->membership->program_version_id,
                    'lane' => $lane,
                    'priority_score' => $priorityScore,
                    'status' => FundWishQueueEntry::STATUS_QUEUED,
                    'blocked_reason' => null,
                    'queued_at' => now(),
                    'released_at' => null,
                    'queued_by_account_id' => $actorAccountId,
                    'released_by_account_id' => null,
                ],
            );
            FundAuditEvent::record($actorAccountId, 'FundWishQueued', 'fund_wish_queue_entry', $entry->id, [
                'fund_wish_id' => $wish->id,
                'lane' => $lane,
                'priority_score' => $priorityScore,
            ]);

            return $entry;
'''
if old not in text: raise SystemExit('queue return pattern missing')
text = text.replace(old, new, 1)
start = text.index('    public function releaseNext(')
end = text.index('    public function authorizeReserve(', start)
release = '''    public function releaseNext(string $programId, string $actorAccountId): FundCollectionSnapshot
    {
        $entryId = null;

        try {
            return DB::transaction(function () use ($programId, $actorAccountId, &$entryId): FundCollectionSnapshot {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['funds:queue:'.$programId]);

                $entry = FundWishQueueEntry::query()
                    ->with('wish')
                    ->where('fund_program_id', $programId)
                    ->where('status', FundWishQueueEntry::STATUS_QUEUED)
                    ->orderByRaw("CASE WHEN lane = 'emergency' THEN 0 ELSE 1 END")
                    ->orderByDesc('priority_score')
                    ->orderBy('queued_at')
                    ->lockForUpdate()
                    ->first();

                if ($entry === null) {
                    throw new RuntimeException('Aucun vœu n’attend dans cette file.');
                }
                $entryId = (string) $entry->id;
                $snapshot = $this->collections->createSnapshot($entry->wish, $actorAccountId);

                $entry->update([
                    'status' => FundWishQueueEntry::STATUS_RELEASED,
                    'released_at' => now(),
                    'released_by_account_id' => $actorAccountId,
                    'blocked_reason' => null,
                ]);
                FundAuditEvent::record($actorAccountId, 'FundWishQueueReleased', 'fund_wish_queue_entry', $entry->id, [
                    'snapshot_id' => $snapshot->id,
                ]);

                return $snapshot;
            });
        } catch (RuntimeException $exception) {
            if ($entryId !== null) {
                FundWishQueueEntry::query()
                    ->whereKey($entryId)
                    ->where('status', FundWishQueueEntry::STATUS_QUEUED)
                    ->update([
                        'status' => FundWishQueueEntry::STATUS_BLOCKED,
                        'blocked_reason' => $exception->getMessage(),
                    ]);
                FundAuditEvent::record($actorAccountId, 'FundWishQueueBlocked', 'fund_wish_queue_entry', $entryId, [
                    'reason' => $exception->getMessage(),
                ]);
            }
            throw $exception;
        }
    }

'''
text = text[:start] + release + text[end:]
old = '''            return FundReserveAllocation::query()->create([
                'fund_program_id' => $wish->membership->fund_program_id,
                'fund_wish_id' => $wish->id,
                'amount_minor' => $amountMinor,
                'consumed_minor' => 0,
                'status' => FundReserveAllocation::STATUS_AUTHORIZED,
                'reason' => $reason,
                'justification' => $justification,
                'authorized_by_account_id' => $actorAccountId,
                'authorized_at' => now(),
            ]);
'''
new = '''            $allocation = FundReserveAllocation::query()->create([
                'fund_program_id' => $wish->membership->fund_program_id,
                'fund_wish_id' => $wish->id,
                'amount_minor' => $amountMinor,
                'consumed_minor' => 0,
                'status' => FundReserveAllocation::STATUS_AUTHORIZED,
                'reason' => $reason,
                'justification' => $justification,
                'authorized_by_account_id' => $actorAccountId,
                'authorized_at' => now(),
            ]);
            FundAuditEvent::record($actorAccountId, 'FundReserveAllocated', 'fund_reserve_allocation', $allocation->id, [
                'fund_wish_id' => $wish->id,
                'amount_minor' => $amountMinor,
                'reason' => $reason,
            ]);

            return $allocation;
'''
if old not in text: raise SystemExit('reserve return pattern missing')
text = text.replace(old, new, 1)
text = text.replace(
    '    public function releaseReserveAllocation(FundReserveAllocation $allocation): FundReserveAllocation\n',
    '    public function releaseReserveAllocation(FundReserveAllocation $allocation, string $actorAccountId): FundReserveAllocation\n',
    1,
)
old = '''        $allocation->update([
            'status' => FundReserveAllocation::STATUS_RELEASED,
            'released_at' => now(),
        ]);

        return $allocation->refresh();
'''
new = '''        $allocation->update([
            'status' => FundReserveAllocation::STATUS_RELEASED,
            'released_at' => now(),
        ]);
        FundAuditEvent::record($actorAccountId, 'FundReserveAllocationReleased', 'fund_reserve_allocation', $allocation->id);

        return $allocation->refresh();
'''
if old not in text: raise SystemExit('release reserve pattern missing')
text = text.replace(old, new, 1)
text = text.replace(
    '    public function detectRehabilitationCases(): int\n',
    '    public function detectRehabilitationCases(?string $actorAccountId = null): int\n',
    1,
)
old = '''                FundRehabilitationCase::query()->create([
                    'fund_membership_id' => $membership->id,
                    'account_id' => $membership->account_id,
                    'status' => FundRehabilitationCase::STATUS_REQUIRED,
                    'trigger_code' => 'persistent_overdue_arrears',
                    'incident_count' => $overdue,
                    'required_actions' => [
                        'settle_open_arrears',
                        'keep_eligible_paid_subscription_active',
                        'keep_valid_fund_mandate',
                        'request_reactivation_review',
                    ],
                    'opened_at' => now(),
                ]);
'''
new = '''                $case = FundRehabilitationCase::query()->create([
                    'fund_membership_id' => $membership->id,
                    'account_id' => $membership->account_id,
                    'status' => FundRehabilitationCase::STATUS_REQUIRED,
                    'trigger_code' => 'persistent_overdue_arrears',
                    'incident_count' => $overdue,
                    'required_actions' => [
                        'settle_open_arrears',
                        'keep_eligible_paid_subscription_active',
                        'keep_valid_fund_mandate',
                        'request_reactivation_review',
                    ],
                    'opened_at' => now(),
                ]);
                FundAuditEvent::record($actorAccountId, 'FundRehabilitationRequired', 'fund_rehabilitation_case', $case->id, [
                    'incident_count' => $overdue,
                ]);
'''
if old not in text: raise SystemExit('rehab create pattern missing')
text = text.replace(old, new, 1)
old = """        $case->update(['status' => FundRehabilitationCase::STATUS_IN_PROGRESS]);

        return $case->refresh();
"""
new = """        $case->update(['status' => FundRehabilitationCase::STATUS_IN_PROGRESS]);
        FundAuditEvent::record($accountId, 'FundRehabilitationStarted', 'fund_rehabilitation_case', $case->id);

        return $case->refresh();
"""
if old not in text: raise SystemExit('rehab start pattern missing')
text = text.replace(old, new, 1)
text = text.replace(
    "with(['membership.subscription', 'membership.mandate', 'membership.program'])",
    "with(['membership.subscription.entitlements', 'membership.mandate', 'membership.program'])",
    1,
)
old = '''            if ($membership->subscription === null || ! $membership->subscription->isActive()) {
                throw new RuntimeException('Un abonnement payant éligible actif est requis.');
            }
'''
new = '''            $eligibleSubscription = $membership->subscription !== null
                && $membership->subscription->isActive()
                && $membership->subscription->entitlements->contains(
                    fn ($entitlement): bool => $entitlement->key === SubscriptionEntitlement::KEY_FONDS_ELIGIBLE && (bool) $entitlement->enabled,
                );
            if (! $eligibleSubscription) {
                throw new RuntimeException('Un abonnement payant éligible Fonds actif est requis.');
            }
'''
if old not in text: raise SystemExit('subscription eligibility pattern missing')
text = text.replace(old, new, 1)
old = '''            $this->refreshReciprocity((string) $case->account_id);

            return $case->refresh();
'''
new = '''            $this->refreshReciprocity((string) $case->account_id);
            FundAuditEvent::record($actorAccountId, 'FundRehabilitationCompleted', 'fund_rehabilitation_case', $case->id, [
                'note' => $note,
            ]);

            return $case->refresh();
'''
if old not in text: raise SystemExit('rehab complete pattern missing')
text = text.replace(old, new, 1)
p.write_text(text)

# Admin controller: safe account projection and new actor parameters.
p = ROOT / 'app/Modules/Funds/Http/Controllers/Admin/AdminFundPilotageController.php'
text = p.read_text()
text = text.replace(
    "->with(['membership.program:id,name,code', 'account:id,display_name,phone_e164'])",
    "->with(['membership.program:id,name,code', 'account.identifiers'])",
    1,
)
old = '''            ->limit(100)
            ->get();

        $deficits = FundCollectionSnapshot::query()
'''
new = '''            ->limit(100)
            ->get()
            ->map(fn (FundRehabilitationCase $case): array => [
                'id' => $case->id,
                'status' => $case->status,
                'incident_count' => (int) $case->incident_count,
                'account_label' => $case->account?->primaryIdentifierLabel() ?? 'Membre',
                'program_name' => $case->membership?->program?->name,
                'opened_at' => $case->opened_at,
            ]);

        $deficits = FundCollectionSnapshot::query()
'''
if old not in text: raise SystemExit('admin rehab mapping pattern missing')
text = text.replace(old, new, 1)
text = text.replace(
    "return response()->json(['data' => $this->pilotage->releaseReserveAllocation($allocation)]);",
    "return response()->json(['data' => $this->pilotage->releaseReserveAllocation($allocation, (string) request()->user()->id)]);",
    1,
)
text = text.replace(
    "return response()->json(['data' => ['created' => $this->pilotage->detectRehabilitationCases()]]);",
    "return response()->json(['data' => ['created' => $this->pilotage->detectRehabilitationCases((string) request()->user()->id)]]);",
    1,
)
p.write_text(text)

# Admin UI uses safe account label projection.
p = ROOT / 'resources/js/Components/AdminFundPilotage.vue'
text = p.read_text()
old = '''type Rehabilitation = {
    id: string;
    status: string;
    incident_count: number;
    account?: { display_name: string | null; phone_e164: string | null };
    membership?: { program?: { name: string } };
};
'''
new = '''type Rehabilitation = {
    id: string;
    status: string;
    incident_count: number;
    account_label: string;
    program_name: string | null;
};
'''
if old not in text: raise SystemExit('ui rehab type pattern missing')
text = text.replace(old, new, 1)
text = text.replace("{{ item.account?.display_name || item.account?.phone_e164 || 'Membre' }}", "{{ item.account_label }}", 1)
text = text.replace("{{ item.membership?.program?.name }} · {{ item.incident_count }} incident(s) · {{ item.status }}", "{{ item.program_name }} · {{ item.incident_count }} incident(s) · {{ item.status }}", 1)
p.write_text(text)
