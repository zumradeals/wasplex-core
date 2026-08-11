<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import AdminWalletLedgerPanel from '@/Components/AdminWalletLedgerPanel.vue';

interface Summary {
    ledger: {
        is_balanced: boolean;
        transaction_count: number;
        net_by_account_type: Record<string, number>;
    };
    campaigns: {
        total_reserved_minor: number;
        total_captured_minor: number;
        total_released_minor: number;
    };
}

interface LedgerTransaction {
    id: string;
    type: string;
    status: string;
    source_module: string;
    business_reference: string | null;
    created_at: string;
}

const summary = ref<Summary | null>(null);
const transactions = ref<LedgerTransaction[]>([]);
const loading = ref(true);
const showAdvanced = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');

const wpInCirculation = computed(() => {
    const byType = summary.value?.ledger.net_by_account_type ?? {};
    return (byType.LIABILITY_USER ?? 0) + (byType.LIABILITY_ADVERTISER ?? 0);
});

const pendingTransactions = computed(() =>
    transactions.value.filter((transaction) => transaction.status === 'pending_approval'),
);
const recentTransactions = computed(() => transactions.value.slice(0, 5));

function typeLabel(type: string): string {
    return (
        {
            ADMIN_CORRECTION: 'Correction comptable',
            ADVERTISER_DEPOSIT_CREDIT: 'Crédit annonceur',
            CAMPAIGN_BUDGET_RESERVED: 'Budget campagne réservé',
            CAMPAIGN_EVENT_CAPTURED: 'Dépense campagne',
            USER_REWARD_CREDIT: 'Récompense utilisateur',
        }[type] ?? type.replaceAll('_', ' ').toLowerCase()
    );
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [summaryRes, transactionsRes] = await Promise.all([
            http.get('/admin/dashboard/summary'),
            http.get('/admin/ledger/transactions'),
        ]);
        summary.value = summaryRes.data;
        transactions.value = transactionsRes.data.transactions;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="text-wpx-white-soft flex flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5 md:p-6">
            <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">Finance</p>
            <h2 class="mt-1 text-2xl font-extrabold">La situation financière, sans langage comptable.</h2>
            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                Tu vois d’abord les montants utiles et les opérations qui attendent une décision. Le Grand Livre complet
                reste disponible en mode avancé pour la comptabilité et l’audit.
            </p>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement de la situation financière…</p>

        <template v-else>
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        WP en circulation
                    </p>
                    <p class="mt-2 text-2xl font-extrabold">{{ numberFormatter.format(wpInCirculation) }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                        ≈ {{ numberFormatter.format(wpInCirculation) }} FCFA
                    </p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        Budgets réservés
                    </p>
                    <p class="text-wpx-gold mt-2 text-2xl font-extrabold">
                        {{ numberFormatter.format(summary?.campaigns.total_reserved_minor ?? 0) }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">FCFA engagés sur des campagnes</p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        Dépenses campagnes
                    </p>
                    <p class="mt-2 text-2xl font-extrabold">
                        {{ numberFormatter.format(summary?.campaigns.total_captured_minor ?? 0) }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">FCFA déjà comptabilisés</p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Grand Livre</p>
                    <p
                        class="mt-2 text-2xl font-extrabold"
                        :class="summary?.ledger.is_balanced ? 'text-wpx-success' : 'text-wpx-danger'"
                    >
                        {{ summary?.ledger.is_balanced ? 'Équilibré' : 'À vérifier' }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                        {{ numberFormatter.format(summary?.ledger.transaction_count ?? 0) }} transactions
                    </p>
                </div>
            </section>

            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
            >
                <div class="border-wpx-border-dark flex items-center justify-between gap-4 border-b px-5 py-4">
                    <div>
                        <h3 class="text-base font-extrabold">À valider</h3>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            Les opérations soumises à une deuxième approbation.
                        </p>
                    </div>
                    <span
                        class="rounded-wpx-full px-3 py-1 text-xs font-extrabold"
                        :class="
                            pendingTransactions.length
                                ? 'bg-wpx-warning/15 text-wpx-gold'
                                : 'bg-wpx-success/15 text-wpx-success'
                        "
                    >
                        {{
                            pendingTransactions.length ? `${pendingTransactions.length} en attente` : 'Rien en attente'
                        }}
                    </span>
                </div>
                <div v-if="pendingTransactions.length" class="divide-wpx-border-dark divide-y">
                    <div
                        v-for="transaction in pendingTransactions"
                        :key="transaction.id"
                        class="flex items-center gap-3 px-5 py-4"
                    >
                        <span
                            class="bg-wpx-warning/12 text-wpx-gold rounded-wpx-md flex h-9 w-9 shrink-0 items-center justify-center"
                            >₣</span
                        >
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-extrabold">{{ typeLabel(transaction.type) }}</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-xs">
                                {{ new Date(transaction.created_at).toLocaleString('fr-FR') }} · deuxième validation
                                requise
                            </p>
                        </div>
                        <details class="shrink-0">
                            <summary class="text-wpx-cyan cursor-pointer text-xs font-extrabold">Détails</summary>
                            <div
                                class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-muted-dark shadow-wpx-card-dark absolute right-8 z-20 mt-2 max-w-sm border p-3 text-[10px]"
                            >
                                <p class="font-mono break-all">{{ transaction.id }}</p>
                                <p class="mt-1">Source : {{ transaction.source_module }}</p>
                                <p v-if="transaction.business_reference" class="mt-1 break-all">
                                    Référence : {{ transaction.business_reference }}
                                </p>
                            </div>
                        </details>
                    </div>
                </div>
                <p v-else class="text-wpx-muted-dark p-5 text-sm">
                    Aucune opération financière ne demande une deuxième validation.
                </p>
            </section>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-[1.2fr_1fr]">
                <div class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                    <h3 class="text-base font-extrabold">Dernières opérations</h3>
                    <div class="mt-3 flex flex-col gap-2">
                        <div
                            v-for="transaction in recentTransactions"
                            :key="transaction.id"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 flex items-center justify-between gap-3 border px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold">{{ typeLabel(transaction.type) }}</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                                    {{ new Date(transaction.created_at).toLocaleString('fr-FR') }}
                                </p>
                            </div>
                            <span
                                class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                :class="
                                    transaction.status === 'posted'
                                        ? 'bg-wpx-success/15 text-wpx-success'
                                        : 'bg-wpx-warning/15 text-wpx-gold'
                                "
                            >
                                {{ transaction.status === 'posted' ? 'Comptabilisée' : 'En attente' }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                    <h3 class="text-base font-extrabold">Mode comptable avancé</h3>
                    <p class="text-wpx-muted-dark mt-1 text-xs">
                        Comptes du Grand Livre, rapprochement, corrections et opérations exceptionnelles restent
                        disponibles ici sans encombrer ton écran quotidien.
                    </p>
                    <button
                        type="button"
                        class="border-wpx-border-dark text-wpx-cyan rounded-wpx-md mt-4 border px-4 py-2 text-xs font-extrabold"
                        @click="showAdvanced = !showAdvanced"
                    >
                        {{ showAdvanced ? 'Fermer le mode avancé' : 'Ouvrir le mode avancé' }}
                    </button>
                </div>
            </section>

            <section
                v-if="showAdvanced"
                class="[&_.bg-white]:bg-wpx-navy-850 [&_.bg-wpx-surface]:bg-wpx-navy-850 [&_.bg-wpx-canvas]:bg-wpx-navy-950 [&_.bg-wpx-raised]:bg-wpx-navy-950 [&_.text-wpx-text]:text-wpx-white-soft [&_.text-wpx-text-muted]:text-wpx-muted-dark [&_.border-wpx-border]:border-wpx-border-dark [&_.text-wpx-blue-light]:text-wpx-cyan [&_.text-wpx-success-light]:text-wpx-success [&_.text-wpx-warning-light]:text-wpx-gold [&_.text-wpx-danger-light]:text-wpx-danger [&_input]:bg-wpx-navy-950 [&_input]:text-wpx-white-soft"
            >
                <AdminWalletLedgerPanel />
            </section>
        </template>
    </div>
</template>
