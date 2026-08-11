<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Summary {
    ledger: {
        total_debit_minor: number;
        total_credit_minor: number;
        is_balanced: boolean;
        transaction_count: number;
        net_by_account_type: Record<string, number>;
    };
    feed: {
        total_deliveries: number;
        completed: number;
        gain_distributed_minor: number;
        attention_rate: number;
    };
    campaigns: {
        active_campaigns: number;
        total_reserved_minor: number;
        total_captured_minor: number;
        total_released_minor: number;
    };
    subscriptions: {
        active_subscriptions: number;
        total_revenue_minor: number;
    };
}

interface ReviewCase {
    id: string;
    brand?: { name: string };
}

interface Advertiser {
    id: string;
    status: string;
}

interface Hold {
    id: string;
    amount_minor: number;
}

interface LedgerTransaction {
    id: string;
    status: string;
}

const emit = defineEmits<{
    navigate: [section: 'users' | 'advertising' | 'finance' | 'offers'];
}>();

const summary = ref<Summary | null>(null);
const reviewCases = ref<ReviewCase[]>([]);
const pendingAdvertiserCount = ref(0);
const antifraudHolds = ref<Hold[]>([]);
const pendingFinanceCount = ref(0);
const usersCount = ref<number | null>(null);
const healthy = ref<boolean | null>(null);
const geniusPayConfigured = ref<boolean | null>(null);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const percentFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 1 });

const wpInCirculation = computed(() => {
    const byType = summary.value?.ledger.net_by_account_type ?? {};
    return (byType.LIABILITY_USER ?? 0) + (byType.LIABILITY_ADVERTISER ?? 0);
});

const antifraudAmount = computed(() => antifraudHolds.value.reduce((total, hold) => total + hold.amount_minor, 0));
const totalPriorities = computed(
    () =>
        reviewCases.value.length +
        pendingAdvertiserCount.value +
        antifraudHolds.value.length +
        pendingFinanceCount.value,
);
const platformHealthy = computed(() => healthy.value !== false && summary.value?.ledger.is_balanced !== false);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [summaryRes, reviewsRes, advertisersRes, holdsRes, transactionsRes, usersRes, geniusPayRes] =
            await Promise.allSettled([
                http.get('/admin/dashboard/summary'),
                http.get('/admin/campaign-reviews'),
                http.get('/admin/advertisers'),
                http.get('/admin/feed/holds'),
                http.get('/admin/ledger/transactions'),
                http.get('/admin/accounts'),
                http.get('/admin/advertiser-wallet/geniuspay'),
            ]);

        summary.value = summaryRes.status === 'fulfilled' ? summaryRes.value.data : null;
        reviewCases.value = reviewsRes.status === 'fulfilled' ? reviewsRes.value.data.review_cases : [];
        pendingAdvertiserCount.value =
            advertisersRes.status === 'fulfilled'
                ? advertisersRes.value.data.advertisers.filter(
                      (advertiser: Advertiser) => advertiser.status === 'draft',
                  ).length
                : 0;
        antifraudHolds.value = holdsRes.status === 'fulfilled' ? holdsRes.value.data.holds : [];
        pendingFinanceCount.value =
            transactionsRes.status === 'fulfilled'
                ? transactionsRes.value.data.transactions.filter(
                      (transaction: LedgerTransaction) => transaction.status === 'pending_approval',
                  ).length
                : 0;
        usersCount.value = usersRes.status === 'fulfilled' ? usersRes.value.data.accounts.length : null;
        geniusPayConfigured.value =
            geniusPayRes.status === 'fulfilled' ? Boolean(geniusPayRes.value.data.geniuspay?.configured) : null;
    } finally {
        loading.value = false;
    }

    try {
        const { data } = await http.get('/health');
        healthy.value = data.status === 'ok';
    } catch {
        healthy.value = false;
    }
}

onMounted(load);
defineExpose({ load });
</script>

<template>
    <div class="text-wpx-white-soft flex flex-col gap-5">
        <section
            class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark relative overflow-hidden border p-5 md:p-6"
        >
            <div class="from-wpx-blue/10 to-wpx-gold/5 pointer-events-none absolute inset-0 bg-gradient-to-br" />
            <div class="relative flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-wpx-cyan text-xs font-extrabold tracking-[0.16em] uppercase">Console fondateur</p>
                    <h1 class="mt-2 text-2xl font-extrabold md:text-3xl">Voici l'essentiel de Wasplex.</h1>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm">
                        Commence par les décisions qui attendent ton intervention. Les détails techniques restent
                        disponibles seulement quand tu en as besoin.
                    </p>
                </div>
                <div
                    class="rounded-wpx-lg border p-4 lg:min-w-64"
                    :class="
                        platformHealthy
                            ? 'border-wpx-success/30 bg-wpx-success/10'
                            : 'border-wpx-danger/35 bg-wpx-danger/10'
                    "
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :class="platformHealthy ? 'bg-wpx-success' : 'bg-wpx-danger'"
                        />
                        <p class="text-sm font-extrabold">
                            {{ platformHealthy ? 'Wasplex fonctionne normalement' : 'Une vérification est nécessaire' }}
                        </p>
                    </div>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Infrastructure et équilibre comptable surveillés.</p>
                </div>
            </div>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement du poste de commandement…</p>

        <template v-else>
            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
            >
                <div class="border-wpx-border-dark flex items-center justify-between border-b px-5 py-4">
                    <div>
                        <h2 class="text-base font-extrabold">À traiter maintenant</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            Les décisions qui peuvent bloquer une personne ou de l'argent.
                        </p>
                    </div>
                    <span
                        class="rounded-wpx-full px-3 py-1 text-xs font-extrabold"
                        :class="
                            totalPriorities > 0
                                ? 'bg-wpx-warning/15 text-wpx-gold'
                                : 'bg-wpx-success/15 text-wpx-success'
                        "
                    >
                        {{ totalPriorities > 0 ? `${totalPriorities} à examiner` : 'Tout est à jour' }}
                    </span>
                </div>

                <div class="divide-wpx-border-dark divide-y">
                    <button
                        type="button"
                        class="hover:bg-wpx-navy-750/60 flex w-full items-center gap-3 px-5 py-4 text-left"
                        @click="emit('navigate', 'advertising')"
                    >
                        <span
                            class="bg-wpx-blue/12 rounded-wpx-md text-wpx-blue flex h-10 w-10 shrink-0 items-center justify-center"
                            >◎</span
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >{{ pendingAdvertiserCount }} annonceur{{ pendingAdvertiserCount > 1 ? 's' : '' }} à
                                vérifier</span
                            >
                            <span class="text-wpx-muted-dark block text-xs">Avant leur première campagne.</span>
                        </span>
                        <span class="text-wpx-cyan text-xs font-extrabold">Examiner →</span>
                    </button>

                    <button
                        type="button"
                        class="hover:bg-wpx-navy-750/60 flex w-full items-center gap-3 px-5 py-4 text-left"
                        @click="emit('navigate', 'advertising')"
                    >
                        <span
                            class="bg-wpx-gold/12 rounded-wpx-md text-wpx-gold flex h-10 w-10 shrink-0 items-center justify-center"
                            >▶</span
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >{{ reviewCases.length }} campagne{{ reviewCases.length > 1 ? 's' : '' }} à
                                approuver</span
                            >
                            <span class="text-wpx-muted-dark block text-xs"
                                >Publicités financées qui attendent ta décision.</span
                            >
                        </span>
                        <span class="text-wpx-cyan text-xs font-extrabold">Examiner →</span>
                    </button>

                    <button
                        type="button"
                        class="hover:bg-wpx-navy-750/60 flex w-full items-center gap-3 px-5 py-4 text-left"
                        @click="emit('navigate', 'advertising')"
                    >
                        <span
                            class="bg-wpx-danger/12 rounded-wpx-md text-wpx-danger flex h-10 w-10 shrink-0 items-center justify-center"
                            >!</span
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >{{ antifraudHolds.length }} récompense{{
                                    antifraudHolds.length > 1 ? 's' : ''
                                }}
                                retenue{{ antifraudHolds.length > 1 ? 's' : '' }} par l'antifraude</span
                            >
                            <span class="text-wpx-muted-dark block text-xs">
                                {{ numberFormatter.format(antifraudAmount) }} WP attendent une décision humaine.
                            </span>
                        </span>
                        <span class="text-wpx-cyan text-xs font-extrabold">Examiner →</span>
                    </button>

                    <button
                        type="button"
                        class="hover:bg-wpx-navy-750/60 flex w-full items-center gap-3 px-5 py-4 text-left"
                        @click="emit('navigate', 'finance')"
                    >
                        <span
                            class="bg-wpx-success/12 rounded-wpx-md text-wpx-success flex h-10 w-10 shrink-0 items-center justify-center"
                            >₣</span
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold"
                                >{{ pendingFinanceCount }} opération{{
                                    pendingFinanceCount > 1 ? 's' : ''
                                }}
                                financière{{ pendingFinanceCount > 1 ? 's' : '' }} à valider</span
                            >
                            <span class="text-wpx-muted-dark block text-xs"
                                >La double validation reste obligatoire.</span
                            >
                        </span>
                        <span class="text-wpx-cyan text-xs font-extrabold">Examiner →</span>
                    </button>
                </div>
            </section>

            <section>
                <div class="mb-3 flex items-end justify-between">
                    <div>
                        <h2 class="text-base font-extrabold">Wasplex en un coup d'œil</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">Les chiffres utiles, sans jargon comptable.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <button
                        type="button"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4 text-left"
                        @click="emit('navigate', 'users')"
                    >
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            Utilisateurs
                        </p>
                        <p class="mt-2 text-2xl font-extrabold">
                            {{ usersCount === null ? '—' : numberFormatter.format(usersCount) }}
                        </p>
                        <p class="text-wpx-cyan mt-1 text-[11px] font-bold">Voir les comptes →</p>
                    </button>
                    <button
                        type="button"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4 text-left"
                        @click="emit('navigate', 'finance')"
                    >
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            WP en circulation
                        </p>
                        <p class="mt-2 text-2xl font-extrabold">{{ numberFormatter.format(wpInCirculation) }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">
                            ≈ {{ numberFormatter.format(wpInCirculation) }} FCFA
                        </p>
                    </button>
                    <button
                        type="button"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4 text-left"
                        @click="emit('navigate', 'advertising')"
                    >
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            Publicités actives
                        </p>
                        <p class="mt-2 text-2xl font-extrabold">{{ summary?.campaigns.active_campaigns ?? 0 }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">
                            {{ summary?.feed.completed ?? 0 }} vues complètes
                        </p>
                    </button>
                    <button
                        type="button"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4 text-left"
                        @click="emit('navigate', 'offers')"
                    >
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            Abonnements actifs
                        </p>
                        <p class="mt-2 text-2xl font-extrabold">
                            {{ summary?.subscriptions.active_subscriptions ?? 0 }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Offres Wasplex</p>
                    </button>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-extrabold">Grand Livre</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">Toutes les écritures restent traçables.</p>
                        </div>
                        <span
                            class="rounded-wpx-full px-2.5 py-1 text-[11px] font-extrabold"
                            :class="
                                summary?.ledger.is_balanced
                                    ? 'bg-wpx-success/15 text-wpx-success'
                                    : 'bg-wpx-danger/15 text-wpx-danger'
                            "
                        >
                            {{ summary?.ledger.is_balanced ? 'Équilibré' : 'À vérifier' }}
                        </span>
                    </div>
                    <p class="mt-4 text-xl font-extrabold">
                        {{ numberFormatter.format(summary?.ledger.transaction_count ?? 0) }}
                    </p>
                    <p class="text-wpx-muted-dark text-[11px]">transactions comptabilisées</p>
                </div>

                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-extrabold">Attention publicitaire</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">Récompenses déjà validées.</p>
                        </div>
                        <span class="text-wpx-gold text-lg font-extrabold"
                            >{{ numberFormatter.format(summary?.feed.gain_distributed_minor ?? 0) }} WP</span
                        >
                    </div>
                    <p class="mt-4 text-xl font-extrabold">
                        {{ percentFormatter.format(summary?.feed.attention_rate ?? 0) }}
                    </p>
                    <p class="text-wpx-muted-dark text-[11px]">taux d'attention</p>
                </div>

                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-extrabold">Paiements annonceurs</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">État de la connexion GeniusPay.</p>
                        </div>
                        <span
                            class="rounded-wpx-full px-2.5 py-1 text-[11px] font-extrabold"
                            :class="
                                geniusPayConfigured
                                    ? 'bg-wpx-success/15 text-wpx-success'
                                    : 'bg-wpx-warning/15 text-wpx-gold'
                            "
                        >
                            {{ geniusPayConfigured ? 'Configuré' : 'À configurer' }}
                        </span>
                    </div>
                    <p class="text-wpx-muted-dark mt-4 text-xs">Mode actuel : intégration sandbox / test.</p>
                </div>
            </section>
        </template>
    </div>
</template>
