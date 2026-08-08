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

const emit = defineEmits<{ navigate: [section: 'campaign-reviews' | 'advertisers'] }>();

const summary = ref<Summary | null>(null);
const reviewCases = ref<ReviewCase[]>([]);
const pendingAdvertiserCount = ref(0);
const healthy = ref<boolean | null>(null);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const percentFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 1 });

const wpInCirculation = computed(() => {
    const byType = summary.value?.ledger.net_by_account_type ?? {};
    return (byType.LIABILITY_USER ?? 0) + (byType.LIABILITY_ADVERTISER ?? 0);
});

const pendingBrandNames = computed(() =>
    reviewCases.value
        .map((c) => c.brand?.name)
        .filter((name): name is string => Boolean(name))
        .slice(0, 3)
        .join(', '),
);

const nothingToHandle = computed(() => reviewCases.value.length === 0 && pendingAdvertiserCount.value === 0);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [summaryRes, reviewsRes, advertisersRes] = await Promise.allSettled([
            http.get('/admin/dashboard/summary'),
            http.get('/admin/campaign-reviews'),
            http.get('/admin/advertisers'),
        ]);

        summary.value = summaryRes.status === 'fulfilled' ? summaryRes.value.data : null;
        reviewCases.value = reviewsRes.status === 'fulfilled' ? reviewsRes.value.data.review_cases : [];
        pendingAdvertiserCount.value =
            advertisersRes.status === 'fulfilled'
                ? advertisersRes.value.data.advertisers.filter((a: Advertiser) => a.status === 'draft').length
                : 0;
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
    <div class="flex flex-col gap-5">
        <div
            class="rounded-wpx-md flex items-center gap-3 border bg-white p-3.5"
            :class="healthy === false ? 'border-wpx-danger/40' : 'border-wpx-success/30'"
        >
            <span
                class="h-2.5 w-2.5 shrink-0 rounded-full"
                :class="healthy === false ? 'bg-wpx-danger' : 'bg-wpx-success'"
            />
            <span
                class="text-[13px] font-bold"
                :class="healthy === false ? 'text-wpx-danger-light' : 'text-wpx-success-light'"
            >
                {{ healthy === false ? 'Un service technique rencontre un problème' : 'Tout fonctionne normalement' }}
            </span>
            <span class="text-wpx-text-muted ml-auto text-xs">Infrastructure (base de données, cache)</span>
        </div>

        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>

        <template v-else>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Utilisateurs</p>
                    <p class="text-wpx-text-muted mt-2 text-sm italic">Bientôt disponible</p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">WP en circulation</p>
                    <p class="text-wpx-text mt-2 text-2xl font-extrabold">
                        {{ numberFormatter.format(wpInCirculation) }}
                    </p>
                    <p class="text-wpx-text-muted mt-1 text-xs">≈ {{ numberFormatter.format(wpInCirculation) }} FCFA</p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Campagnes actives</p>
                    <p class="text-wpx-text mt-2 text-2xl font-extrabold">
                        {{ summary?.campaigns.active_campaigns ?? 0 }}
                    </p>
                    <p v-if="reviewCases.length > 0" class="text-wpx-warning-light mt-1 text-xs font-bold">
                        {{ reviewCases.length }} en attente de validation
                    </p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Retraits en attente</p>
                    <p class="text-wpx-text-muted mt-2 text-sm italic">Bientôt disponible</p>
                </div>
            </div>

            <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface overflow-hidden border">
                <p class="text-wpx-text border-wpx-border border-b px-5 py-3.5 text-sm font-bold">À traiter</p>
                <div
                    v-if="reviewCases.length > 0"
                    class="border-wpx-border/60 flex items-center gap-3 border-b px-5 py-3.5"
                >
                    <span
                        class="bg-wpx-warning/10 rounded-wpx-sm flex h-8.5 w-8.5 shrink-0 items-center justify-center"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M3 10l14-6-4 16-3-6-6-4z"
                                stroke="#9A5B00"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span class="flex-1">
                        <span class="text-wpx-text block text-[13px] font-bold">
                            {{ reviewCases.length }} campagne{{ reviewCases.length > 1 ? 's' : '' }} attend{{
                                reviewCases.length > 1 ? 'ent' : ''
                            }}
                            ta validation
                        </span>
                        <span class="text-wpx-text-muted block text-[11px]">{{ pendingBrandNames }}</span>
                    </span>
                    <button
                        type="button"
                        class="rounded-wpx-sm bg-wpx-navy-950 px-4 py-2 text-xs font-bold whitespace-nowrap text-white"
                        @click="emit('navigate', 'campaign-reviews')"
                    >
                        Examiner
                    </button>
                </div>
                <div v-if="pendingAdvertiserCount > 0" class="flex items-center gap-3 px-5 py-3.5">
                    <span
                        class="bg-wpx-blue-light/10 rounded-wpx-sm flex h-8.5 w-8.5 shrink-0 items-center justify-center"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="8" r="3" stroke="#075CCF" stroke-width="1.6" />
                            <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6" stroke="#075CCF" stroke-width="1.6" />
                        </svg>
                    </span>
                    <span class="flex-1">
                        <span class="text-wpx-text block text-[13px] font-bold">
                            {{ pendingAdvertiserCount }} nouveau{{ pendingAdvertiserCount > 1 ? 'x' : '' }} compte{{
                                pendingAdvertiserCount > 1 ? 's' : ''
                            }}
                            annonceur
                        </span>
                        <span class="text-wpx-text-muted block text-[11px]">En attente de vérification</span>
                    </span>
                    <button
                        type="button"
                        class="text-wpx-blue-light text-xs font-bold whitespace-nowrap"
                        @click="emit('navigate', 'advertisers')"
                    >
                        Voir
                    </button>
                </div>
                <p v-if="nothingToHandle" class="text-wpx-text-muted px-5 py-4 text-sm">
                    Rien à traiter pour le moment.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-wpx-text text-sm font-bold">Grand Livre</p>
                        <span
                            class="rounded-wpx-full px-2.5 py-0.5 text-[11px] font-bold"
                            :class="
                                summary?.ledger.is_balanced
                                    ? 'bg-wpx-success/10 text-wpx-success-light'
                                    : 'bg-wpx-danger/10 text-wpx-danger-light'
                            "
                        >
                            {{ summary?.ledger.is_balanced ? 'Équilibré' : 'Déséquilibre' }}
                        </span>
                    </div>
                    <p class="text-wpx-text-muted text-xs">Transactions comptabilisées</p>
                    <p class="text-wpx-text text-xl font-extrabold">
                        {{ numberFormatter.format(summary?.ledger.transaction_count ?? 0) }}
                    </p>
                </div>
                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-5">
                    <p class="text-wpx-text mb-3 text-sm font-bold">Feed & Abonnements</p>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-wpx-text-muted text-xs">WP distribués (publicités vues)</p>
                            <p class="text-wpx-text font-bold">
                                {{ numberFormatter.format(summary?.feed.gain_distributed_minor ?? 0) }} WP
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-xs">Taux d'attention</p>
                            <p class="text-wpx-text font-bold">
                                {{ percentFormatter.format(summary?.feed.attention_rate ?? 0) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-xs">Abonnements actifs</p>
                            <p class="text-wpx-text font-bold">
                                {{ summary?.subscriptions.active_subscriptions ?? 0 }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-xs">Revenu abonnements</p>
                            <p class="text-wpx-text font-bold">
                                {{ numberFormatter.format(summary?.subscriptions.total_revenue_minor ?? 0) }} WP
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
