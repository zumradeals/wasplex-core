<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';
import { CAMPAIGN_OBJECTIVES } from '@/lib/campaignObjectives';

interface Brand {
    id: string;
    name: string;
    status: string;
}

interface Asset {
    id: string;
    type: string;
    url: string;
    filename?: string;
}

interface Quote {
    gross_amount_minor: number;
    estimated_events: number;
    estimated_reach_min?: number;
    estimated_reach_max?: number;
    class_breakdown: Record<string, { envelope_minor: number; gain_unitaire_minor: number }>;
}

interface CampaignVersion {
    creative_configuration: { asset_id?: string; title?: string } | null;
    audience_configuration: {
        territory?: { country_code?: string };
        economic_classes?: string[];
        profile_taxonomies?: string[];
    } | null;
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

interface Advertiser {
    id: string;
    organization_id: string;
    advertiser_type: string | null;
    status: string;
}

interface PriceVersion {
    id: string;
    status: string;
    currency: string;
    minimum_budget_minor: number;
    minimum_daily_budget_minor: number;
    minimum_duration_days: number;
    maximum_duration_days: number;
    duration_days: number;
    catalog: { code: string };
}

const queue = ref<ReviewCase[]>([]);
const approvedCampaigns = ref<Array<Campaign & { brand?: Brand }>>([]);
const selectedCase = ref<{ review_case: ReviewCase; brand: Brand | null; asset: Asset | null } | null>(null);
const advertisers = ref<Advertiser[]>([]);
const brands = ref<Brand[]>([]);
const pendingAssets = ref<Asset[]>([]);
const priceVersions = ref<PriceVersion[]>([]);
const loading = ref(true);
const busy = ref(false);
const actionError = ref<string | null>(null);

const showAdvertisers = ref(false);
const showPricing = ref(false);
const showPreparatoryControls = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');

const priceDraftForm = reactive({
    minimum_budget_minor: 2000,
    minimum_daily_budget_minor: 500,
    minimum_duration_days: 1,
    maximum_duration_days: 30,
    duration_days: 7,
});

function initials(name: string): string {
    return name.slice(0, 2).toUpperCase();
}

function timeAgo(dateString: string): string {
    const diffMs = Date.now() - new Date(dateString).getTime();
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    if (hours < 1) return 'à l’instant';
    if (hours < 24) return `il y a ${hours} h`;
    return `il y a ${Math.floor(hours / 24)} j`;
}

async function load(): Promise<void> {
    loading.value = true;
    actionError.value = null;
    try {
        const results = await Promise.allSettled([
            http.get('/admin/campaign-reviews'),
            http.get('/admin/campaigns/approved'),
            http.get('/admin/advertisers'),
            http.get('/admin/brands'),
            http.get('/admin/creative-assets'),
            http.get('/admin/advertising/pricing'),
        ]);
        const [queueRes, approvedRes, advertisersRes, brandsRes, assetsRes, pricingRes] = results;
        if (queueRes.status === 'fulfilled') queue.value = queueRes.value.data.review_cases;
        else actionError.value = 'La file des campagnes soumises ne peut pas être chargée.';
        if (approvedRes.status === 'fulfilled') approvedCampaigns.value = approvedRes.value.data.campaigns;
        if (advertisersRes.status === 'fulfilled') advertisers.value = advertisersRes.value.data.advertisers;
        if (brandsRes.status === 'fulfilled') brands.value = brandsRes.value.data.brands;
        if (assetsRes.status === 'fulfilled') pendingAssets.value = assetsRes.value.data.assets;
        if (pricingRes.status === 'fulfilled') {
            priceVersions.value = pricingRes.value.data.price_versions;
            const draftPrice = priceVersions.value.find((version) => version.status === 'draft');
            if (draftPrice) {
                priceDraftForm.minimum_budget_minor = draftPrice.minimum_budget_minor;
                priceDraftForm.minimum_daily_budget_minor = draftPrice.minimum_daily_budget_minor;
                priceDraftForm.minimum_duration_days = draftPrice.minimum_duration_days;
                priceDraftForm.maximum_duration_days = draftPrice.maximum_duration_days;
                priceDraftForm.duration_days = draftPrice.duration_days;
            }
        }
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

async function verifyAdvertiser(advertiser: Advertiser): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/advertisers/${advertiser.id}/verify`);
        await load();
    } finally {
        busy.value = false;
    }
}

async function restrictAdvertiser(advertiser: Advertiser): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/advertisers/${advertiser.id}/restrict`);
        await load();
    } finally {
        busy.value = false;
    }
}

async function restoreAdvertiser(advertiser: Advertiser): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/advertisers/${advertiser.id}/restore`);
        await load();
    } finally {
        busy.value = false;
    }
}

async function verifyBrand(brand: Brand): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/brands/${brand.id}/verify`);
        await load();
    } finally {
        busy.value = false;
    }
}

async function decideAsset(asset: Asset, decision: string): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/creative-assets/${asset.id}/decide`, { decision });
        await load();
    } finally {
        busy.value = false;
    }
}

async function savePriceDraft(version: PriceVersion): Promise<void> {
    busy.value = true;
    try {
        await http.patch(`/admin/advertising/pricing/${version.id}`, priceDraftForm);
        await load();
    } finally {
        busy.value = false;
    }
}

async function createPriceDraft(): Promise<void> {
    busy.value = true;
    actionError.value = null;
    try {
        const published = priceVersions.value.find((version) => version.status === 'published');
        await http.post('/admin/advertising/pricing', {
            catalog_code: 'standard',
            currency: 'XOF',
            minimum_budget_minor: published?.minimum_budget_minor ?? 2000,
            minimum_daily_budget_minor: published?.minimum_daily_budget_minor ?? 500,
            minimum_duration_days: published?.minimum_duration_days ?? 1,
            maximum_duration_days: published?.maximum_duration_days ?? 30,
            duration_days: published?.duration_days ?? 7,
        });
        await load();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Création de la configuration impossible.';
    } finally {
        busy.value = false;
    }
}

async function publishPrice(version: PriceVersion): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/advertising/pricing/${version.id}/publish`);
        await load();
    } finally {
        busy.value = false;
    }
}

function statusClasses(status: string): string {
    if (['verified', 'active', 'ready', 'approved', 'published'].includes(status)) {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (['rejected', 'restricted', 'suspended', 'needs_changes'].includes(status)) {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }
    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="flex gap-5">
        <div class="flex w-64 shrink-0 flex-col gap-3.5">
            <p class="text-wpx-text-muted text-[11px] font-extrabold tracking-wide uppercase">
                À examiner ({{ queue.length }})
            </p>
            <button
                v-for="reviewCase in queue"
                :key="reviewCase.id"
                type="button"
                class="rounded-wpx-md shadow-wpx-card border-[1.5px] bg-white p-3 text-left"
                :class="selectedCase?.review_case.id === reviewCase.id ? 'border-wpx-blue-light' : 'border-wpx-border'"
                @click="openCase(reviewCase)"
            >
                <span class="flex items-center gap-2.5">
                    <span
                        class="from-wpx-blue to-wpx-cyan flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-xs font-extrabold text-white"
                    >
                        {{ initials(reviewCase.brand?.name ?? '??') }}
                    </span>
                    <span class="text-wpx-text text-[13px] font-bold">{{
                        reviewCase.brand?.name ?? 'Marque inconnue'
                    }}</span>
                </span>
                <span class="text-wpx-text-muted mt-1.5 block text-[11px]">
                    {{
                        reviewCase.campaign.objective_code
                            ? CAMPAIGN_OBJECTIVES[reviewCase.campaign.objective_code]
                            : 'Campagne'
                    }}
                </span>
                <span class="text-wpx-text-muted mt-0.5 block text-[10px]"
                    >Déposée {{ timeAgo(reviewCase.opened_at) }}</span
                >
            </button>
            <p v-if="!loading && queue.length === 0" class="text-wpx-text-muted text-sm">Aucune campagne en attente.</p>

            <p class="text-wpx-text-muted mt-2.5 text-[11px] font-extrabold tracking-wide uppercase">
                Campagnes actives
            </p>
            <div
                v-for="campaign in approvedCampaigns"
                :key="campaign.id"
                class="rounded-wpx-md shadow-wpx-card border-wpx-border flex items-center justify-between border bg-white p-3"
            >
                <span class="text-wpx-text text-xs font-bold">
                    {{ campaign.brand?.name ?? 'Marque inconnue' }} —
                    {{ campaign.objective_code ? CAMPAIGN_OBJECTIVES[campaign.objective_code] : 'Campagne' }}
                </span>
                <button
                    type="button"
                    class="text-wpx-danger-light text-[11px] font-bold whitespace-nowrap"
                    @click="suspend(campaign)"
                >
                    Suspendre
                </button>
            </div>
            <p v-if="approvedCampaigns.length === 0" class="text-wpx-text-muted text-sm">Aucune campagne active.</p>
        </div>

        <div class="min-w-0 flex-1 space-y-5">
            <p v-if="actionError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-sm p-2 text-xs">
                {{ actionError }}
            </p>

            <div
                v-if="selectedCase"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border overflow-hidden border bg-white"
            >
                <div class="border-wpx-border flex items-center gap-3 border-b p-4.5">
                    <span
                        class="from-wpx-blue to-wpx-cyan flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-extrabold text-white"
                    >
                        {{ initials(selectedCase.brand?.name ?? '??') }}
                    </span>
                    <span>
                        <span class="text-wpx-text block text-[15px] font-bold">
                            {{ selectedCase.brand?.name ?? 'Marque inconnue' }} —
                            {{
                                selectedCase.review_case.campaign.objective_code
                                    ? CAMPAIGN_OBJECTIVES[selectedCase.review_case.campaign.objective_code]
                                    : 'Campagne'
                            }}
                        </span>
                        <span
                            v-if="selectedCase.review_case.campaign_version?.quotes?.length"
                            class="text-wpx-text-muted block text-xs"
                        >
                            Budget
                            {{
                                numberFormatter.format(
                                    selectedCase.review_case.campaign_version.quotes[0].gross_amount_minor,
                                )
                            }}
                            WP
                        </span>
                    </span>
                </div>

                <div class="grid grid-cols-[220px_1fr]">
                    <div class="border-wpx-border border-r p-5">
                        <div
                            class="bg-wpx-navy-950 rounded-wpx-md relative flex h-64 items-center justify-center overflow-hidden"
                        >
                            <video
                                v-if="selectedCase.asset?.type === 'video'"
                                :src="selectedCase.asset.url"
                                class="h-full w-full object-cover"
                                controls
                            />
                            <img
                                v-else-if="selectedCase.asset"
                                :src="selectedCase.asset.url"
                                alt=""
                                class="h-full w-full object-cover"
                            />
                            <span v-else class="text-wpx-muted-dark px-4 text-center font-mono text-[11px]"
                                >média de la campagne · 9:16</span
                            >
                        </div>
                    </div>
                    <div class="flex flex-col gap-3.5 p-5">
                        <div class="grid grid-cols-2 gap-3.5">
                            <div>
                                <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                    Audience visée
                                </p>
                                <p class="text-wpx-text mt-1 text-[13px] font-bold">
                                    <template v-if="selectedCase.review_case.campaign_version?.audience_configuration">
                                        {{
                                            selectedCase.review_case.campaign_version.audience_configuration.economic_classes?.join(
                                                ', ',
                                            )
                                        }}
                                        <template
                                            v-if="
                                                selectedCase.review_case.campaign_version.audience_configuration
                                                    .territory
                                            "
                                        >
                                            ·
                                            {{
                                                selectedCase.review_case.campaign_version.audience_configuration
                                                    .territory.country_code
                                            }}
                                        </template>
                                        <template
                                            v-if="
                                                selectedCase.review_case.campaign_version.audience_configuration
                                                    .profile_taxonomies?.length
                                            "
                                        >
                                            ·
                                            {{
                                                selectedCase.review_case.campaign_version.audience_configuration.profile_taxonomies.join(
                                                    ', ',
                                                )
                                            }}
                                        </template>
                                    </template>
                                    <template v-else>—</template>
                                </p>
                            </div>
                            <div>
                                <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                    Budget & portée estimée
                                </p>
                                <p class="text-wpx-text mt-1 text-[13px] font-bold">
                                    <template v-if="selectedCase.review_case.campaign_version?.quotes?.length">
                                        {{
                                            numberFormatter.format(
                                                selectedCase.review_case.campaign_version.quotes[0].gross_amount_minor,
                                            )
                                        }}
                                        WP ·
                                        {{ selectedCase.review_case.campaign_version.quotes[0].estimated_events }}
                                        événements
                                    </template>
                                    <template v-else>—</template>
                                </p>
                            </div>
                        </div>
                        <div class="bg-wpx-canvas rounded-wpx-sm p-3.5">
                            <p class="text-wpx-text-muted mb-1 text-[11px] font-bold tracking-wide uppercase">
                                Message affiché
                            </p>
                            <p class="text-wpx-text text-[13px]">
                                «
                                {{ selectedCase.review_case.campaign_version?.creative_configuration?.title ?? '—' }} »
                            </p>
                        </div>
                        <div class="mt-auto flex gap-2.5 pt-1.5">
                            <button
                                type="button"
                                class="rounded-wpx-md bg-wpx-success text-wpx-navy-950 px-5 py-2.5 text-[13px] font-bold disabled:opacity-50"
                                :disabled="busy"
                                @click="approve"
                            >
                                Approuver et diffuser
                            </button>
                            <button
                                type="button"
                                class="rounded-wpx-md border-wpx-warning-light text-wpx-warning-light border px-5 py-2.5 text-[13px] font-bold disabled:opacity-50"
                                :disabled="busy"
                                @click="requestChanges"
                            >
                                Demander une correction
                            </button>
                            <button
                                type="button"
                                class="rounded-wpx-md border-wpx-danger-light text-wpx-danger-light ml-auto border px-5 py-2.5 text-[13px] font-bold disabled:opacity-50"
                                :disabled="busy"
                                @click="reject"
                            >
                                Rejeter
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border text-wpx-text-muted flex h-40 items-center justify-center border bg-white text-sm"
            >
                Choisis une campagne à examiner à gauche.
            </div>

            <div class="rounded-wpx-md bg-wpx-blue/5 border-wpx-blue/20 border p-4 text-sm">
                <p class="text-wpx-text font-bold">Une seule approbation pour la campagne</p>
                <p class="text-wpx-text-muted mt-1 text-xs">
                    Quand tu approuves une campagne financée, Wasplex valide aussi son compte annonceur, sa marque et le
                    média utilisé. Les contrôles séparés ci-dessous restent disponibles seulement en cas de besoin.
                </p>
            </div>

            <button
                type="button"
                class="text-wpx-blue-light block self-start text-xs font-bold"
                @click="showPreparatoryControls = !showPreparatoryControls"
            >
                {{ showPreparatoryControls ? 'Masquer les contrôles séparés' : 'Afficher les contrôles séparés' }}
            </button>

            <div
                v-if="showPreparatoryControls"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border overflow-hidden border bg-white"
            >
                <p class="text-wpx-text border-wpx-border border-b p-4.5 text-sm font-bold">Marques à vérifier</p>
                <div
                    v-for="brand in brands.filter((b) => b.status !== 'verified')"
                    :key="brand.id"
                    class="border-wpx-border/60 flex items-center gap-3 border-b p-3.5 last:border-0"
                >
                    <span
                        class="from-wpx-orange to-wpx-gold flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br text-xs font-extrabold text-white"
                    >
                        {{ initials(brand.name) }}
                    </span>
                    <span class="text-wpx-text flex-1 text-[13px] font-bold">{{ brand.name }}</span>
                    <span
                        class="rounded-wpx-full px-2.5 py-0.5 text-[11px] font-bold"
                        :class="statusClasses(brand.status)"
                    >
                        {{ brand.status === 'draft' ? 'À vérifier' : brand.status }}
                    </span>
                    <button
                        type="button"
                        class="rounded-wpx-sm bg-wpx-navy-950 px-3.5 py-1.5 text-xs font-bold text-white disabled:opacity-50"
                        :disabled="busy"
                        @click="verifyBrand(brand)"
                    >
                        Vérifier
                    </button>
                </div>
                <p
                    v-if="brands.filter((b) => b.status !== 'verified').length === 0"
                    class="text-wpx-text-muted p-4 text-sm"
                >
                    Toutes les marques sont vérifiées.
                </p>
            </div>

            <div
                v-if="showPreparatoryControls"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border overflow-hidden border bg-white"
            >
                <p class="text-wpx-text border-wpx-border border-b p-4.5 text-sm font-bold">
                    Médias en attente de modération
                </p>
                <div
                    v-for="asset in pendingAssets"
                    :key="asset.id"
                    class="border-wpx-border/60 flex items-center justify-between border-b p-3.5 last:border-0"
                >
                    <span class="text-wpx-text text-[13px] font-bold">{{ asset.filename }} ({{ asset.type }})</span>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="text-wpx-success-light text-xs font-bold disabled:opacity-50"
                            :disabled="busy"
                            @click="decideAsset(asset, 'approve')"
                        >
                            Approuver
                        </button>
                        <button
                            type="button"
                            class="text-wpx-warning-light text-xs font-bold disabled:opacity-50"
                            :disabled="busy"
                            @click="decideAsset(asset, 'request_changes')"
                        >
                            Corrections
                        </button>
                        <button
                            type="button"
                            class="text-wpx-danger-light text-xs font-bold disabled:opacity-50"
                            :disabled="busy"
                            @click="decideAsset(asset, 'reject')"
                        >
                            Rejeter
                        </button>
                    </div>
                </div>
                <p v-if="pendingAssets.length === 0" class="text-wpx-text-muted p-4 text-sm">Rien en attente.</p>
            </div>

            <button
                type="button"
                class="text-wpx-blue-light block self-start text-xs font-bold"
                @click="showAdvertisers = !showAdvertisers"
            >
                {{ showAdvertisers ? 'Masquer les comptes annonceurs' : 'Voir les comptes annonceurs' }}
            </button>
            <div
                v-if="showAdvertisers"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border overflow-hidden border bg-white"
            >
                <p class="text-wpx-text border-wpx-border border-b p-4.5 text-sm font-bold">Comptes annonceurs</p>
                <div
                    v-for="advertiser in advertisers"
                    :key="advertiser.id"
                    class="border-wpx-border/60 flex items-center gap-3 border-b p-3.5 last:border-0"
                >
                    <span class="text-wpx-text-muted flex-1 font-mono text-xs">{{ advertiser.organization_id }}</span>
                    <span class="text-wpx-text-muted text-xs">{{ advertiser.advertiser_type ?? '—' }}</span>
                    <span
                        class="rounded-wpx-full px-2.5 py-0.5 text-[11px] font-bold"
                        :class="statusClasses(advertiser.status)"
                    >
                        {{ advertiser.status }}
                    </span>
                    <button
                        v-if="advertiser.status !== 'verified' && advertiser.status !== 'active'"
                        type="button"
                        class="text-wpx-success-light text-xs font-bold disabled:opacity-50"
                        :disabled="busy"
                        @click="verifyAdvertiser(advertiser)"
                    >
                        Vérifier
                    </button>
                    <button
                        v-if="advertiser.status === 'restricted'"
                        type="button"
                        class="text-wpx-success-light text-xs font-bold disabled:opacity-50"
                        :disabled="busy"
                        @click="restoreAdvertiser(advertiser)"
                    >
                        Restaurer
                    </button>
                    <button
                        v-if="advertiser.status !== 'restricted'"
                        type="button"
                        class="text-wpx-danger-light text-xs font-bold disabled:opacity-50"
                        :disabled="busy"
                        @click="restrictAdvertiser(advertiser)"
                    >
                        Restreindre
                    </button>
                </div>
                <p v-if="advertisers.length === 0" class="text-wpx-text-muted p-4 text-sm">Aucun compte annonceur.</p>
            </div>

            <button
                type="button"
                class="text-wpx-blue-light block self-start text-xs font-bold"
                @click="showPricing = !showPricing"
            >
                {{ showPricing ? 'Masquer les tarifs publicitaires' : 'Voir les tarifs publicitaires' }}
            </button>
            <div v-if="showPricing" class="rounded-wpx-lg shadow-wpx-card border-wpx-border border bg-white p-4.5">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-wpx-text text-sm font-bold">Règles publicitaires</p>
                    <button
                        v-if="!priceVersions.some((version) => version.status === 'draft')"
                        type="button"
                        class="text-wpx-blue-light text-xs font-bold"
                        :disabled="busy"
                        @click="createPriceDraft"
                    >
                        Modifier les règles
                    </button>
                </div>
                <p class="text-wpx-text-muted mt-1 mb-3 text-xs">
                    Ces limites protègent la qualité des campagnes sans imposer leur durée. L’annonceur choisit
                    librement ses jours de diffusion. Le budget reste partagé automatiquement : 50 % Wasplex, 50 %
                    récompenses utilisateurs.
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1050px] text-sm">
                        <thead>
                            <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                                <th class="p-2">Configuration</th>
                                <th class="p-2">Budget minimum</th>
                                <th class="p-2">Minimum/jour</th>
                                <th class="p-2">Durée min.</th>
                                <th class="p-2">Durée max.</th>
                                <th class="p-2">Durée proposée</th>
                                <th class="p-2">Statut</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="v in priceVersions" :key="v.id" class="border-wpx-border text-wpx-text border-b">
                                <td class="p-2">{{ v.catalog.code }}</td>
                                <td class="p-2">
                                    <input
                                        v-if="v.status === 'draft'"
                                        v-model.number="priceDraftForm.minimum_budget_minor"
                                        type="number"
                                        class="rounded-wpx-sm border-wpx-border w-28 border px-1 py-0.5 text-xs"
                                    />
                                    <span v-else>{{ numberFormatter.format(v.minimum_budget_minor) }} FCFA</span>
                                </td>
                                <td class="p-2">
                                    <input
                                        v-if="v.status === 'draft'"
                                        v-model.number="priceDraftForm.minimum_daily_budget_minor"
                                        type="number"
                                        class="rounded-wpx-sm border-wpx-border w-24 border px-1 py-0.5 text-xs"
                                    />
                                    <span v-else>{{ numberFormatter.format(v.minimum_daily_budget_minor) }} FCFA</span>
                                </td>
                                <td class="p-2">
                                    <input
                                        v-if="v.status === 'draft'"
                                        v-model.number="priceDraftForm.minimum_duration_days"
                                        type="number"
                                        class="rounded-wpx-sm border-wpx-border w-16 border px-1 py-0.5 text-xs"
                                    />
                                    <span v-else>{{ v.minimum_duration_days }} j</span>
                                </td>
                                <td class="p-2">
                                    <input
                                        v-if="v.status === 'draft'"
                                        v-model.number="priceDraftForm.maximum_duration_days"
                                        type="number"
                                        class="rounded-wpx-sm border-wpx-border w-16 border px-1 py-0.5 text-xs"
                                    />
                                    <span v-else>{{ v.maximum_duration_days }} j</span>
                                </td>
                                <td class="p-2">
                                    <input
                                        v-if="v.status === 'draft'"
                                        v-model.number="priceDraftForm.duration_days"
                                        type="number"
                                        class="rounded-wpx-sm border-wpx-border w-20 border px-1 py-0.5 text-xs"
                                    />
                                    <span v-else>{{ v.duration_days }} jours</span>
                                </td>
                                <td class="p-2">
                                    <span
                                        class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                        :class="statusClasses(v.status)"
                                        >{{ v.status }}</span
                                    >
                                </td>
                                <td class="p-2 text-right whitespace-nowrap">
                                    <button
                                        v-if="v.status === 'draft'"
                                        class="text-wpx-blue-light mr-2 text-xs hover:underline disabled:opacity-50"
                                        :disabled="busy"
                                        @click="savePriceDraft(v)"
                                    >
                                        Enregistrer
                                    </button>
                                    <button
                                        v-if="v.status === 'draft'"
                                        class="text-wpx-success-light text-xs hover:underline disabled:opacity-50"
                                        :disabled="busy"
                                        @click="publishPrice(v)"
                                    >
                                        Publier
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
