<script setup lang="ts">
import { ref, watch } from 'vue';
import http from '@/lib/http';

const props = defineProps<{ campaignId: string }>();

interface Report {
    status: string;
    budget_amount_minor: number;
    budget_reserved_minor: number;
    budget_captured_minor: number;
    budget_released_minor: number;
    feed: {
        total_deliveries: number;
        started: number;
        completed: number;
        abandoned: number;
        expired: number;
        held: number;
        gain_distributed_minor: number;
        attention_rate: number;
    };
}

interface PublicReport {
    status: string;
    distribution_mode: 'members_only' | 'members_public';
    public_url: string | null;
    stats: {
        views: number;
        unique_visitors: number;
        completions: number;
        shares: number;
        wasplex_cta_clicks: number;
    };
}

const report = ref<Report | null>(null);
const publicReport = ref<PublicReport | null>(null);
const loading = ref(true);
const distributionBusy = ref(false);
const distributionError = ref<string | null>(null);
const copied = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const percentFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 1 });

async function load(): Promise<void> {
    loading.value = true;
    distributionError.value = null;
    try {
        const [reportRes, publicRes] = await Promise.all([
            http.get(`/advertiser/campaigns/${props.campaignId}/report`),
            http.get(`/advertiser/campaigns/${props.campaignId}/public-report`),
        ]);
        report.value = reportRes.data.report;
        publicReport.value = publicRes.data.public_report;
    } finally {
        loading.value = false;
    }
}

async function setDistribution(mode: 'members_only' | 'members_public'): Promise<void> {
    if (distributionBusy.value || publicReport.value?.distribution_mode === mode) return;

    distributionBusy.value = true;
    distributionError.value = null;
    try {
        await http.patch(`/advertiser/campaigns/${props.campaignId}/distribution`, { distribution_mode: mode });
        const { data } = await http.get(`/advertiser/campaigns/${props.campaignId}/public-report`);
        publicReport.value = data.public_report;
    } catch (error) {
        distributionError.value =
            (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'La portée publique ne peut pas être modifiée pour le moment.';
    } finally {
        distributionBusy.value = false;
    }
}

async function copyPublicLink(): Promise<void> {
    if (!publicReport.value?.public_url) return;
    await navigator.clipboard.writeText(publicReport.value.public_url);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1600);
}

watch(() => props.campaignId, load, { immediate: true });

defineExpose({ load });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h3 class="text-wpx-text mb-3 text-sm font-semibold">Performance</h3>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <div v-else-if="report" class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Livraisons</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.feed.total_deliveries) }}
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Attention qualifiée</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.feed.completed) }}
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Taux d'attention</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ percentFormatter.format(report.feed.attention_rate) }}
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Gain distribué</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.feed.gain_distributed_minor) }} WP
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Budget cible</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.budget_amount_minor) }} WP
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Budget réservé</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.budget_reserved_minor) }} WP
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Budget consommé</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ numberFormatter.format(report.budget_captured_minor) }} WP
                    </p>
                </div>
                <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                    <p class="text-wpx-text-muted text-xs">Abandons / expirés / en attente</p>
                    <p class="text-wpx-text text-lg font-semibold">
                        {{ report.feed.abandoned }} / {{ report.feed.expired }} / {{ report.feed.held }}
                    </p>
                </div>
            </div>
        </div>

        <div v-if="!loading && publicReport" class="border-wpx-border-dark bg-wpx-navy-950 rounded-wpx-lg border p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-extrabold tracking-[0.14em] uppercase">Portée publique</p>
                    <h3 class="text-wpx-white-soft mt-1 text-sm font-extrabold">
                        Diffuser aussi hors connexion Wasplex
                    </h3>
                    <p class="text-wpx-muted-dark mt-1 max-w-2xl text-xs leading-relaxed">
                        Une vue publique apporte de la visibilité supplémentaire, mais verse 0 WP, ne consomme aucun
                        quota membre et ne touche jamais au budget récompense.
                    </p>
                </div>
                <span
                    class="rounded-full px-2.5 py-1 text-[10px] font-extrabold"
                    :class="
                        publicReport.distribution_mode === 'members_public'
                            ? 'bg-wpx-success/12 text-wpx-success-light'
                            : 'bg-wpx-navy-750 text-wpx-muted-dark'
                    "
                >
                    {{ publicReport.distribution_mode === 'members_public' ? 'Public activé' : 'Membres uniquement' }}
                </span>
            </div>

            <p v-if="distributionError" class="bg-wpx-danger/10 text-wpx-danger-light mt-3 rounded-xl p-3 text-xs">
                {{ distributionError }}
            </p>

            <div class="mt-4 grid gap-2 sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-xl border p-3 text-left text-xs font-bold disabled:opacity-50"
                    :class="
                        publicReport.distribution_mode === 'members_only'
                            ? 'border-wpx-gold bg-wpx-gold/10 text-wpx-gold'
                            : 'border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark'
                    "
                    :disabled="distributionBusy"
                    @click="setDistribution('members_only')"
                >
                    <span class="block text-sm">Membres Wasplex uniquement</span>
                    <span class="mt-1 block font-normal opacity-75">Diffusion classique dans le Feed.</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-3 text-left text-xs font-bold disabled:opacity-50"
                    :class="
                        publicReport.distribution_mode === 'members_public'
                            ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan'
                            : 'border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark'
                    "
                    :disabled="distributionBusy || publicReport.status !== 'approved'"
                    @click="setDistribution('members_public')"
                >
                    <span class="block text-sm">Membres + public</span>
                    <span class="mt-1 block font-normal opacity-75">
                        {{
                            publicReport.status === 'approved'
                                ? 'Lien public partageable, sans rémunération.'
                                : 'Disponible après approbation.'
                        }}
                    </span>
                </button>
            </div>

            <div
                v-if="publicReport.distribution_mode === 'members_public' && publicReport.public_url"
                class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-xl border p-3"
            >
                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Lien public</p>
                <p class="text-wpx-white-soft mt-1 text-xs font-semibold break-all">{{ publicReport.public_url }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="bg-wpx-gold text-wpx-navy-950 rounded-lg px-3 py-2 text-[11px] font-extrabold"
                        @click="copyPublicLink"
                    >
                        {{ copied ? 'Copié ✓' : 'Copier le lien' }}
                    </button>
                    <a
                        :href="publicReport.public_url"
                        target="_blank"
                        rel="noopener"
                        class="border-wpx-border-dark text-wpx-cyan rounded-lg border px-3 py-2 text-[11px] font-bold"
                    >
                        Ouvrir la page ↗
                    </a>
                </div>
            </div>

            <div
                v-if="publicReport.distribution_mode === 'members_public'"
                class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-5"
            >
                <div class="bg-wpx-navy-850 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px]">Vues publiques</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-black">
                        {{ numberFormatter.format(publicReport.stats.views) }}
                    </p>
                </div>
                <div class="bg-wpx-navy-850 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px]">Visiteurs approx.</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-black">
                        {{ numberFormatter.format(publicReport.stats.unique_visitors) }}
                    </p>
                </div>
                <div class="bg-wpx-navy-850 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px]">Lectures complètes</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-black">
                        {{ numberFormatter.format(publicReport.stats.completions) }}
                    </p>
                </div>
                <div class="bg-wpx-navy-850 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px]">Partages</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-black">
                        {{ numberFormatter.format(publicReport.stats.shares) }}
                    </p>
                </div>
                <div class="bg-wpx-navy-850 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px]">Vers Wasplex</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-black">
                        {{ numberFormatter.format(publicReport.stats.wasplex_cta_clicks) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
