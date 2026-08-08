<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';
import { campaignDisplayName } from '@/lib/campaignObjectives';

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
    quoted: 'Devisée',
    funded: 'Financée',
    submitted: 'Soumise',
    changes_requested: 'Correction demandée',
    approved: 'Active',
    rejected: 'Rejetée',
    suspended: 'Suspendue',
};

const rows = ref<Row[]>([]);
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
        const [campaignsRes, reportsRes] = await Promise.all([
            http.get('/advertiser/campaigns'),
            http.get('/advertiser/campaigns/report'),
        ]);
        const campaigns: Campaign[] = campaignsRes.data.campaigns;
        const reports: CampaignReport[] = reportsRes.data.reports;

        rows.value = reports.map((report) => {
            const campaign = campaigns.find((c) => c.id === report.campaign_id);
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
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Le tableau de bord est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

const totalBudgetAllocated = computed(() => rows.value.reduce((total, r) => total + r.budgetAmountMinor, 0));
const totalBudgetCaptured = computed(() => rows.value.reduce((total, r) => total + r.budgetCapturedMinor, 0));
const totalViews = computed(() => rows.value.reduce((total, r) => total + r.views, 0));
const activeCount = computed(() => rows.value.filter((r) => r.status === 'approved').length);
const draftCount = computed(() => rows.value.filter((r) => ['draft', 'quoted', 'funded'].includes(r.status)).length);
const costPerView = computed(() =>
    totalViews.value === 0 ? 0 : Math.round((totalBudgetCaptured.value / totalViews.value) * 10) / 10,
);

function statusClasses(status: string): string {
    if (status === 'approved') {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (status === 'rejected' || status === 'suspended') {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

void load();
</script>

<template>
    <div class="flex flex-col gap-5">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>
        <p v-else-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>

        <template v-else>
            <!-- Cartes de métriques -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-wpx-md shadow-wpx-card bg-wpx-surface border-wpx-border border p-4.5">
                    <div class="flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="6" width="18" height="13" rx="3" stroke="#FF9A3D" stroke-width="1.8" />
                            <rect x="3" y="10" width="18" height="2.4" fill="#FF9A3D" />
                        </svg>
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Budget consommé</p>
                    </div>
                    <p class="text-wpx-text mt-2.5 text-2xl font-extrabold">
                        {{ numberFormatter.format(totalBudgetCaptured) }}
                        <span class="text-wpx-text-muted text-[13px] font-bold">WP</span>
                    </p>
                    <p class="text-wpx-success-light mt-1 text-[11px] font-bold">
                        sur {{ numberFormatter.format(totalBudgetAllocated) }} WP alloués
                    </p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card bg-wpx-surface border-wpx-border border p-4.5">
                    <div class="flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M3 10l14-6-4 16-3-6-6-4z"
                                stroke="#075CCF"
                                stroke-width="1.8"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                            Campagnes actives
                        </p>
                    </div>
                    <p class="text-wpx-text mt-2.5 text-2xl font-extrabold">{{ activeCount }}</p>
                    <p class="text-wpx-text-muted mt-1 text-[11px]">{{ draftCount }} en brouillon</p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card bg-wpx-surface border-wpx-border border p-4.5">
                    <div class="flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"
                                stroke="#2BC4DE"
                                stroke-width="1.7"
                            />
                            <circle cx="12" cy="12" r="3" stroke="#2BC4DE" stroke-width="1.7" />
                        </svg>
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Vues</p>
                    </div>
                    <p class="text-wpx-text mt-2.5 text-2xl font-extrabold">{{ numberFormatter.format(totalViews) }}</p>
                    <p class="text-wpx-text-muted mt-1 text-[11px]">Attention qualifiée, toutes campagnes</p>
                </div>
                <div class="rounded-wpx-md shadow-wpx-card bg-wpx-surface border-wpx-border border p-4.5">
                    <div class="flex items-center gap-2">
                        <svg width="15" height="15" viewBox="0 0 24 24">
                            <path
                                d="M12 2l2.9 6.6 7.1.7-5.4 4.7 1.6 7-6.2-3.8-6.2 3.8 1.6-7L2 9.3l7.1-.7z"
                                fill="#F2C14E"
                            />
                        </svg>
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Coût par vue</p>
                    </div>
                    <p class="text-wpx-text mt-2.5 text-2xl font-extrabold">
                        {{ numberFormatter.format(costPerView) }}
                        <span class="text-wpx-text-muted text-[13px] font-bold">WP</span>
                    </p>
                    <p class="text-wpx-text-muted mt-1 text-[11px]">Moyenne, toutes campagnes actives</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-[1.4fr_1fr]">
                <!-- Vues totales (pas de ventilation quotidienne aujourd'hui) -->
                <div
                    class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface border-wpx-border flex flex-col items-center justify-center border p-6 text-center"
                >
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Vues totales</p>
                    <p class="text-wpx-text mt-2 text-4xl font-extrabold">{{ numberFormatter.format(totalViews) }}</p>
                    <p class="text-wpx-text-muted mt-2 text-xs">Répartition quotidienne bientôt disponible.</p>
                </div>

                <!-- Répartition du budget -->
                <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface border-wpx-border border p-5.5">
                    <p class="text-wpx-text mb-3.5 text-sm font-bold">Répartition du budget</p>
                    <div class="flex flex-col gap-3.5">
                        <div v-for="row in rows" :key="row.campaignId">
                            <div class="mb-1.5 flex justify-between text-xs">
                                <span class="text-wpx-text font-bold">{{ row.name }}</span>
                                <span class="text-wpx-text-muted">
                                    {{ numberFormatter.format(row.budgetCapturedMinor) }} WP
                                </span>
                            </div>
                            <div class="bg-wpx-raised h-1.75 overflow-hidden rounded-full">
                                <div
                                    class="bg-wpx-blue-light h-full"
                                    :style="{
                                        width: `${row.budgetAmountMinor === 0 ? 0 : Math.min(100, (row.budgetCapturedMinor / row.budgetAmountMinor) * 100)}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <p v-if="rows.length === 0" class="text-wpx-text-muted text-sm">Aucune campagne encore.</p>
                    </div>
                </div>
            </div>

            <!-- Mes campagnes -->
            <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface border-wpx-border overflow-hidden border">
                <p class="text-wpx-text border-wpx-border border-b px-5.5 py-4 text-sm font-bold">Mes campagnes</p>
                <table v-if="rows.length > 0" class="w-full text-sm">
                    <thead>
                        <tr
                            class="text-wpx-text-muted border-wpx-border border-b text-left text-[11px] font-bold tracking-wide uppercase"
                        >
                            <th class="px-5.5 py-2.5">Campagne</th>
                            <th class="px-5.5 py-2.5">Statut</th>
                            <th class="px-5.5 py-2.5">Budget</th>
                            <th class="px-5.5 py-2.5">Vues</th>
                            <th class="px-5.5 py-2.5">Coût / vue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.campaignId"
                            class="border-wpx-border text-wpx-text border-b last:border-0"
                        >
                            <td class="px-5.5 py-3.5 font-bold">{{ row.name }}</td>
                            <td class="px-5.5 py-3.5">
                                <span
                                    class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                                    :class="statusClasses(row.status)"
                                >
                                    {{ STATUS_LABELS[row.status] ?? row.status }}
                                </span>
                            </td>
                            <td class="px-5.5 py-3.5">{{ numberFormatter.format(row.budgetAmountMinor) }} WP</td>
                            <td class="px-5.5 py-3.5">{{ numberFormatter.format(row.views) }}</td>
                            <td class="px-5.5 py-3.5">
                                {{
                                    row.views === 0
                                        ? '—'
                                        : numberFormatter.format(
                                              Math.round((row.budgetCapturedMinor / row.views) * 10) / 10,
                                          )
                                }}
                                WP
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-wpx-text-muted p-5.5 text-sm">
                    Aucune campagne encore — créez-en une dans l'onglet Campagnes.
                </p>
            </div>
        </template>
    </div>
</template>
