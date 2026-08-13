from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
PLATFORM = ROOT / 'apps/platform'


def read(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')


def write(rel: str, content: str) -> None:
    path = ROOT / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding='utf-8')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    if old not in text:
        raise RuntimeError(f'Marqueur introuvable: {label}')
    return text.replace(old, new, 1)


# ---------------------------------------------------------------------------
# Wallet Ledger history: categories + paginated module history.
# ---------------------------------------------------------------------------
path = 'apps/platform/app/Modules/Wallet/Application/Services/UserWalletQueryService.php'
text = read(path)
text = replace_once(
    text,
    'use Illuminate\\Support\\Facades\\Log;\n',
    'use Illuminate\\Support\\Facades\\Log;\nuse Illuminate\\Support\\Str;\n',
    'UserWalletQueryService import Str',
)
pattern = re.compile(r'    public function history\(string \$accountId, int \$perPage = 25\): LengthAwarePaginator\n    \{.*?\n    \}\n\}', re.S)
replacement = r'''    /**
     * Les accordéons Wallet sont des projections du Grand Livre. On ne crée
     * jamais un historique parallèle : source_module reste la source de
     * catégorisation et les écritures Ledger restent la source de vérité.
     *
     * @return array<int, array{key: string, label: string, description: string, icon: string, count: int}>
     */
    public function historyCategories(string $accountId): array
    {
        $account = $this->availableLedgerAccount($accountId);
        $counts = [];

        if ($account !== null) {
            $rows = LedgerEntry::query()
                ->join('ledger_transactions', 'ledger_transactions.id', '=', 'ledger_entries.transaction_id')
                ->where('ledger_entries.account_id', $account->id)
                ->selectRaw('ledger_transactions.source_module AS source_module, COUNT(*) AS total')
                ->groupBy('ledger_transactions.source_module')
                ->get();

            foreach ($rows as $row) {
                $source = $row->source_module === null ? 'other' : (string) $row->source_module;
                $counts[$source] = (int) $row->total;
            }
        }

        $definitions = $this->historyCategoryDefinitions();
        $categories = [];
        $knownModules = [];

        foreach ($definitions as $key => $definition) {
            $count = 0;
            foreach ($definition['modules'] as $module) {
                $knownModules[] = $module;
                $count += $counts[$module] ?? 0;
            }

            $categories[] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'count' => $count,
            ];
        }

        foreach ($counts as $module => $count) {
            if ($module === 'other' || in_array($module, $knownModules, true) || $count <= 0) {
                continue;
            }

            $categories[] = [
                'key' => 'module:'.$module,
                'label' => 'Historique '.Str::headline($module),
                'description' => 'Mouvements enregistrés par ce module dans le Grand Livre.',
                'icon' => '•',
                'count' => $count,
            ];
        }

        if (($counts['other'] ?? 0) > 0) {
            $categories[] = [
                'key' => 'other',
                'label' => 'Autres opérations',
                'description' => 'Écritures Wallet sans module métier identifié.',
                'icon' => '•',
                'count' => $counts['other'],
            ];
        }

        return $categories;
    }

    public function history(string $accountId, int $perPage = 10, ?string $category = null): LengthAwarePaginator
    {
        $account = $this->availableLedgerAccount($accountId);

        if ($account === null) {
            return LedgerEntry::query()->whereRaw('1 = 0')->paginate($perPage);
        }

        $query = LedgerEntry::query()
            ->where('account_id', $account->id)
            ->with('transaction');

        if ($category !== null && $category !== '') {
            $definitions = $this->historyCategoryDefinitions();

            if (isset($definitions[$category])) {
                $modules = $definitions[$category]['modules'];
                $query->whereHas('transaction', fn ($transaction) => $transaction->whereIn('source_module', $modules));
            } elseif (str_starts_with($category, 'module:')) {
                $module = substr($category, 7);
                $query->whereHas('transaction', fn ($transaction) => $transaction->where('source_module', $module));
            } elseif ($category === 'other') {
                $knownModules = collect($definitions)->flatMap(fn (array $definition): array => $definition['modules'])->values()->all();
                $query->whereHas('transaction', fn ($transaction) => $transaction
                    ->whereNull('source_module')
                    ->orWhereNotIn('source_module', $knownModules));
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    private function availableLedgerAccount(string $accountId): ?LedgerAccount
    {
        return LedgerAccount::query()
            ->where('code', self::AVAILABLE_ACCOUNT_CODE)
            ->where('owner_type', LedgerAccount::OWNER_TYPE_IDENTITY_ACCOUNT)
            ->where('owner_id', $accountId)
            ->first();
    }

    /** @return array<string, array{label: string, description: string, icon: string, modules: array<int, string>}> */
    private function historyCategoryDefinitions(): array
    {
        return [
            'advertising' => [
                'label' => 'Historique publicité',
                'description' => 'Gains issus des publicités et opérations Feed.',
                'icon' => '↗',
                'modules' => ['feed', 'campaigns'],
            ],
            'wallet' => [
                'label' => 'Historique Wallet',
                'description' => 'Dépôts et transferts entre membres.',
                'icon' => '⇄',
                'modules' => ['wallet'],
            ],
            'funds' => [
                'label' => 'Historique Fonds',
                'description' => 'Adhésions et mouvements entre le Wallet et Fonds.',
                'icon' => '◎',
                'modules' => ['funds'],
            ],
            'card' => [
                'label' => 'Historique Carte Wasplex',
                'description' => 'Paiements et opérations Carte Wasplex.',
                'icon' => '▣',
                'modules' => ['card', 'cards', 'wasplex_card'],
            ],
            'live' => [
                'label' => 'Historique Live',
                'description' => 'Gains et dépenses enregistrés par Wasplex Live.',
                'icon' => '◉',
                'modules' => ['live'],
            ],
        ];
    }
}'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise RuntimeError('Impossible de remplacer UserWalletQueryService::history')
write(path, text)

# Wallet controller: summary-only request plus category projection.
path = 'apps/platform/app/Modules/Wallet/Http/Controllers/User/WalletController.php'
text = read(path)
pattern = re.compile(r'    public function history\(Request \$request\): JsonResponse\n    \{.*?\n    \}\n\n    public function createDeposit', re.S)
replacement = r'''    public function history(Request $request): JsonResponse
    {
        /** @var Account $account */
        $account = $request->user();
        $data = $request->validate([
            'category' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25'],
            'summary' => ['nullable', 'boolean'],
        ]);

        $categories = $this->wallet->historyCategories($account->id);
        if ((bool) ($data['summary'] ?? false)) {
            return response()->json(['categories' => $categories]);
        }

        $entries = $this->wallet
            ->history($account->id, (int) ($data['per_page'] ?? 10), $data['category'] ?? null)
            ->through(fn ($entry) => [
                'id' => $entry->id,
                'direction' => $entry->direction,
                'amount_minor' => $entry->amount_minor,
                'description' => $entry->description,
                'type' => $entry->transaction?->type,
                'source_module' => $entry->transaction?->source_module,
                'created_at' => $entry->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'categories' => $categories,
            'selected_category' => $data['category'] ?? null,
            'history' => $entries,
        ]);
    }

    public function createDeposit'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise RuntimeError('Impossible de remplacer WalletController::history')
write(path, text)

# New lazy accordion component.
write('apps/platform/resources/js/Components/WalletHistoryAccordions.vue', r'''<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue';
import http from '@/lib/http';

type Category = {
    key: string;
    label: string;
    description: string;
    icon: string;
    count: number;
};

type Entry = {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    source_module: string | null;
    created_at: string | null;
};

type Bucket = {
    entries: Entry[];
    page: number;
    lastPage: number;
    loading: boolean;
    loaded: boolean;
};

const props = defineProps<{ refreshKey?: number }>();
const categories = reactive<Category[]>([]);
const open = reactive<Record<string, boolean>>({});
const buckets = reactive<Record<string, Bucket>>({});
const numberFormatter = new Intl.NumberFormat('fr-FR');

function bucket(key: string): Bucket {
    buckets[key] ??= { entries: [], page: 0, lastPage: 1, loading: false, loaded: false };
    return buckets[key];
}

function describe(entry: Entry): string {
    if (entry.description) return entry.description;
    if (entry.type === 'USER_WALLET_DEPOSIT') return 'Dépôt Wallet';
    if (entry.type === 'USER_TRANSFER') return entry.direction === 'credit' ? 'Transfert reçu' : 'Transfert envoyé';
    if (entry.type === 'FUND_MEMBERSHIP_FEE') return 'Adhésion au programme Fonds';
    return entry.type ?? (entry.direction === 'credit' ? 'Crédit Wallet' : 'Débit Wallet');
}

async function loadCategories(): Promise<void> {
    const { data } = await http.get('/me/wallet/history', { params: { summary: 1 } });
    categories.splice(0, categories.length, ...(data.categories ?? []));
}

async function loadCategory(key: string, reset = false): Promise<void> {
    const state = bucket(key);
    if (state.loading) return;

    state.loading = true;
    try {
        const page = reset ? 1 : state.page + 1;
        const { data } = await http.get('/me/wallet/history', {
            params: { category: key, page, per_page: 5 },
        });
        const pagination = data.history;
        const entries: Entry[] = pagination?.data ?? [];
        state.entries = reset ? entries : [...state.entries, ...entries];
        state.page = pagination?.current_page ?? page;
        state.lastPage = pagination?.last_page ?? state.page;
        state.loaded = true;

        const updated = (data.categories ?? []).find((item: Category) => item.key === key);
        const current = categories.find((item) => item.key === key);
        if (updated && current) current.count = updated.count;
    } finally {
        state.loading = false;
    }
}

async function toggle(category: Category): Promise<void> {
    open[category.key] = !open[category.key];
    if (open[category.key] && !bucket(category.key).loaded) {
        await loadCategory(category.key, true);
    }
}

async function refresh(): Promise<void> {
    await loadCategories();
    await Promise.all(
        categories.filter((category) => open[category.key]).map((category) => loadCategory(category.key, true)),
    );
}

onMounted(loadCategories);
watch(() => props.refreshKey, refresh);
</script>

<template>
    <section class="flex flex-col gap-2.5">
        <div>
            <h2 class="text-wpx-white-soft text-sm font-bold">Historique</h2>
            <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Chaque module conserve sa vue, alimentée par le même Grand Livre.</p>
        </div>

        <article
            v-for="category in categories"
            :key="category.key"
            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg overflow-hidden border"
        >
            <button type="button" class="flex w-full items-center gap-3 p-3.5 text-left" @click="toggle(category)">
                <span class="bg-wpx-navy-750 text-wpx-gold flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base font-bold">
                    {{ category.icon }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="text-wpx-white-soft block text-sm font-bold">{{ category.label }}</span>
                    <span class="text-wpx-muted-dark mt-0.5 block truncate text-[10px]">{{ category.description }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="category.count ? 'bg-wpx-success/12 text-wpx-success-light' : 'bg-white/5 text-wpx-muted-dark'"
                    >{{ category.count }}</span>
                    <span class="text-wpx-muted-dark text-sm">{{ open[category.key] ? '⌃' : '⌄' }}</span>
                </span>
            </button>

            <div v-if="open[category.key]" class="border-wpx-border-dark border-t px-3.5 pb-3.5">
                <p v-if="bucket(category.key).loading && !bucket(category.key).loaded" class="text-wpx-muted-dark py-4 text-center text-xs">Chargement…</p>
                <ul v-else class="divide-wpx-border-dark divide-y">
                    <li
                        v-for="entry in bucket(category.key).entries"
                        :key="entry.id"
                        class="flex items-center justify-between gap-3 py-3"
                    >
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
                    <li v-if="bucket(category.key).entries.length === 0" class="text-wpx-muted-dark py-5 text-center text-xs">
                        Aucun mouvement dans cette catégorie.
                    </li>
                </ul>
                <button
                    v-if="bucket(category.key).page < bucket(category.key).lastPage"
                    type="button"
                    class="border-wpx-border-dark text-wpx-blue mt-2 w-full rounded-xl border px-3 py-2.5 text-xs font-bold disabled:opacity-50"
                    :disabled="bucket(category.key).loading"
                    @click="loadCategory(category.key)"
                >
                    {{ bucket(category.key).loading ? 'Chargement…' : 'Voir plus' }}
                </button>
            </div>
        </article>
    </section>
</template>
''')

# WalletPanel: let the dedicated component own history state.
path = 'apps/platform/resources/js/Components/WalletPanel.vue'
text = read(path)
text = replace_once(text, "import http from '@/lib/http';\n", "import http from '@/lib/http';\nimport WalletHistoryAccordions from '@/Components/WalletHistoryAccordions.vue';\n", 'WalletPanel import')
text = re.sub(r'interface HistoryEntry \{.*?\}\n\n', '', text, count=1, flags=re.S)
text = replace_once(text, 'const history = ref<HistoryEntry[]>([]);\n', '', 'WalletPanel history ref')
text = replace_once(text, 'const historyOpen = ref(true);\n', 'const historyRefreshKey = ref(0);\n', 'WalletPanel history open')
old_load = """        const [walletRes, historyRes] = await Promise.all([http.get('/me/wallet'), http.get('/me/wallet/history')]);
        balance.value = walletRes.data.balance_minor;
        summary.value = walletRes.data.summary ?? summary.value;
        history.value = historyRes.data.history.data ?? historyRes.data.history;
"""
new_load = """        const walletRes = await http.get('/me/wallet');
        balance.value = walletRes.data.balance_minor;
        summary.value = walletRes.data.summary ?? summary.value;
        historyRefreshKey.value += 1;
"""
text = replace_once(text, old_load, new_load, 'WalletPanel load')
text = re.sub(r'\nfunction describe\(entry: HistoryEntry\): string \{.*?\n\}\n', '\n', text, count=1, flags=re.S)
heading = '<span class="text-wpx-white-soft block text-sm font-bold">Activité du Wallet</span>'
idx = text.find(heading)
if idx < 0:
    raise RuntimeError('Section Activité du Wallet introuvable')
section_start = text.rfind('        <section', 0, idx)
teleport_start = text.find('        <Teleport to="body">', idx)
if section_start < 0 or teleport_start < 0:
    raise RuntimeError('Bornes section historique Wallet introuvables')
text = text[:section_start] + '        <WalletHistoryAccordions :refresh-key="historyRefreshKey" />\n\n' + text[teleport_start:]
write(path, text)

# ---------------------------------------------------------------------------
# Fonds membership fee: real Ledger debit, never a decorative price.
# ---------------------------------------------------------------------------
path = 'apps/platform/app/Modules/Funds/Application/Services/FundContributionService.php'
text = read(path)
marker = '    private function fundBalanceReference(string $accountId): LedgerAccountReference\n'
method = r'''    public function chargeMembershipFee(
        string $accountId,
        string $membershipId,
        string $programVersionId,
        int $amountMinor,
        ?string $createdBy = null,
    ): ?string {
        if ($amountMinor <= 0) {
            return null;
        }

        return DB::transaction(function () use ($accountId, $membershipId, $programVersionId, $amountMinor, $createdBy): string {
            $this->lockAccountFlow($accountId);

            if ($this->wallet->balanceMinor($accountId) < $amountMinor) {
                throw new RuntimeException('Solde Wallet insuffisant pour payer l’adhésion Fonds.');
            }

            $transaction = $this->posting->post(new PostLedgerTransaction(
                type: 'FUND_MEMBERSHIP_FEE',
                sourceModule: 'funds',
                idempotencyKey: 'fund-membership-fee:'.$membershipId,
                entries: [
                    LedgerEntryInput::debit(
                        $this->wallet->availableAccountReference($accountId),
                        Money::of($amountMinor, 'WP'),
                        'Adhésion au programme Fonds',
                    ),
                    LedgerEntryInput::credit(
                        LedgerAccountReference::system('wasplex.funds.membership.revenue', 'REVENUE', 'WP'),
                        Money::of($amountMinor, 'WP'),
                        'Revenu adhésion Fonds Wasplex',
                    ),
                ],
                businessReference: 'fund-membership:'.$membershipId,
                createdBy: $createdBy,
                metadata: [
                    'account_id' => $accountId,
                    'fund_membership_id' => $membershipId,
                    'program_version_id' => $programVersionId,
                ],
                ruleVersion: 'P014-A-membership-fee-v1',
            ));

            return (string) $transaction->id;
        });
    }

    public function notifyMembershipFeeCharged(string $accountId, int $amountMinor, string $ledgerTransactionId): void
    {
        $this->wallet->notifyBalanceChanged(
            $accountId,
            -$amountMinor,
            'funds',
            'membership_fee',
            $ledgerTransactionId,
        );
    }

'''
text = replace_once(text, marker, method + marker, 'FundContributionService membership fee')
write(path, text)

# FundsController join: enforce program class + charge fee atomically.
path = 'apps/platform/app/Modules/Funds/Http/Controllers/User/FundsController.php'
text = read(path)
needle = """        $version = $program->publishedVersion();
        abort_if($version === null, 422, 'Ce programme ne possède aucune version publiée.');

        $cap = (int) $data['personal_cap_minor'];
"""
replacement = """        $version = $program->publishedVersion();
        abort_if($version === null, 422, 'Ce programme ne possède aucune version publiée.');

        $eligibleClasses = array_map('strtoupper', $version->eligible_subscription_classes ?? []);
        $subscriptionClass = strtoupper((string) ($subscription->economicClass?->code ?? ''));
        abort_if(
            $eligibleClasses !== [] && ! in_array($subscriptionClass, $eligibleClasses, true),
            422,
            'Votre niveau d’abonnement Wasplex n’est pas éligible à ce programme Fonds.',
        );

        $cap = (int) $data['personal_cap_minor'];
"""
text = replace_once(text, needle, replacement, 'FundsController class eligibility')
old = re.compile(r"        \$membership = DB::transaction\(function \(\) use \(\$accountId, \$subscription, \$program, \$version, \$cap\): FundMembership \{.*?        \}\);\n\n        return response\(\)->json\(\$this->membershipPayload\(\$membership->load\(\['program', 'version', 'mandate'\]\)\), 201\);", re.S)
new = r'''        $feeTransactionId = null;

        try {
            $membership = DB::transaction(function () use ($accountId, $subscription, $program, $version, $cap, &$feeTransactionId): FundMembership {
                $membership = FundMembership::query()->create([
                    'account_id' => $accountId,
                    'fund_program_id' => $program->id,
                    'program_version_id' => $version->id,
                    'user_subscription_id' => $subscription->id,
                    'status' => FundMembership::STATUS_ACTIVE,
                    'started_at' => now(),
                ]);

                FundMandate::query()->create([
                    'fund_membership_id' => $membership->id,
                    'personal_cap_minor' => $cap,
                    'is_active' => true,
                    'terms_version' => 'P014-A-v1',
                    'accepted_at' => now(),
                    'accepted_snapshot' => [
                        'program_id' => $program->id,
                        'program_version_id' => $version->id,
                        'membership_fee_minor' => $version->membership_fee_minor,
                        'min_debit_minor' => $version->min_debit_minor,
                        'max_debit_minor' => $version->max_debit_minor,
                        'monthly_cap_minor' => $version->monthly_cap_minor,
                        'notice_hours' => $version->notice_hours,
                        'grace_period_days' => $version->grace_period_days,
                        'personal_cap_minor' => $cap,
                    ],
                ]);

                $feeTransactionId = $this->contributions->chargeMembershipFee(
                    $accountId,
                    (string) $membership->id,
                    (string) $version->id,
                    (int) $version->membership_fee_minor,
                    $accountId,
                );

                FundAuditEvent::record($accountId, 'FundMembershipStarted', 'fund_membership', $membership->id, [
                    'program_version_id' => $version->id,
                    'membership_fee_minor' => (int) $version->membership_fee_minor,
                    'ledger_transaction_id' => $feeTransactionId,
                ]);

                return $membership;
            });
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($feeTransactionId !== null) {
            $this->contributions->notifyMembershipFeeCharged(
                $accountId,
                (int) $version->membership_fee_minor,
                $feeTransactionId,
            );
        }

        return response()->json($this->membershipPayload($membership->load(['program', 'version', 'mandate'])), 201);'''
text, count = old.subn(new, text, count=1)
if count != 1:
    raise RuntimeError('Bloc adhésion FundsController introuvable')
write(path, text)

# User presentation: show the actual seeded commercial limits.
path = 'apps/platform/resources/js/Components/FundsPanel.vue'
text = read(path)
needle = """                            <p class=\"text-wpx-muted-dark mt-0.5 text-xs\">
                                {{ program.max_wishes_per_period }} vœu(x) · apport
                                {{ program.personal_contribution_percent }}%
                            </p>
"""
replacement = needle + """                            <p class=\"text-wpx-gold mt-1 text-[11px] font-bold\">
                                Adhésion {{ money(program.membership_fee_minor) }} · jusqu’à {{ money(program.max_wish_amount_minor) }} / vœu
                            </p>
"""
text = replace_once(text, needle, replacement, 'FundsPanel program commercial details')
write(path, text)

# ---------------------------------------------------------------------------
# Complete idempotent Funds catalog.
# ---------------------------------------------------------------------------
write('apps/platform/app/Modules/Funds/Console/SeedFundProgramsCommand.php', r'''<?php

declare(strict_types=1);

namespace App\Modules\Funds\Console;

use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use Illuminate\Console\Command;

final class SeedFundProgramsCommand extends Command
{
    protected $signature = 'funds:seed-programs';

    protected $description = 'Initialise les 3 programmes Fonds et les catégories de lancement (idempotent).';

    /** @var array<int, array<string, mixed>> */
    private const PROGRAMS = [
        [
            'code' => 'fonds-essentiel',
            'name' => 'Fonds Essentiel',
            'sort_order' => 10,
            'membership_fee_minor' => 1000,
            'duration_days' => 365,
            'max_active_wishes' => 1,
            'max_wishes_per_period' => 2,
            'max_wish_amount_minor' => 150000,
            'personal_contribution_percent' => 30,
            'min_debit_minor' => 100,
            'max_debit_minor' => 500,
            'daily_cap_minor' => 1000,
            'monthly_cap_minor' => 3000,
            'annual_cap_minor' => 36000,
            'wasplex_fee_minor' => 50,
            'notice_hours' => 24,
            'grace_period_days' => 7,
            'arrears_grace_days' => 7,
            'max_simultaneous_collections' => 1,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 0,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['PREMIUM', 'GOLD', 'PLATINUM'],
        ],
        [
            'code' => 'fonds-plus',
            'name' => 'Fonds Plus',
            'sort_order' => 20,
            'membership_fee_minor' => 2500,
            'duration_days' => 365,
            'max_active_wishes' => 2,
            'max_wishes_per_period' => 3,
            'max_wish_amount_minor' => 500000,
            'personal_contribution_percent' => 25,
            'min_debit_minor' => 250,
            'max_debit_minor' => 1000,
            'daily_cap_minor' => 2000,
            'monthly_cap_minor' => 7500,
            'annual_cap_minor' => 90000,
            'wasplex_fee_minor' => 100,
            'notice_hours' => 24,
            'grace_period_days' => 7,
            'arrears_grace_days' => 7,
            'max_simultaneous_collections' => 2,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 10,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['GOLD', 'PLATINUM'],
        ],
        [
            'code' => 'fonds-excellence',
            'name' => 'Fonds Excellence',
            'sort_order' => 30,
            'membership_fee_minor' => 5000,
            'duration_days' => 365,
            'max_active_wishes' => 2,
            'max_wishes_per_period' => 4,
            'max_wish_amount_minor' => 1000000,
            'personal_contribution_percent' => 20,
            'min_debit_minor' => 500,
            'max_debit_minor' => 2000,
            'daily_cap_minor' => 4000,
            'monthly_cap_minor' => 15000,
            'annual_cap_minor' => 180000,
            'wasplex_fee_minor' => 150,
            'notice_hours' => 48,
            'grace_period_days' => 10,
            'arrears_grace_days' => 10,
            'max_simultaneous_collections' => 2,
            'emergency_queue_share_percent' => 20,
            'reserve_min_balance_minor' => 0,
            'reciprocity_min_score' => 20,
            'rehabilitation_incident_threshold' => 3,
            'eligible_subscription_classes' => ['PLATINUM'],
        ],
    ];

    /** @var array<int, array<string, mixed>> */
    private const CATEGORIES = [
        ['code' => 'sante-soins', 'name' => 'Santé & soins', 'icon' => '❤', 'description' => 'Soins, médicaments, examens et interventions médicales vérifiées.', 'sensitive' => true],
        ['code' => 'logement-habitat', 'name' => 'Logement & habitat', 'icon' => '⌂', 'description' => 'Logement essentiel, rénovation et amélioration de l’habitat.', 'sensitive' => false],
        ['code' => 'mobilite-transport', 'name' => 'Mobilité & transport', 'icon' => '↗', 'description' => 'Moto, tricycle, taxi ou véhicule nécessaire à une activité.', 'sensitive' => false],
        ['code' => 'formation-scolarite', 'name' => 'Formation & scolarité', 'icon' => '✦', 'description' => 'Formation, scolarité et équipement éducatif vérifiable.', 'sensitive' => false],
        ['code' => 'equipement-professionnel', 'name' => 'Équipement professionnel', 'icon' => '⚙', 'description' => 'Outils et équipements nécessaires à une activité professionnelle.', 'sensitive' => false],
        ['code' => 'agriculture-production', 'name' => 'Agriculture & production', 'icon' => '♧', 'description' => 'Équipement agricole et moyens de production.', 'sensitive' => false],
        ['code' => 'eau-energie', 'name' => 'Eau & énergie', 'icon' => '◇', 'description' => 'Accès à l’eau, électrification et solutions énergétiques essentielles.', 'sensitive' => false],
        ['code' => 'creation-activite', 'name' => 'Création / relance d’activité', 'icon' => '＋', 'description' => 'Création ou relance documentée d’une activité économique.', 'sensitive' => false],
        ['code' => 'accessibilite-handicap', 'name' => 'Accessibilité & handicap', 'icon' => '◎', 'description' => 'Équipements et adaptations liés à l’accessibilité.', 'sensitive' => true],
        ['code' => 'urgence-familiale', 'name' => 'Urgence familiale grave', 'icon' => '!', 'description' => 'Besoin familial grave vérifié pouvant relever de la file urgence.', 'sensitive' => true],
    ];

    public function handle(): int
    {
        foreach (self::PROGRAMS as $definition) {
            $program = FundProgram::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'status' => FundProgram::STATUS_ACTIVE,
                    'sort_order' => $definition['sort_order'],
                ],
            );

            if (! $program->versions()->exists()) {
                FundProgramVersion::query()->create([
                    'fund_program_id' => $program->id,
                    'version' => 1,
                    'currency' => 'XOF',
                    'membership_fee_minor' => $definition['membership_fee_minor'],
                    'duration_days' => $definition['duration_days'],
                    'minimum_subscription_age_days' => 0,
                    'max_active_wishes' => $definition['max_active_wishes'],
                    'max_wishes_per_period' => $definition['max_wishes_per_period'],
                    'max_wish_amount_minor' => $definition['max_wish_amount_minor'],
                    'personal_contribution_percent' => $definition['personal_contribution_percent'],
                    'min_debit_minor' => $definition['min_debit_minor'],
                    'max_debit_minor' => $definition['max_debit_minor'],
                    'daily_cap_minor' => $definition['daily_cap_minor'],
                    'monthly_cap_minor' => $definition['monthly_cap_minor'],
                    'annual_cap_minor' => $definition['annual_cap_minor'],
                    'wasplex_fee_minor' => $definition['wasplex_fee_minor'],
                    'notice_hours' => $definition['notice_hours'],
                    'grace_period_days' => $definition['grace_period_days'],
                    'arrears_grace_days' => $definition['arrears_grace_days'],
                    'max_simultaneous_collections' => $definition['max_simultaneous_collections'],
                    'emergency_queue_share_percent' => $definition['emergency_queue_share_percent'],
                    'reserve_min_balance_minor' => $definition['reserve_min_balance_minor'],
                    'reciprocity_min_score' => $definition['reciprocity_min_score'],
                    'rehabilitation_incident_threshold' => $definition['rehabilitation_incident_threshold'],
                    'eligible_subscription_classes' => $definition['eligible_subscription_classes'],
                    'status' => 'published',
                    'published_at' => now(),
                ]);
            }
        }

        foreach (self::CATEGORIES as $index => $definition) {
            FundWishCategory::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'icon' => $definition['icon'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'requires_sensitive_documents' => $definition['sensitive'],
                    'sort_order' => ($index + 1) * 10,
                ],
            );
        }

        $this->info('Fonds prêt : Essentiel, Plus et Excellence.');
        $this->info('Adhésions annuelles : 1 000 / 2 500 / 5 000 FCFA.');
        $this->info('10 catégories de vœux initialisées.');
        $this->warn('Une relance du seed ne remplace jamais les paramètres déjà modifiés par l’administration.');

        return self::SUCCESS;
    }
}
''')

# Register seed command.
path = 'apps/platform/app/Modules/Funds/Infrastructure/Providers/FundsServiceProvider.php'
text = read(path)
text = replace_once(
    text,
    'use App\\Modules\\Funds\\Console\\FundIntegrityCheckCommand;\n',
    'use App\\Modules\\Funds\\Console\\FundIntegrityCheckCommand;\nuse App\\Modules\\Funds\\Console\\SeedFundProgramsCommand;\n',
    'FundsServiceProvider seed import',
)
text = replace_once(
    text,
    '$this->commands([FundIntegrityCheckCommand::class]);',
    '$this->commands([FundIntegrityCheckCommand::class, SeedFundProgramsCommand::class]);',
    'FundsServiceProvider commands',
)
write(path, text)

# Focused tests for the new contract.
write('apps/platform/tests/Feature/Wallet/WalletHistoryAndFundsSeedTest.php', r'''<?php

declare(strict_types=1);

use App\Modules\Funds\Infrastructure\Models\FundMembership;
use App\Modules\Funds\Infrastructure\Models\FundProgram;
use App\Modules\Funds\Infrastructure\Models\FundProgramVersion;
use App\Modules\Funds\Infrastructure\Models\FundWishCategory;
use App\Modules\Identity\Infrastructure\Models\Account;
use App\Modules\Ledger\Application\Services\LedgerPostingContract;
use App\Modules\Ledger\Domain\ValueObjects\LedgerEntryInput;
use App\Modules\Ledger\Domain\ValueObjects\PostLedgerTransaction;
use App\Modules\Subscriptions\Infrastructure\Models\EconomicClass;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionEntitlement;
use App\Modules\Subscriptions\Infrastructure\Models\SubscriptionPlanVersion;
use App\Modules\Subscriptions\Infrastructure\Models\UserSubscription;
use App\Modules\Wallet\Application\Services\UserWalletQueryService;
use App\Shared\Money\Money;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    Artisan::call('ledger:seed-catalog');
    Artisan::call('subscriptions:seed-catalog');
});

function walletHistoryAccount(string $identifier): Account
{
    return Account::query()->whereHas('identifiers', fn ($query) => $query->where('value', $identifier))->firstOrFail();
}

function walletHistoryCredit(string $accountId, int $amountMinor, string $key, string $module = 'tests'): void
{
    app(LedgerPostingContract::class)->post(new PostLedgerTransaction(
        type: 'TEST_WALLET_CREDIT',
        sourceModule: $module,
        idempotencyKey: $key,
        entries: [
            LedgerEntryInput::debit(ledgerSuspense(), Money::of($amountMinor, 'WP'), 'Crédit test'),
            LedgerEntryInput::credit(ledgerUserAvailable($accountId), Money::of($amountMinor, 'WP'), 'Crédit test'),
        ],
    ));
}

function walletHistoryMakeEligibleSubscription(Account $account, string $classCode = EconomicClass::CODE_PREMIUM): void
{
    $economicClass = EconomicClass::query()->where('code', $classCode)->firstOrFail();
    $planVersion = SubscriptionPlanVersion::query()
        ->whereHas('plan', fn ($query) => $query->where('code', $classCode))
        ->firstOrFail();

    $subscription = UserSubscription::query()->create([
        'account_id' => $account->id,
        'plan_version_id' => $planVersion->id,
        'economic_class_id' => $economicClass->id,
        'status' => UserSubscription::STATUS_ACTIVE,
        'started_at' => now(),
        'current_period_end' => now()->addDays(30),
    ]);

    SubscriptionEntitlement::query()->create([
        'user_subscription_id' => $subscription->id,
        'key' => SubscriptionEntitlement::KEY_FONDS_ELIGIBLE,
        'enabled' => true,
    ]);
}

it('seeds three complete fund programs and categories without overwriting admin changes', function (): void {
    Artisan::call('funds:seed-programs');

    expect(FundProgram::query()->count())->toBe(3)
        ->and(FundProgramVersion::query()->count())->toBe(3)
        ->and(FundWishCategory::query()->count())->toBe(10);

    $essential = FundProgram::query()->where('code', 'fonds-essentiel')->firstOrFail();
    $version = $essential->publishedVersion();

    expect($version)->not->toBeNull()
        ->and((int) $version->membership_fee_minor)->toBe(1000)
        ->and((int) $version->max_wishes_per_period)->toBe(2)
        ->and((int) $version->max_wish_amount_minor)->toBe(150000)
        ->and((int) $version->personal_contribution_percent)->toBe(30)
        ->and($version->eligible_subscription_classes)->toBe(['PREMIUM', 'GOLD', 'PLATINUM']);

    $version->update(['membership_fee_minor' => 1234]);
    Artisan::call('funds:seed-programs');

    expect(FundProgram::query()->count())->toBe(3)
        ->and(FundProgramVersion::query()->count())->toBe(3)
        ->and((int) $version->refresh()->membership_fee_minor)->toBe(1234);
});

it('charges the real membership fee from Wallet and classifies it in Fonds history', function (): void {
    Artisan::call('funds:seed-programs');
    registerAndLogin('wallet-funds-membership@example.com');
    $account = walletHistoryAccount('wallet-funds-membership@example.com');
    walletHistoryMakeEligibleSubscription($account);
    walletHistoryCredit($account->id, 1500, 'wallet-history-membership-credit');

    $program = FundProgram::query()->where('code', 'fonds-essentiel')->firstOrFail();

    test()->postJson('/api/funds/membership', [
        'program_id' => $program->id,
        'personal_cap_minor' => 500,
        'accept_mandate' => true,
    ])->assertCreated()
        ->assertJsonPath('status', FundMembership::STATUS_ACTIVE);

    expect(app(UserWalletQueryService::class)->balanceMinor($account->id))->toBe(500);

    test()->getJson('/api/me/wallet/history?summary=1')
        ->assertOk()
        ->assertJsonFragment(['key' => 'funds', 'label' => 'Historique Fonds', 'count' => 1]);

    test()->getJson('/api/me/wallet/history?category=funds&per_page=5')
        ->assertOk()
        ->assertJsonPath('history.data.0.type', 'FUND_MEMBERSHIP_FEE')
        ->assertJsonPath('history.data.0.source_module', 'funds')
        ->assertJsonPath('history.data.0.amount_minor', 1000);
});

it('keeps standard Wallet history accordions even when a future module has no movement yet', function (): void {
    registerAndLogin('wallet-history-categories@example.com');
    $account = walletHistoryAccount('wallet-history-categories@example.com');
    walletHistoryCredit($account->id, 300, 'wallet-history-feed-credit', 'feed');
    walletHistoryCredit($account->id, 200, 'wallet-history-wallet-credit', 'wallet');

    $response = test()->getJson('/api/me/wallet/history?summary=1')->assertOk();
    $keys = collect($response->json('categories'))->pluck('key')->all();

    expect($keys)->toContain('advertising', 'wallet', 'funds', 'card', 'live');
    $response->assertJsonFragment(['key' => 'advertising', 'count' => 1]);
    $response->assertJsonFragment(['key' => 'wallet', 'count' => 1]);
    $response->assertJsonFragment(['key' => 'card', 'count' => 0]);
    $response->assertJsonFragment(['key' => 'live', 'count' => 0]);
});
''')

print('Wallet history + Fonds seed patch applied.')
