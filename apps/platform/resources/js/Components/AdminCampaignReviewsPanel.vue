<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Brand {
    id: string;
    name: string;
}

interface Asset {
    id: string;
    type: string;
    url: string;
}

interface Quote {
    gross_amount_minor: number;
    estimated_events: number;
    class_breakdown: Record<string, { envelope_minor: number; gain_unitaire_minor: number }>;
}

interface CampaignVersion {
    creative_configuration: { asset_id?: string; title?: string } | null;
    audience_configuration: { territory?: { country_code?: string }; economic_classes?: string[] } | null;
    budget_configuration: { budget_amount_minor?: number } | null;
    quotes?: Quote[];
}

interface Campaign {
    id: string;
    objective_code: string | null;
    status: string;
    organization_id: string;
}

interface ReviewCase {
    id: string;
    status: string;
    opened_at: string;
    campaign: Campaign;
    campaign_version?: CampaignVersion;
    brand?: Brand;
}

const queue = ref<ReviewCase[]>([]);
const approvedCampaigns = ref<Array<Campaign & { brand?: Brand }>>([]);
const selectedCase = ref<{ review_case: ReviewCase; brand: Brand | null; asset: Asset | null } | null>(null);
const loading = ref(true);
const busy = ref(false);
const actionError = ref<string | null>(null);

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [queueRes, approvedRes] = await Promise.all([
            http.get('/admin/campaign-reviews'),
            http.get('/admin/campaigns/approved'),
        ]);
        queue.value = queueRes.data.review_cases;
        approvedCampaigns.value = approvedRes.data.campaigns;
    } finally {
        loading.value = false;
    }
}

async function openCase(reviewCase: ReviewCase): Promise<void> {
    actionError.value = null;
    const { data } = await http.get(`/admin/campaign-reviews/${reviewCase.id}`);
    selectedCase.value = data;
}

async function approve(): Promise<void> {
    if (!selectedCase.value) return;
    busy.value = true;
    actionError.value = null;
    try {
        await http.post(`/admin/campaign-reviews/${selectedCase.value.review_case.id}/approve`);
        selectedCase.value = null;
        await load();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Approbation impossible.';
    } finally {
        busy.value = false;
    }
}

async function requestChanges(): Promise<void> {
    if (!selectedCase.value) return;
    const reason = window.prompt('Motif de la demande de correction :');
    if (!reason) return;
    busy.value = true;
    actionError.value = null;
    try {
        await http.post(`/admin/campaign-reviews/${selectedCase.value.review_case.id}/request-changes`, { reason });
        selectedCase.value = null;
        await load();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Demande impossible.';
    } finally {
        busy.value = false;
    }
}

async function reject(): Promise<void> {
    if (!selectedCase.value) return;
    const reason = window.prompt('Motif du rejet :');
    if (!reason) return;
    busy.value = true;
    actionError.value = null;
    try {
        await http.post(`/admin/campaign-reviews/${selectedCase.value.review_case.id}/reject`, { reason });
        selectedCase.value = null;
        await load();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Rejet impossible.';
    } finally {
        busy.value = false;
    }
}

async function suspend(campaign: Campaign): Promise<void> {
    const reason = window.prompt('Motif de la suspension :');
    if (!reason) return;
    busy.value = true;
    try {
        await http.post(`/admin/campaigns/${campaign.id}/suspend`, { reason });
        await load();
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Campagnes en attente de revue ({{ queue.length }})</h2>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <ul v-else class="flex flex-col gap-1">
                <li v-for="reviewCase in queue" :key="reviewCase.id">
                    <button
                        type="button"
                        class="rounded-wpx-sm bg-wpx-canvas flex w-full items-center justify-between px-2 py-1.5 text-left text-sm"
                        @click="openCase(reviewCase)"
                    >
                        <span
                            >{{ reviewCase.brand?.name ?? 'Marque inconnue' }} —
                            {{ reviewCase.campaign.objective_code ?? 'Campagne' }}</span
                        >
                        <span class="text-wpx-text-muted text-xs">{{
                            new Date(reviewCase.opened_at).toLocaleString()
                        }}</span>
                    </button>
                </li>
                <li v-if="!loading && queue.length === 0" class="text-wpx-text-muted text-sm">
                    Aucune campagne en attente.
                </li>
            </ul>
        </div>

        <div v-if="selectedCase" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h3 class="text-wpx-text mb-3 text-sm font-semibold">
                Dossier — {{ selectedCase.brand?.name ?? 'Marque inconnue' }}
            </h3>

            <p v-if="actionError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-sm mb-3 p-2 text-xs">
                {{ actionError }}
            </p>

            <div class="mb-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <span class="text-wpx-text-muted text-xs">Objectif</span>
                    <p>{{ selectedCase.review_case.campaign.objective_code ?? '—' }}</p>
                </div>
                <div v-if="selectedCase.asset">
                    <span class="text-wpx-text-muted text-xs">Média</span>
                    <video
                        v-if="selectedCase.asset.type === 'video'"
                        :src="selectedCase.asset.url"
                        class="mt-1 h-32 rounded"
                        controls
                    />
                    <img v-else :src="selectedCase.asset.url" alt="" class="mt-1 h-32 rounded object-cover" />
                </div>
                <div v-if="selectedCase.review_case.campaign_version?.audience_configuration">
                    <span class="text-wpx-text-muted text-xs">Audience</span>
                    <p>
                        {{
                            selectedCase.review_case.campaign_version.audience_configuration.economic_classes?.join(
                                ', ',
                            )
                        }}
                        <template v-if="selectedCase.review_case.campaign_version.audience_configuration.territory">
                            —
                            {{
                                selectedCase.review_case.campaign_version.audience_configuration.territory.country_code
                            }}
                        </template>
                    </p>
                </div>
                <div v-if="selectedCase.review_case.campaign_version?.quotes?.length">
                    <span class="text-wpx-text-muted text-xs">Devis</span>
                    <p>
                        {{
                            numberFormatter.format(
                                selectedCase.review_case.campaign_version.quotes[0].gross_amount_minor,
                            )
                        }}
                        FCFA — {{ selectedCase.review_case.campaign_version.quotes[0].estimated_events }} événements
                        estimés
                    </p>
                </div>
            </div>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded-wpx-sm bg-wpx-success text-wpx-navy-950 px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
                    :disabled="busy"
                    @click="approve"
                >
                    Approuver
                </button>
                <button
                    type="button"
                    class="rounded-wpx-sm bg-wpx-pending text-wpx-navy-950 px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
                    :disabled="busy"
                    @click="requestChanges"
                >
                    Demander une correction
                </button>
                <button
                    type="button"
                    class="rounded-wpx-sm bg-wpx-danger px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="busy"
                    @click="reject"
                >
                    Rejeter
                </button>
            </div>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Campagnes approuvées</h2>
            <ul class="flex flex-col gap-1">
                <li
                    v-for="campaign in approvedCampaigns"
                    :key="campaign.id"
                    class="flex items-center justify-between text-sm"
                >
                    <span
                        >{{ campaign.brand?.name ?? 'Marque inconnue' }} —
                        {{ campaign.objective_code ?? 'Campagne' }}</span
                    >
                    <button
                        type="button"
                        class="text-wpx-danger-light text-xs hover:underline disabled:opacity-50"
                        :disabled="busy"
                        @click="suspend(campaign)"
                    >
                        Suspendre
                    </button>
                </li>
                <li v-if="approvedCampaigns.length === 0" class="text-wpx-text-muted text-sm">
                    Aucune campagne approuvée.
                </li>
            </ul>
        </div>
    </div>
</template>
