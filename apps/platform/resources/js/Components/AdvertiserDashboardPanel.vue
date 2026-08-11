<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';
import { campaignDisplayName } from '@/lib/campaignObjectives';

const props = defineProps<{
    organizationName: string;
}>();

const emit = defineEmits<{
    navigate: [section: 'dashboard' | 'campaigns' | 'wallet' | 'brands' | 'team'];
}>();

interface CampaignVersion {
    version_number: number;
    creative_configuration: Record<string, unknown> | null;
}

interface Campaign {
    id: string;
    objective_code: string | null;
    versions: CampaignVersion[];
}

interface CampaignReport {
    campaign_id: string;
    status: string;
    budget_amount_minor: number;
    budget_captured_minor: number;
    feed: { completed: number };
}

interface Row {
    campaignId: string;
    name: string;
    status: string;
    budgetAmountMinor: number;
    budgetCapturedMinor: number;
    views: number;
}

const STATUS_LABELS: Record<string, string> = {
    draft: 'Brouillon',
    quoted: 'Budget calculé',
    funded: 'Financée',
    submitted: 'En validation',
    changes_requested: 'À corriger',
    approved: 'Active',
    rejected: 'Refusée',
    suspended: 'Suspendue',
};

const rows = ref<Row[]>([]);
const wallet = ref<{ available_minor: number; currency: string } | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);

const numberFormatter = new Intl.NumberFormat('fr-FR');

function latestTitle(campaign: Campaign): string | null {
    const version = [...campaign.versions].sort((a, b) => b.version_number - a.version_number)[0];
    return (version?.creative_configuration?.title as string) ?? null;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;

    try {
        const [campaignsRes, reportsRes, walletRes] = await Promise.all([
            http.get('/advertiser/campaigns'),
            http.get('/advertiser/campaigns/report'),
            http.get('/advertiser/wallet'),
        ]);

        const campaigns: Campaign[] = campaignsRes.data.campaigns;
        const reports: CampaignReport[] = reportsRes.data.reports;

        wallet.value = walletRes.data.wallet;
        rows.value = reports.map((report) => {
            const campaign = campaigns.find((item) => item.id === report.campaign_id);

            return {
                campaignId: report.campaign_id,
                name: campaignDisplayName(campaign?.objective_code ?? null, campaign ? latestTitle(campaign) : null),
                status: report.status,
                budgetAmountMinor: report.budget_amount_minor,
                budgetCapturedMinor: report.budget_captured_minor,
                views: report.feed.completed,
            };
        });
    } catch (e) {
        loadError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le Studio annonceur est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

const totalViews = computed(() => rows.value.reduce((total, row) => total + row.views, 0));
const totalSpent = computed(() => rows.value.reduce((total, row) => total + row.budgetCapturedMinor, 0));
const activeCount = computed(() => rows.value.filter((row) => row.status === 'approved').length);
const waitingCount = computed(() =>
    rows.value.filter((row) => ['submitted', 'funded', 'quoted', 'changes_requested'].includes(row.status)).length,
);
const draftCount = computed(() => rows.value.filter((row) => row.status === 'draft').length);
const recentRows = computed(() => rows.value.slice(0, 3));

function statusClasses(status: string): string {
    if (status === 'approved') return 'bg-wpx-success/15 text-wpx-success-light';
    if (status === 'rejected' || status === 'suspended') return 'bg-wpx-danger/15 text-wpx-danger-light';
    if (status === 'submitted' || status === 'funded' || status === 'quoted') {
        return 'bg-wpx-cyan/12 text-wpx-cyan';
    }

    return 'bg-wpx-gold/12 text-wpx-gold';
}

void load();
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 lg:gap-5">
        <div v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-2xl p-4 text-sm">
            {{ loadError }}
        </div>

        <div v-else-if="loading" class="text-wpx-muted-dark flex min-h-64 items-center justify-center text-sm">
            Chargement du Studio…
        </div>

        <template v-else>
            <section
                class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-border-dark shadow-wpx-card-dark overflow-hidden rounded-3xl border bg-gradient-to-br"
            >
                <div class="p-5 sm:p-6 lg:flex lg:items-center lg:justify-between lg:gap-8">
                    <div class="max-w-xl">
                        <p class="text-wpx-cyan text-xs font-bold tracking-wide uppercase">{{ props.organizationName }}</p>
                        <h1 class="text-wpx-white-soft mt-2 text-2xl font-extrabold sm:text-3xl">
                            Faites connaître votre activité simplement.
                        </h1>
                        <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                            Créez, financez et envoyez une publicité à Wasplex en quelques minutes, sans connaissances techniques.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 shadow-wpx-card-dark mt-5 flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r px-5 py-4 text-base font-extrabold lg:mt-0 lg:w-auto lg:min-w-56"
                        @click="emit('navigate', 'campaigns')"
                    >
                        <span class="text-xl leading-none">+</span>
                        Créer une publicité
                    </button>
                </div>

                <div class="border-wpx-border-dark grid grid-cols-3 border-t">
                    <button
                        type="button"
                        class="px-2 py-3.5 text-center sm:px-4"
                        @click="emit('navigate', 'wallet')"
                    >
                        <p class="text-wpx-white-soft text-base font-extrabold sm:text-lg">
                            {{ numberFormatter.format(wallet?.available_minor ?? 0) }}
                        </p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px] font-semibold tracking-wide uppercase sm:text-[10px]">
                            FCFA disponibles
                        </p>
                    </button>
                    <button
                        type="button"
                        class="border-wpx-border-dark border-x px-2 py-3.5 text-center sm:px-4"
                        @click="emit('navigate', 'campaigns')"
                    >
                        <p class="text-wpx-white-soft text-base font-extrabold sm:text-lg">{{ activeCount }}</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px] font-semibold tracking-wide uppercase sm:text-[10px]">
                            Pub{{ activeCount > 1 ? 's' : '' }} active{{ activeCount > 1 ? 's' : '' }}
                        </p>
                    </button>
                    <div class="px-2 py-3.5 text-center sm:px-4">
                        <p class="text-wpx-white-soft text-base font-extrabold sm:text-lg">{{ numberFormatter.format(totalViews) }}</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px] font-semibold tracking-wide uppercase sm:text-[10px]">
                            Vues obtenues
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-3 gap-2.5 sm:gap-3">
                <button
                    type="button"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-2xl border p-3.5 text-left sm:p-4"
                    @click="emit('navigate', 'wallet')"
                >
                    <span class="bg-wpx-blue/14 flex h-9 w-9 items-center justify-center rounded-xl">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="6" width="18" height="13" rx="3" stroke="#4FA3FF" stroke-width="1.7" />
                            <path d="M3 10h18" stroke="#4FA3FF" stroke-width="1.7" />
                        </svg>
                    </span>
                    <p class="text-wpx-white-soft mt-2.5 text-xs font-bold sm:text-sm">Mon solde</p>
                    <p class="text-wpx-muted-dark mt-1 hidden text-[11px] leading-tight sm:block">Recharger et suivre mes dépenses</p>
                </button>

                <button
                    type="button"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-2xl border p-3.5 text-left sm:p-4"
                    @click="emit('navigate', 'campaigns')"
                >
                    <span class="bg-wpx-gold/14 flex h-9 w-9 items-center justify-center rounded-xl">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M3 10l14-6-4 16-3-6-6-4z" stroke="#F2C14E" stroke-width="1.7" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <p class="text-wpx-white-soft mt-2.5 text-xs font-bold sm:text-sm">Mes publicités</p>
                    <p class="text-wpx-muted-dark mt-1 hidden text-[11px] leading-tight sm:block">
                        {{ waitingCount }} en validation · {{ draftCount }} brouillon{{ draftCount > 1 ? 's' : '' }}
                    </p>
                </button>

                <button
                    type="button"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-2xl border p-3.5 text-left sm:p-4"
                    @click="emit('navigate', 'brands')"
                >
                    <span class="bg-wpx-cyan/14 flex h-9 w-9 items-center justify-center rounded-xl">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                            <path d="M4 7.5A2.5 2.5 0 016.5 5h11A2.5 2.5 0 0120 7.5V19H4V7.5z" stroke="#2BC4DE" stroke-width="1.7" />
                            <path d="M9 5V3.5h6V5M3 10h18" stroke="#2BC4DE" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </span>
                    <p class="text-wpx-white-soft mt-2.5 text-xs font-bold sm:text-sm">Mon activité</p>
                    <p class="text-wpx-muted-dark mt-1 hidden text-[11px] leading-tight sm:block">Identité, visuels et informations utiles</p>
                </button>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-wpx-white-soft text-sm font-bold">Créer une pub, c’est simple</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Trois décisions suffisent pour commencer.</p>
                    </div>
                    <span class="bg-wpx-success/12 text-wpx-success-light rounded-full px-2.5 py-1 text-[10px] font-bold">
                        &lt; 5 min
                    </span>
                </div>

                <div class="mt-4 grid gap-2.5 sm:grid-cols-3">
                    <div class="bg-wpx-navy-750/60 rounded-xl p-3">
                        <span class="text-wpx-gold text-xs font-extrabold">01</span>
                        <p class="text-wpx-white-soft mt-1 text-xs font-bold">Votre publicité</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Un visuel, un message et l’action souhaitée.</p>
                    </div>
                    <div class="bg-wpx-navy-750/60 rounded-xl p-3">
                        <span class="text-wpx-cyan text-xs font-extrabold">02</span>
                        <p class="text-wpx-white-soft mt-1 text-xs font-bold">Les personnes à toucher</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Une zone simple, puis des options seulement si nécessaire.</p>
                    </div>
                    <div class="bg-wpx-navy-750/60 rounded-xl p-3">
                        <span class="text-wpx-orange text-xs font-extrabold">03</span>
                        <p class="text-wpx-white-soft mt-1 text-xs font-bold">Votre budget</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Choisissez un montant, Wasplex calcule le reste.</p>
                    </div>
                </div>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-2xl border">
                <div class="border-wpx-border-dark flex items-center justify-between gap-3 border-b px-4 py-3.5 sm:px-5">
                    <div>
                        <p class="text-wpx-white-soft text-sm font-bold">Vos publicités récentes</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[11px]">
                            {{ numberFormatter.format(totalSpent) }} FCFA dépensés au total
                        </p>
                    </div>
                    <button type="button" class="text-wpx-blue text-xs font-bold" @click="emit('navigate', 'campaigns')">
                        Tout voir ›
                    </button>
                </div>

                <div v-if="recentRows.length === 0" class="px-4 py-8 text-center sm:px-5">
                    <p class="text-wpx-white-soft text-sm font-bold">Aucune publicité pour le moment</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Votre première campagne peut être prête en quelques minutes.</p>
                    <button
                        type="button"
                        class="text-wpx-gold mt-3 text-xs font-bold"
                        @click="emit('navigate', 'campaigns')"
                    >
                        Créer ma première publicité →
                    </button>
                </div>

                <div v-else class="divide-wpx-border-dark divide-y">
                    <button
                        v-for="row in recentRows"
                        :key="row.campaignId"
                        type="button"
                        class="flex w-full items-center gap-3 px-4 py-3.5 text-left sm:px-5"
                        @click="emit('navigate', 'campaigns')"
                    >
                        <span class="bg-wpx-navy-750 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path d="M3 10l14-6-4 16-3-6-6-4z" stroke="#F2C14E" stroke-width="1.7" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="text-wpx-white-soft block truncate text-sm font-bold">{{ row.name }}</span>
                            <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">
                                {{ numberFormatter.format(row.budgetAmountMinor) }} FCFA · {{ numberFormatter.format(row.views) }} vue{{ row.views > 1 ? 's' : '' }}
                            </span>
                        </span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-bold" :class="statusClasses(row.status)">
                            {{ STATUS_LABELS[row.status] ?? row.status }}
                        </span>
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>
