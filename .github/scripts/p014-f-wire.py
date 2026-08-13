from pathlib import Path

ROOT = Path('apps/platform')

def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f'Pattern not found in {path}: {old[:100]!r}')
    path.write_text(text.replace(old, new, 1))

# Collection: apply authorized reserve before immutable snapshot.
p = ROOT / 'app/Modules/Funds/Application/Services/FundCollectionService.php'
replace_once(
    p,
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundMembership;\n',
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundMembership;\nuse App\\Modules\\Funds\\Infrastructure\\Models\\FundReserveAllocation;\n',
)
replace_once(
    p,
    "            $personalApplied = min($personalContribution, $validatedCost);\n            $collectiveAmount = max(0, $validatedCost - $personalApplied);\n            if ($collectiveAmount <= 0) {\n                throw new RuntimeException('Aucun montant collectif ne reste à financer.');\n            }\n\n            $programId = (string) $wish->membership->fund_program_id;\n",
    "            $personalApplied = min($personalContribution, $validatedCost);\n            $reserveAuthorized = (int) FundReserveAllocation::query()\n                ->where('fund_wish_id', $wish->id)\n                ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])\n                ->get()\n                ->sum(fn (FundReserveAllocation $allocation): int => max(0, (int) $allocation->amount_minor - (int) $allocation->consumed_minor));\n            $reserveApplied = min($reserveAuthorized, max(0, $validatedCost - $personalApplied));\n            $collectiveAmount = max(0, $validatedCost - $personalApplied - $reserveApplied);\n\n            $programId = (string) $wish->membership->fund_program_id;\n",
)
replace_once(
    p,
    "            $version = $wish->membership->version;\n\n            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [\n",
    "            $version = $wish->membership->version;\n\n            if ($collectiveAmount <= 0) {\n                return $this->createReserveFundedSnapshot(\n                    $wish,\n                    $programId,\n                    $country,\n                    $currency,\n                    $validatedCost,\n                    $personalApplied,\n                    $reserveApplied,\n                    $actorAccountId,\n                );\n            }\n\n            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [\n",
)
replace_once(
    p,
    "                'collective_amount_minor' => $collectiveAmount,\n                'participants' => $participants->map",
    "                'collective_amount_minor' => $collectiveAmount,\n                'reserve_contribution_minor' => $reserveApplied,\n                'participants' => $participants->map",
)
replace_once(
    p,
    "                'partner_contribution_minor' => 0,\n                'reserve_contribution_minor' => 0,\n                'collective_amount_minor' => $collectiveAmount,\n",
    "                'partner_contribution_minor' => 0,\n                'reserve_contribution_minor' => $reserveApplied,\n                'collective_amount_minor' => $collectiveAmount,\n",
)
helper = r'''
    private function createReserveFundedSnapshot(
        FundWish $wish,
        string $programId,
        string $country,
        string $currency,
        int $validatedCost,
        int $personalApplied,
        int $reserveApplied,
        string $actorAccountId,
    ): FundCollectionSnapshot {
        $payload = [
            'fund_wish_id' => (string) $wish->id,
            'fund_program_id' => $programId,
            'program_version_id' => (string) $wish->membership->program_version_id,
            'country_code' => $country,
            'currency' => $currency,
            'validated_cost_minor' => $validatedCost,
            'personal_contribution_minor' => $personalApplied,
            'reserve_contribution_minor' => $reserveApplied,
            'collective_amount_minor' => 0,
            'participants' => [],
            'rule_version' => self::RULE_VERSION,
        ];
        $now = now();

        return FundCollectionSnapshot::query()->create([
            'fund_wish_id' => $wish->id,
            'fund_program_id' => $programId,
            'program_version_id' => $wish->membership->program_version_id,
            'country_code' => $country,
            'currency' => $currency,
            'validated_cost_minor' => $validatedCost,
            'personal_contribution_minor' => $personalApplied,
            'partner_contribution_minor' => 0,
            'reserve_contribution_minor' => $reserveApplied,
            'collective_amount_minor' => 0,
            'default_wasplex_fee_minor' => (int) ($wish->membership->version->wasplex_fee_minor ?? 0),
            'participant_count' => 0,
            'solidarity_base_minor' => 0,
            'solidarity_remainder_minor' => 0,
            'rule_version' => self::RULE_VERSION,
            'snapshot_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'rules_snapshot' => [
                'wp_to_xof' => 1,
                'reserve_funded_without_collective_debit' => true,
                'beneficiary_excluded' => true,
                'fee_separate_from_solidarity' => true,
            ],
            'status' => FundCollectionSnapshot::STATUS_FUNDED,
            'notice_at' => $now,
            'scheduled_at' => $now,
            'started_at' => $now,
            'completed_at' => $now,
            'created_by_account_id' => $actorAccountId,
        ]);
    }

'''
replace_once(p, '    private function eligibleCandidates(FundWish $wish, int $collectiveAmount): Collection\n', helper + '    private function eligibleCandidates(FundWish $wish, int $collectiveAmount): Collection\n')

# Realization: carry pre-snapshot reserve authorization into the order and consume allocations.
p = ROOT / 'app/Modules/Funds/Application/Services/FundRealizationService.php'
replace_once(
    p,
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundOrder;\n',
    'use App\\Modules\\Funds\\Infrastructure\\Models\\FundOrder;\nuse App\\Modules\\Funds\\Infrastructure\\Models\\FundReserveAllocation;\n',
)
replace_once(
    p,
    "            return FundOrder::query()->firstOrCreate(['fund_wish_id' => $wish->id], [\n                'quote_id' => $wish->selected_quote_id,\n                'provider_organization_id' => $wish->provider_organization_id,\n                'status' => FundOrder::STATUS_ISSUED,\n                'total_minor' => (int) $wish->validated_amount_minor,\n                'currency' => $wish->currency,\n                'ordered_by_account_id' => $actorAccountId,\n                'ordered_at' => now(),\n            ]);\n",
    "            $authorizedReserve = (int) FundReserveAllocation::query()\n                ->where('fund_wish_id', $wish->id)\n                ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])\n                ->get()\n                ->sum(fn (FundReserveAllocation $allocation): int => max(0, (int) $allocation->amount_minor - (int) $allocation->consumed_minor));\n\n            $order = FundOrder::query()->firstOrCreate(['fund_wish_id' => $wish->id], [\n                'quote_id' => $wish->selected_quote_id,\n                'provider_organization_id' => $wish->provider_organization_id,\n                'status' => FundOrder::STATUS_ISSUED,\n                'total_minor' => (int) $wish->validated_amount_minor,\n                'currency' => $wish->currency,\n                'authorized_reserve_minor' => $authorizedReserve,\n                'ordered_by_account_id' => $actorAccountId,\n                'ordered_at' => now(),\n            ]);\n            FundReserveAllocation::query()\n                ->where('fund_wish_id', $wish->id)\n                ->whereNull('fund_order_id')\n                ->update(['fund_order_id' => $order->id]);\n\n            return $order;\n",
)
replace_once(
    p,
    "                DB::table('fund_reserve_entries')->insert([\n                    'id' => (string) Str::ulid(), 'fund_order_id' => $order->id, 'direction' => 'debit', 'amount_minor' => $reserve,\n                    'reason' => 'Complément exceptionnel de réalisation', 'ledger_transaction_id' => $tx->id, 'created_by_account_id' => $actorAccountId, 'created_at' => now(),\n                ]);\n",
    "                DB::table('fund_reserve_entries')->insert([\n                    'id' => (string) Str::ulid(), 'fund_order_id' => $order->id, 'direction' => 'debit', 'amount_minor' => $reserve,\n                    'reason' => 'Complément exceptionnel de réalisation', 'ledger_transaction_id' => $tx->id, 'created_by_account_id' => $actorAccountId, 'created_at' => now(),\n                ]);\n                $this->consumeReserveAllocations($order, $reserve);\n",
)
consume_helper = r'''
    private function consumeReserveAllocations(FundOrder $order, int $amountMinor): void
    {
        $remaining = $amountMinor;
        $allocations = FundReserveAllocation::query()
            ->where('fund_order_id', $order->id)
            ->whereIn('status', [FundReserveAllocation::STATUS_AUTHORIZED, FundReserveAllocation::STATUS_PARTIALLY_CONSUMED])
            ->orderBy('authorized_at')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            if ($remaining <= 0) {
                break;
            }
            $available = max(0, (int) $allocation->amount_minor - (int) $allocation->consumed_minor);
            $consume = min($available, $remaining);
            if ($consume <= 0) {
                continue;
            }
            $consumed = (int) $allocation->consumed_minor + $consume;
            $allocation->update([
                'consumed_minor' => $consumed,
                'status' => $consumed >= (int) $allocation->amount_minor
                    ? FundReserveAllocation::STATUS_CONSUMED
                    : FundReserveAllocation::STATUS_PARTIALLY_CONSUMED,
                'consumed_at' => $consumed >= (int) $allocation->amount_minor ? now() : null,
            ]);
            $remaining -= $consume;
        }

        if ($remaining > 0) {
            throw new RuntimeException('La consommation de réserve dépasse les allocations autorisées.');
        }
    }

'''
replace_once(p, '    public function reserveBalanceMinor(): int\n', consume_helper + '    public function reserveBalanceMinor(): int\n')

# Wire user/admin UI cards.
p = ROOT / 'resources/js/Components/FundsPanel.vue'
replace_once(p, "import FundRealizationStatusCard from '@/Components/FundRealizationStatusCard.vue';\n", "import FundRealizationStatusCard from '@/Components/FundRealizationStatusCard.vue';\nimport FundPilotageCard from '@/Components/FundPilotageCard.vue';\n")
replace_once(p, '            <FundRealizationStatusCard v-if="activeMembership" />\n', '            <FundRealizationStatusCard v-if="activeMembership" />\n\n            <FundPilotageCard v-if="activeMembership" />\n')

p = ROOT / 'resources/js/Components/AdminFundsPanel.vue'
replace_once(p, "import AdminFundRealizations from '@/Components/AdminFundRealizations.vue';\n", "import AdminFundRealizations from '@/Components/AdminFundRealizations.vue';\nimport AdminFundPilotage from '@/Components/AdminFundPilotage.vue';\n")
replace_once(p, "    max_simultaneous_collections: number;\n", "    max_simultaneous_collections: number;\n    emergency_queue_share_percent: number;\n    reserve_min_balance_minor: number;\n    reciprocity_min_score: number;\n    rehabilitation_incident_threshold: number;\n")
replace_once(p, "    max_simultaneous_collections: 1,\n});\n", "    max_simultaneous_collections: 1,\n    emergency_queue_share_percent: 20,\n    reserve_min_balance_minor: 0,\n    reciprocity_min_score: 0,\n    rehabilitation_incident_threshold: 3,\n});\n")
# resetProgram contains the same tail a second time.
replace_once(p, "        max_simultaneous_collections: 1,\n    };\n", "        max_simultaneous_collections: 1,\n        emergency_queue_share_percent: 20,\n        reserve_min_balance_minor: 0,\n        reciprocity_min_score: 0,\n        rehabilitation_incident_threshold: 3,\n    };\n")
replace_once(p, "            max_simultaneous_collections: programForm.value.max_simultaneous_collections,\n", "            max_simultaneous_collections: programForm.value.max_simultaneous_collections,\n            emergency_queue_share_percent: programForm.value.emergency_queue_share_percent,\n            reserve_min_balance_minor: programForm.value.reserve_min_balance_minor,\n            reciprocity_min_score: programForm.value.reciprocity_min_score,\n            rehabilitation_incident_threshold: programForm.value.rehabilitation_incident_threshold,\n")
replace_once(p, '            <AdminFundRealizations />\n', '            <AdminFundRealizations />\n\n            <AdminFundPilotage />\n')
needle = '''                        <label class="text-wpx-muted-dark text-xs"
                            >Collectes simultanées<input
                                v-model.number="programForm.max_simultaneous_collections"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
'''
extra = needle + '''                        <label class="text-wpx-muted-dark text-xs"
                            >Part urgence réservée (%)<input
                                v-model.number="programForm.emergency_queue_share_percent"
                                type="number"
                                min="0"
                                max="100"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Plancher de réserve<input
                                v-model.number="programForm.reserve_min_balance_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Réciprocité minimale<input
                                v-model.number="programForm.reciprocity_min_score"
                                type="number"
                                min="0"
                                max="100"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Incidents avant réhabilitation<input
                                v-model.number="programForm.rehabilitation_incident_threshold"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
'''
replace_once(p, needle, extra)

# Pilotage rules: reserve floor and ordinary reciprocity threshold.
p = ROOT / 'app/Modules/Funds/Application/Services/FundPilotageService.php'
text = p.read_text()
text = text.replace("$wish = FundWish::query()->with(['membership.version'])->whereKey($wish->id)->lockForUpdate()->firstOrFail();", "$wish = FundWish::query()->with(['membership.version'])->whereKey($wish->id)->lockForUpdate()->firstOrFail();", 1)
old = """            $reciprocity = $this->refreshReciprocity((string) $wish->account_id);
            $priorityScore = $lane === FundWishQueueEntry::LANE_EMERGENCY
                ? 1_000_000
                : (int) $reciprocity->score;
"""
new = """            $reciprocity = $this->refreshReciprocity((string) $wish->account_id);
            $minimumScore = (int) ($wish->membership->version->reciprocity_min_score ?? 0);
            if ($lane === FundWishQueueEntry::LANE_ORDINARY && (int) $reciprocity->score < $minimumScore) {
                throw new RuntimeException('L’indice de réciprocité minimum du programme n’est pas encore atteint.');
            }
            $priorityScore = $lane === FundWishQueueEntry::LANE_EMERGENCY
                ? 1_000_000
                : (int) $reciprocity->score;
"""
if old not in text:
    raise SystemExit('Reciprocity queue pattern not found')
text = text.replace(old, new, 1)
old = """            $wish = FundWish::query()->with('membership')->whereKey($wish->id)->lockForUpdate()->firstOrFail();
"""
new = """            $wish = FundWish::query()->with('membership.version')->whereKey($wish->id)->lockForUpdate()->firstOrFail();
"""
if old not in text:
    raise SystemExit('Reserve membership pattern not found')
text = text.replace(old, new, 1)
old = """            $available = $this->availableReserveMinor();
            if ($amountMinor > $available) {
                throw new RuntimeException('Réserve disponible insuffisante.');
            }
"""
new = """            $available = $this->availableReserveMinor();
            $minimumBalance = max(0, (int) ($wish->membership->version->reserve_min_balance_minor ?? 0));
            $allocatable = max(0, $available - $minimumBalance);
            if ($amountMinor > $allocatable) {
                throw new RuntimeException('Cette allocation ferait passer la réserve sous le plancher du programme.');
            }
"""
if old not in text:
    raise SystemExit('Reserve floor pattern not found')
text = text.replace(old, new, 1)
p.write_text(text)
