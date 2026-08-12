<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import { CAMPAIGN_OBJECTIVES } from '@/lib/campaignObjectives';
import { TARGET_COUNTRIES } from '@/lib/countries';
import CampaignPerformancePanel from '@/Components/CampaignPerformancePanel.vue';
import CampaignPreviewPhone from '@/Components/CampaignPreviewPhone.vue';

const emit = defineEmits<{ navigateWallet: [] }>();

interface Brand {
    id: string;
    name: string;
    status: string;
}

interface Asset {
    id: string;
    type: string;
    filename: string;
    moderation_status?: string;
    url?: string;
    duration?: number | null;
    duration_ms?: number | null;
}

interface ProfileCriterion {
    code: string;
    label: string;
}

interface QuoteClassBreakdown {
    envelope_minor: number;
    events: number;
    gain_unitaire_minor: number;
}

interface Quote {
    id: string;
    gross_amount_minor: number;
    net_distributable_amount_minor: number;
    estimated_events: number;
    estimated_reach_min: number;
    estimated_reach_max: number;
    class_breakdown: Record<string, QuoteClassBreakdown>;
    expires_at: string;
    status: string;
    price_version?: {
        duration_days: number;
        minimum_budget_minor: number;
        minimum_daily_budget_minor: number;
        minimum_duration_days: number;
        maximum_duration_days: number;
    };
}

interface CampaignVersion {
    version_number: number;
    creative_configuration: Record<string, unknown> | null;
    audience_configuration: {
        territory?: { country_code?: string };
        profile_taxonomies?: string[];
    } | null;
    budget_configuration: { budget_amount_minor?: number; daily_budget_minor?: number; duration_days?: number } | null;
    status: string;
    quotes?: Quote[];
}

interface ReviewCase {
    status: string;
    decision: string | null;
    reason: string | null;
    opened_at: string;
}

interface Campaign {
    id: string;
    brand_id: string;
    objective_code: string | null;
    status: string;
    versions: CampaignVersion[];
    review_cases?: ReviewCase[];
}

interface AdvertisingRules {
    minimum_budget_minor: number;
    minimum_daily_budget_minor: number;
    minimum_duration_days: number;
    maximum_duration_days: number;
    duration_days: number;
}

const OBJECTIVES = CAMPAIGN_OBJECTIVES;
const STEPS = ['Ma publicité', 'Audience & budget', 'Envoi'] as const;
const DEFAULT_DAILY_BUDGETS = [500, 1000, 2000, 5000, 10000] as const;
const DEFAULT_DURATIONS = [3, 7, 14, 30] as const;
const SAVE_TIMEOUT_MS = 12000;

const campaigns = ref<Campaign[]>([]);
const brands = ref<Brand[]>([]);
const assets = ref<Asset[]>([]);
const profileCriteria = ref<Record<string, ProfileCriterion[]>>({});
const advertisingRules = ref<AdvertisingRules>({
    minimum_budget_minor: 2000,
    minimum_daily_budget_minor: 500,
    minimum_duration_days: 1,
    maximum_duration_days: 30,
    duration_days: 7,
});
const selectedCampaignId = ref<string | null>(null);
const step = ref(0);
const view = ref<'create' | 'history'>('create');
const loading = ref(true);
const loadError = ref<string | null>(null);
const actionError = ref<string | null>(null);
const estimating = ref(false);
const estimate = ref<{ estimated_min: number; estimated_max: number; too_small: boolean } | null>(null);
const sending = ref(false);
const resubmitting = ref(false);
const preparingStep = ref(false);
const walletAvailableMinor = ref(0);
const walletShortfallMinor = ref<number | null>(null);
const showAdvancedTargeting = ref(false);
const showBrandCreator = ref(false);
const newBrandName = ref('');
const creatingBrand = ref(false);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);

const form = reactive({
    brand_id: '',
    objective_code: '' as string | null,
    asset_id: '' as string | null,
    title: '',
    country_code: '',
    profile_taxonomies: [] as string[],
    daily_budget_minor: 500,
    duration_days: 7,
});

const numberFormatter = new Intl.NumberFormat('fr-FR');

const totalBudgetMinor = computed(
    () => Math.max(0, Number(form.daily_budget_minor) || 0) * Math.max(0, Number(form.duration_days) || 0),
);
const hasEnoughWallet = computed(() => walletAvailableMinor.value >= totalBudgetMinor.value);
const budgetOptions = computed(() =>
    [...new Set([advertisingRules.value.minimum_daily_budget_minor, ...DEFAULT_DAILY_BUDGETS])]
        .filter((amount) => amount >= advertisingRules.value.minimum_daily_budget_minor)
        .sort((a, b) => a - b),
);
const durationOptions = computed(() =>
    [...new Set([advertisingRules.value.duration_days, ...DEFAULT_DURATIONS])]
        .filter(
            (days) =>
                days >= advertisingRules.value.minimum_duration_days &&
                days <= advertisingRules.value.maximum_duration_days,
        )
        .sort((a, b) => a - b),
);
const canContinue = computed(() => {
    if (step.value === 0) return Boolean(form.brand_id && form.objective_code && form.asset_id);
    if (step.value === 1) {
        return (
            form.daily_budget_minor >= advertisingRules.value.minimum_daily_budget_minor &&
            form.duration_days >= advertisingRules.value.minimum_duration_days &&
            form.duration_days <= advertisingRules.value.maximum_duration_days &&
            totalBudgetMinor.value >= advertisingRules.value.minimum_budget_minor
        );
    }
    return false;
});
const budgetValidationMessage = computed(() => {
    if (step.value !== 1) return null;
    if (form.daily_budget_minor < advertisingRules.value.minimum_daily_budget_minor) {
        return `Le minimum par jour est de ${numberFormatter.format(advertisingRules.value.minimum_daily_budget_minor)} FCFA.`;
    }
    if (form.duration_days < advertisingRules.value.minimum_duration_days) {
        return `La durée minimum est de ${advertisingRules.value.minimum_duration_days} jour(s).`;
    }
    if (form.duration_days > advertisingRules.value.maximum_duration_days) {
        return `La durée maximum est de ${advertisingRules.value.maximum_duration_days} jours.`;
    }
    if (totalBudgetMinor.value < advertisingRules.value.minimum_budget_minor) {
        const missing = advertisingRules.value.minimum_budget_minor - totalBudgetMinor.value;
        return `Budget minimum : ${numberFormatter.format(advertisingRules.value.minimum_budget_minor)} FCFA. Ajoute encore ${numberFormatter.format(missing)} FCFA.`;
    }
    return null;
});

const selectedCampaign = computed(
    () => campaigns.value.find((campaign) => campaign.id === selectedCampaignId.value) ?? null,
);
const currentVersion = computed<CampaignVersion | null>(() => {
    const campaign = selectedCampaign.value;
    if (!campaign || campaign.versions.length === 0) return null;
    return [...campaign.versions].sort((a, b) => b.version_number - a.version_number)[0];
});
const latestQuote = computed<Quote | null>(() => {
    const version = currentVersion.value;
    if (!version?.quotes || version.quotes.length === 0) return null;
    return version.quotes[version.quotes.length - 1];
});
const latestReviewCase = computed<ReviewCase | null>(() => {
    const cases = selectedCampaign.value?.review_cases ?? [];
    return cases.length > 0 ? cases[0] : null;
});
const selectedAsset = computed(() => assets.value.find((asset) => asset.id === form.asset_id) ?? null);
const selectedBrand = computed(() => brands.value.find((brand) => brand.id === form.brand_id) ?? null);
const selectedVideoDurationMs = computed(() => {
    const asset = selectedAsset.value;
    if (!asset) return 0;
    return asset.duration_ms ?? (asset.duration ? asset.duration * 1000 : 0);
});
const selectedVideoUnits = computed(() =>
    selectedVideoDurationMs.value > 0 ? Math.ceil(selectedVideoDurationMs.value / 15000) : 0,
);
function formatVideoDuration(ms: number): string {
    const seconds = Math.max(1, Math.ceil(ms / 1000));
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;
    return minutes > 0 ? `${minutes} min ${rest.toString().padStart(2, '0')} s` : `${rest} s`;
}
const ctaLabel = computed(() => (form.objective_code ? OBJECTIVES[form.objective_code] : null));
const gainLabel = computed(() => {
    const quote = latestQuote.value;
    if (!quote) return null;
    const gains = Object.values(quote.class_breakdown ?? {})
        .map((breakdown) => breakdown.gain_unitaire_minor)
        .filter((value): value is number => typeof value === 'number');
    if (gains.length === 0) return null;
    return `+${Math.min(...gains)} WP`;
});
const isReadOnlyCampaign = computed(() =>
    ['submitted', 'approved', 'rejected', 'suspended'].includes(selectedCampaign.value?.status ?? ''),
);

async function loadAssetsForBrand(brandId: string): Promise<void> {
    if (!brandId) {
        assets.value = [];
        return;
    }
    const { data } = await http.get(`/advertiser/assets?brand_id=${brandId}`);
    assets.value = (data.assets as Asset[]).filter((asset) => asset.type === 'video');
}

async function loadAll(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [campaignsRes, brandsRes, criteriaRes, rulesRes, walletRes] = await Promise.all([
            http.get('/advertiser/campaigns'),
            http.get('/advertiser/brands'),
            http.get('/advertiser/targeting/profile-criteria'),
            http.get('/advertiser/advertising-rules'),
            http.get('/advertiser/wallet'),
        ]);
        campaigns.value = campaignsRes.data.campaigns;
        brands.value = brandsRes.data.brands;
        profileCriteria.value = criteriaRes.data.profile_criteria;
        if (rulesRes.data.advertising_rules) advertisingRules.value = rulesRes.data.advertising_rules;
        walletAvailableMinor.value = walletRes.data.wallet.available_minor;
        form.daily_budget_minor = advertisingRules.value.minimum_daily_budget_minor;
        form.duration_days = advertisingRules.value.duration_days;

        if (brands.value.length === 1) {
            form.brand_id = brands.value[0].id;
            await loadAssetsForBrand(form.brand_id);
        }
    } catch (error) {
        const message = (error as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Le Studio publicitaire est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function hydrateFormFromCampaign(campaign: Campaign): void {
    const version = [...campaign.versions].sort((a, b) => b.version_number - a.version_number)[0];
    form.brand_id = campaign.brand_id;
    form.objective_code = campaign.objective_code;
    form.asset_id = (version?.creative_configuration?.asset_id as string) ?? null;
    form.title = (version?.creative_configuration?.title as string) ?? '';
    form.country_code = version?.audience_configuration?.territory?.country_code ?? '';
    form.profile_taxonomies = version?.audience_configuration?.profile_taxonomies ?? [];
    form.duration_days = version?.budget_configuration?.duration_days ?? advertisingRules.value.duration_days;
    form.daily_budget_minor =
        version?.budget_configuration?.daily_budget_minor ??
        Math.max(
            advertisingRules.value.minimum_daily_budget_minor,
            Math.floor((version?.budget_configuration?.budget_amount_minor ?? 0) / Math.max(1, form.duration_days)),
        );
    estimate.value = null;
    showAdvancedTargeting.value = form.profile_taxonomies.length > 0;
}

async function resetNewCampaign(): Promise<void> {
    cancelPendingAutosave();
    selectedCampaignId.value = null;
    step.value = 0;
    view.value = 'create';
    actionError.value = null;
    estimate.value = null;
    walletShortfallMinor.value = null;
    showAdvancedTargeting.value = false;
    form.brand_id = brands.value.length === 1 ? brands.value[0].id : '';
    form.objective_code = '';
    form.asset_id = '';
    form.title = '';
    form.country_code = '';
    form.profile_taxonomies = [];
    form.daily_budget_minor = advertisingRules.value.minimum_daily_budget_minor;
    form.duration_days = advertisingRules.value.duration_days;
    await loadAssetsForBrand(form.brand_id);
}

async function selectBrandForNewCampaign(brandId: string): Promise<void> {
    if (selectedCampaignId.value) return;
    form.brand_id = brandId;
    form.asset_id = '';
    actionError.value = null;
    await loadAssetsForBrand(brandId);
}

async function createBrandInline(): Promise<void> {
    const name = newBrandName.value.trim();
    if (!name) return;
    creatingBrand.value = true;
    actionError.value = null;
    try {
        const { data } = await http.post('/advertiser/brands', { name });
        brands.value = [data.brand, ...brands.value];
        newBrandName.value = '';
        showBrandCreator.value = false;
        await selectBrandForNewCampaign(data.brand.id);
    } catch (error) {
        actionError.value =
            (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "Impossible de créer l'activité pour le moment.";
    } finally {
        creatingBrand.value = false;
    }
}

async function selectCampaign(campaign: Campaign): Promise<void> {
    cancelPendingAutosave();
    selectedCampaignId.value = campaign.id;
    step.value = 0;
    view.value = 'create';
    actionError.value = null;
    hydrateFormFromCampaign(campaign);
    await loadAssetsForBrand(campaign.brand_id);
}

type CampaignRequestError = {
    code?: string;
    message?: string;
    response?: { data?: { message?: string; missing_minor?: number } };
};

function campaignRequestMessage(error: unknown, fallback: string): string {
    const requestError = error as CampaignRequestError;
    if (['ECONNABORTED', 'ETIMEDOUT'].includes(requestError.code ?? '')) {
        return 'Connexion trop lente. Tes informations restent à l’écran. Réessaie.';
    }
    if (requestError.code === 'ERR_NETWORK' || (!requestError.response && requestError.message === 'Network Error')) {
        return 'Connexion interrompue. Vérifie ton réseau puis réessaie.';
    }
    return requestError.response?.data?.message ?? fallback;
}

function applyCampaign(campaign: Campaign): void {
    const index = campaigns.value.findIndex((item) => item.id === campaign.id);
    if (index === -1) campaigns.value = [campaign, ...campaigns.value];
    else campaigns.value[index] = campaign;
}

function draftPayload(): Record<string, unknown> {
    return {
        objective_code: form.objective_code || null,
        creative_configuration: form.asset_id ? { asset_id: form.asset_id, title: form.title } : undefined,
        audience_configuration: {
            ...(form.profile_taxonomies.length > 0 ? { profile_taxonomies: form.profile_taxonomies } : {}),
            ...(form.country_code ? { territory: { country_code: form.country_code.toUpperCase() } } : {}),
        },
        budget_configuration: form.daily_budget_minor
            ? {
                  daily_budget_minor: form.daily_budget_minor,
                  duration_days: form.duration_days,
                  budget_amount_minor: totalBudgetMinor.value,
              }
            : undefined,
    };
}

async function ensureDraftCampaign(): Promise<void> {
    if (selectedCampaignId.value) return;
    const { data } = await http.post(
        '/advertiser/campaigns',
        { brand_id: form.brand_id },
        { timeout: SAVE_TIMEOUT_MS },
    );
    applyCampaign(data.campaign);
    selectedCampaignId.value = data.campaign.id;
}

async function refreshSelected(): Promise<void> {
    if (!selectedCampaignId.value) return;
    const { data } = await http.get(`/advertiser/campaigns/${selectedCampaignId.value}`, {
        timeout: SAVE_TIMEOUT_MS,
    });
    applyCampaign(data.campaign);
}

let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
let autosaveController: AbortController | null = null;

function cancelPendingAutosave(): void {
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = null;
    if (autosaveController) autosaveController.abort();
    autosaveController = null;
}

async function persistDraft(signal?: AbortSignal): Promise<void> {
    if (!selectedCampaignId.value || isReadOnlyCampaign.value) return;
    const { data } = await http.patch(`/advertiser/campaigns/${selectedCampaignId.value}`, draftPayload(), {
        timeout: SAVE_TIMEOUT_MS,
        signal,
    });
    applyCampaign(data.campaign);
}

function scheduleAutosave(): void {
    if (!selectedCampaignId.value || isReadOnlyCampaign.value || preparingStep.value || sending.value) return;
    if (autosaveTimer) clearTimeout(autosaveTimer);
    autosaveTimer = setTimeout(() => {
        autosaveTimer = null;
        const controller = new AbortController();
        if (autosaveController) autosaveController.abort();
        autosaveController = controller;
        void persistDraft(controller.signal)
            .then(() => {
                actionError.value = null;
            })
            .catch((error: unknown) => {
                if ((error as CampaignRequestError).code === 'ERR_CANCELED') return;
                actionError.value = campaignRequestMessage(error, "Échec de l'enregistrement automatique.");
            })
            .finally(() => {
                if (autosaveController === controller) autosaveController = null;
            });
    }, 550);
}

async function saveBeforeAction(): Promise<boolean> {
    cancelPendingAutosave();
    try {
        await persistDraft();
        return true;
    } catch (error) {
        actionError.value = campaignRequestMessage(error, "Impossible d'enregistrer les modifications.");
        return false;
    }
}

watch(
    () => [
        form.objective_code,
        form.asset_id,
        form.title,
        form.country_code,
        [...form.profile_taxonomies],
        form.daily_budget_minor,
        form.duration_days,
    ],
    () => scheduleAutosave(),
);

function toggleProfileCriterion(code: string): void {
    const index = form.profile_taxonomies.indexOf(code);
    if (index === -1) form.profile_taxonomies.push(code);
    else form.profile_taxonomies.splice(index, 1);
    estimate.value = null;
}

async function uploadFile(file: File | undefined): Promise<void> {
    if (!file) return;
    if (!file.type.startsWith('video/')) {
        actionError.value = 'Wasplex V1 accepte uniquement une vidéo publicitaire.';
        return;
    }
    if (!form.brand_id) {
        actionError.value = "Choisis d'abord l'activité que tu veux promouvoir.";
        return;
    }
    uploading.value = true;
    actionError.value = null;
    try {
        const payload = new FormData();
        payload.append('brand_id', form.brand_id);
        payload.append('file', file);
        const { data } = await http.post('/advertiser/assets', payload, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        assets.value = [data.asset, ...assets.value];
        form.asset_id = data.asset.id;
    } catch (error) {
        actionError.value =
            (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "Le visuel n'a pas pu être envoyé.";
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
}

function onFileInputChange(event: Event): void {
    void uploadFile((event.target as HTMLInputElement).files?.[0]);
}

function onDrop(event: DragEvent): void {
    dragOver.value = false;
    void uploadFile(event.dataTransfer?.files?.[0]);
}

async function runEstimate(): Promise<void> {
    if (!selectedCampaignId.value) return;
    estimating.value = true;
    actionError.value = null;
    try {
        if (!(await saveBeforeAction())) return;
        const { data } = await http.post(
            `/advertiser/campaigns/${selectedCampaignId.value}/estimate-audience`,
            undefined,
            { timeout: SAVE_TIMEOUT_MS },
        );
        estimate.value = data.estimate;
    } catch (error) {
        actionError.value =
            (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Estimation impossible.';
    } finally {
        estimating.value = false;
    }
}

async function nextStep(): Promise<void> {
    if (!canContinue.value || preparingStep.value) return;
    preparingStep.value = true;
    actionError.value = null;
    cancelPendingAutosave();
    try {
        if (step.value === 0) {
            await ensureDraftCampaign();
            if (!(await saveBeforeAction())) return;
            step.value = 1;
            return;
        }
        if (step.value === 1) {
            if (!(await saveBeforeAction())) return;
            step.value = 2;
        }
    } catch (error) {
        actionError.value = campaignRequestMessage(error, 'Impossible de passer à l’étape suivante.');
    } finally {
        preparingStep.value = false;
    }
}

async function financeAndSubmit(): Promise<void> {
    if (!selectedCampaignId.value) return;
    if (!hasEnoughWallet.value) {
        walletShortfallMinor.value = totalBudgetMinor.value - walletAvailableMinor.value;
        return;
    }
    sending.value = true;
    actionError.value = null;
    walletShortfallMinor.value = null;
    try {
        if (!(await saveBeforeAction())) return;
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/finance-and-submit`, undefined, {
            timeout: SAVE_TIMEOUT_MS,
        });
        await refreshSelected();
        walletAvailableMinor.value = Math.max(0, walletAvailableMinor.value - totalBudgetMinor.value);
    } catch (error) {
        const data = (error as CampaignRequestError).response?.data;
        actionError.value = campaignRequestMessage(error, 'La publicité ne peut pas encore être envoyée.');
        walletShortfallMinor.value = data?.missing_minor ?? null;
    } finally {
        sending.value = false;
    }
}

async function runResubmit(): Promise<void> {
    if (!selectedCampaignId.value) return;
    resubmitting.value = true;
    actionError.value = null;
    try {
        if (!(await saveBeforeAction())) return;
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/resubmit`, undefined, {
            timeout: SAVE_TIMEOUT_MS,
        });
        await refreshSelected();
    } catch (error) {
        actionError.value =
            (error as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Resoumission impossible.';
    } finally {
        resubmitting.value = false;
    }
}

function statusLabel(status: string): string {
    return (
        {
            draft: 'Brouillon',
            quoted: 'Prête à financer',
            funded: 'Financée',
            submitted: 'En vérification',
            changes_requested: 'À corriger',
            approved: 'Active',
            rejected: 'Refusée',
            suspended: 'Suspendue',
        }[status] ?? status
    );
}

function statusClasses(status: string): string {
    if (status === 'approved') return 'bg-wpx-success/12 text-wpx-success-light';
    if (status === 'rejected' || status === 'suspended') return 'bg-wpx-danger/12 text-wpx-danger-light';
    if (status === 'submitted') return 'bg-wpx-blue/12 text-wpx-blue-light';
    return 'bg-wpx-gold/12 text-wpx-gold';
}

function campaignTitle(campaign: Campaign): string {
    const version = [...campaign.versions].sort((a, b) => b.version_number - a.version_number)[0];
    return (
        (version?.creative_configuration?.title as string | undefined)?.trim() ||
        (campaign.objective_code ? OBJECTIVES[campaign.objective_code] : null) ||
        'Nouvelle publicité'
    );
}

function categoryLabel(category: string): string {
    return (
        {
            demographic: 'Profil',
            interest: 'Centres d’intérêt',
            usage: 'Usages',
            possession: 'Équipement',
            project: 'Projets',
            situation: 'Situation',
            territory: 'Zone approximative',
        }[category] ?? category
    );
}

void loadAll();
</script>

<template>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-4">
        <div v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-2xl p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-wpx-gold text-[11px] font-extrabold tracking-[0.18em] uppercase">
                            Studio publicitaire
                        </p>
                        <h1 class="text-wpx-white-soft mt-1 text-xl font-extrabold sm:text-2xl">
                            Fais connaître ton activité
                        </h1>
                        <p class="text-wpx-muted-dark mt-1 max-w-2xl text-xs leading-relaxed sm:text-sm">
                            Prépare, cible et finance ta publicité avec ton solde Wasplex. Le parcours est pensé pour
                            être terminé en moins de 5 minutes.
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-xs font-bold"
                            :class="
                                view === 'create'
                                    ? 'bg-wpx-gold text-wpx-navy-950'
                                    : 'bg-wpx-navy-750 text-wpx-muted-dark'
                            "
                            @click="resetNewCampaign"
                        >
                            + Nouvelle publicité
                        </button>
                        <button
                            type="button"
                            class="bg-wpx-navy-750 text-wpx-muted-dark rounded-xl px-3 py-2 text-xs font-bold"
                            @click="view = 'history'"
                        >
                            Mes publicités ({{ campaigns.length }})
                        </button>
                    </div>
                </div>
            </section>

            <section
                v-if="view === 'history'"
                class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-wpx-white-soft text-base font-bold">Mes publicités</h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Retrouve tes brouillons, publicités en vérification et campagnes actives.
                        </p>
                    </div>
                    <button type="button" class="text-wpx-gold text-xs font-bold" @click="resetNewCampaign">
                        Créer →
                    </button>
                </div>
                <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>
                <div v-else-if="campaigns.length > 0" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="campaign in campaigns"
                        :key="campaign.id"
                        type="button"
                        class="border-wpx-border-dark bg-wpx-navy-750 rounded-xl border p-3.5 text-left transition hover:-translate-y-0.5"
                        @click="selectCampaign(campaign)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-sm font-bold">
                                    {{ campaignTitle(campaign) }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 truncate text-[11px]">
                                    {{ brands.find((brand) => brand.id === campaign.brand_id)?.name ?? 'Mon activité' }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold"
                                :class="statusClasses(campaign.status)"
                            >
                                {{ statusLabel(campaign.status) }}
                            </span>
                        </div>
                    </button>
                </div>
                <div v-else class="border-wpx-border-dark rounded-xl border border-dashed p-8 text-center">
                    <p class="text-wpx-white-soft text-sm font-bold">Aucune publicité pour le moment</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs">
                        Ta première publicité peut être prête en quelques minutes.
                    </p>
                    <button type="button" class="text-wpx-gold mt-3 text-xs font-bold" @click="resetNewCampaign">
                        Créer ma première publicité →
                    </button>
                </div>
            </section>

            <template v-else>
                <section
                    v-if="selectedCampaign && isReadOnlyCampaign"
                    class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                                :class="statusClasses(selectedCampaign.status)"
                            >
                                {{ statusLabel(selectedCampaign.status) }}
                            </span>
                            <h2 class="text-wpx-white-soft mt-3 text-xl font-extrabold">
                                {{ campaignTitle(selectedCampaign) }}
                            </h2>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                {{ selectedBrand?.name ?? 'Mon activité' }} ·
                                {{ totalBudgetMinor.toLocaleString('fr-FR') }} FCFA
                            </p>
                            <p
                                v-if="selectedCampaign.status === 'submitted'"
                                class="text-wpx-muted-dark mt-4 max-w-xl text-sm"
                            >
                                Ta publicité est bien envoyée. Wasplex la vérifie avant sa mise en diffusion.
                            </p>
                            <p
                                v-else-if="selectedCampaign.status === 'approved'"
                                class="text-wpx-success-light mt-4 text-sm font-semibold"
                            >
                                Ta publicité est active et peut être diffusée aux membres éligibles.
                            </p>
                            <p
                                v-else-if="selectedCampaign.status === 'rejected'"
                                class="text-wpx-danger-light mt-4 text-sm font-semibold"
                            >
                                Cette publicité a été refusée.
                            </p>
                            <p v-else class="text-wpx-gold mt-4 text-sm font-semibold">
                                Cette publicité est actuellement suspendue.
                            </p>
                            <p
                                v-if="latestReviewCase?.reason"
                                class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-muted-dark mt-3 rounded-xl border p-3 text-xs"
                            >
                                {{ latestReviewCase.reason }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="bg-wpx-gold text-wpx-navy-950 rounded-xl px-4 py-2 text-xs font-extrabold"
                            @click="resetNewCampaign"
                        >
                            + Nouvelle publicité
                        </button>
                    </div>
                    <CampaignPerformancePanel
                        v-if="selectedCampaign.status === 'approved' || selectedCampaign.status === 'suspended'"
                        :campaign-id="selectedCampaign.id"
                        class="mt-5"
                    />
                </section>

                <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_230px]">
                    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
                        <div class="mb-5 flex items-center justify-between gap-2">
                            <template v-for="(label, index) in STEPS" :key="label">
                                <div class="flex min-w-0 flex-1 items-center gap-2 last:flex-none">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-extrabold"
                                            :class="
                                                index <= step
                                                    ? 'bg-wpx-gold text-wpx-navy-950'
                                                    : 'bg-wpx-navy-750 text-wpx-muted-dark'
                                            "
                                        >
                                            {{ index + 1 }}
                                        </span>
                                        <span
                                            class="hidden text-[11px] font-bold sm:block"
                                            :class="index === step ? 'text-wpx-white-soft' : 'text-wpx-muted-dark'"
                                        >
                                            {{ label }}
                                        </span>
                                    </div>
                                    <span v-if="index < STEPS.length - 1" class="bg-wpx-border-dark h-px flex-1" />
                                </div>
                            </template>
                        </div>

                        <p
                            v-if="actionError && step === 2"
                            class="bg-wpx-danger/10 text-wpx-danger-light mb-4 rounded-xl p-3 text-xs"
                        >
                            {{ actionError }}
                        </p>

                        <div v-if="step === 0" class="flex flex-col gap-5">
                            <div>
                                <p class="text-wpx-white-soft text-lg font-extrabold">
                                    Qu’est-ce que tu veux promouvoir ?
                                </p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">
                                    Choisis ton activité, ton objectif et ton visuel. C’est tout pour cette étape.
                                </p>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <label class="text-wpx-white-soft text-xs font-bold">Mon activité</label>
                                    <button
                                        v-if="brands.length > 0 && !selectedCampaignId"
                                        type="button"
                                        class="text-wpx-gold text-[11px] font-bold"
                                        @click="showBrandCreator = !showBrandCreator"
                                    >
                                        + Ajouter une activité
                                    </button>
                                </div>
                                <select
                                    v-if="brands.length > 0"
                                    :value="form.brand_id"
                                    :disabled="Boolean(selectedCampaignId)"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-sm disabled:opacity-60"
                                    @change="selectBrandForNewCampaign(($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="" disabled>Choisir mon activité…</option>
                                    <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                        {{ brand.name }}
                                    </option>
                                </select>
                                <div
                                    v-if="brands.length === 0 || showBrandCreator"
                                    class="border-wpx-border-dark bg-wpx-navy-750 mt-2 flex flex-col gap-2 rounded-xl border p-3 sm:flex-row"
                                >
                                    <input
                                        v-model="newBrandName"
                                        class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft min-w-0 flex-1 rounded-lg border px-3 py-2.5 text-sm"
                                        placeholder="Ex. Boutique Awa, Chez Moussa, DG Afrique…"
                                        @keyup.enter="createBrandInline"
                                    />
                                    <button
                                        type="button"
                                        class="bg-wpx-gold text-wpx-navy-950 rounded-lg px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                                        :disabled="creatingBrand || !newBrandName.trim()"
                                        @click="createBrandInline"
                                    >
                                        {{ creatingBrand ? 'Création…' : 'Continuer' }}
                                    </button>
                                </div>
                                <p v-if="brands.length === 0" class="text-wpx-muted-dark mt-2 text-[11px]">
                                    Pas besoin d’être une entreprise : indique simplement le nom sous lequel les clients
                                    te connaissent.
                                </p>
                            </div>

                            <div>
                                <p class="text-wpx-white-soft text-xs font-bold">Que veux-tu que les gens fassent ?</p>
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <button
                                        v-for="(label, code) in OBJECTIVES"
                                        :key="code"
                                        type="button"
                                        class="rounded-xl border p-3 text-left text-sm font-bold transition"
                                        :class="
                                            form.objective_code === code
                                                ? 'border-wpx-gold bg-wpx-gold/10 text-wpx-white-soft'
                                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-muted-dark'
                                        "
                                        @click="form.objective_code = code"
                                    >
                                        <span class="flex items-center justify-between gap-2">
                                            {{ label }}
                                            <span v-if="form.objective_code === code" class="text-wpx-gold">✓</span>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-wpx-white-soft text-xs font-bold">Ton visuel</p>
                                <label
                                    class="mt-2 flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed p-5 text-center transition"
                                    :class="
                                        dragOver
                                            ? 'border-wpx-gold bg-wpx-gold/5'
                                            : 'border-wpx-border-dark bg-wpx-navy-750'
                                    "
                                    @dragover.prevent="dragOver = true"
                                    @dragleave.prevent="dragOver = false"
                                    @drop.prevent="onDrop"
                                >
                                    <span
                                        class="bg-wpx-cyan/12 text-wpx-cyan flex h-10 w-10 items-center justify-center rounded-xl text-xl"
                                        >↑</span
                                    >
                                    <span class="text-wpx-white-soft mt-2 text-sm font-bold">{{
                                        uploading ? 'Envoi de la vidéo…' : 'Ajouter une vidéo'
                                    }}</span>
                                    <span class="text-wpx-muted-dark mt-1 text-[11px]"
                                        >MP4, MOV ou WEBM · 5 minutes maximum</span
                                    >
                                    <input
                                        ref="fileInput"
                                        type="file"
                                        accept="video/mp4,video/quicktime,video/webm"
                                        class="hidden"
                                        @change="onFileInputChange"
                                    />
                                </label>

                                <div v-if="assets.length > 0" class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-5">
                                    <button
                                        v-for="asset in assets"
                                        :key="asset.id"
                                        type="button"
                                        class="relative overflow-hidden rounded-xl border p-1.5"
                                        :class="
                                            form.asset_id === asset.id
                                                ? 'border-wpx-gold bg-wpx-gold/10'
                                                : 'border-wpx-border-dark bg-wpx-navy-750'
                                        "
                                        @click="form.asset_id = asset.id"
                                    >
                                        <img
                                            v-if="asset.type === 'image' && asset.url"
                                            :src="asset.url"
                                            class="h-16 w-full rounded-lg object-cover"
                                        />
                                        <span
                                            v-else
                                            class="text-wpx-muted-dark flex h-16 items-center justify-center rounded-lg text-2xl"
                                            >▶</span
                                        >
                                        <span
                                            v-if="form.asset_id === asset.id"
                                            class="bg-wpx-gold text-wpx-navy-950 absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-black"
                                            >✓</span
                                        >
                                    </button>
                                </div>
                            </div>

                            <label class="flex flex-col gap-1.5">
                                <span class="text-wpx-white-soft text-xs font-bold"
                                    >Petit titre <span class="text-wpx-muted-dark font-normal">(facultatif)</span></span
                                >
                                <input
                                    v-model="form.title"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-xl border px-3 py-3 text-sm"
                                    placeholder="Ex. Livraison gratuite ce week-end"
                                />
                            </label>
                        </div>

                        <div v-else-if="step === 1" class="flex flex-col gap-5">
                            <div>
                                <p class="text-wpx-white-soft text-lg font-extrabold">À qui montrer ta publicité ?</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">
                                    Le réglage recommandé est simple : audience large, puis tu choisis ton budget.
                                </p>
                            </div>

                            <div class="border-wpx-border-dark bg-wpx-navy-750 rounded-xl border p-3.5">
                                <div class="flex items-start gap-3">
                                    <span
                                        class="bg-wpx-success/12 text-wpx-success-light flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                        >✓</span
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-wpx-white-soft text-sm font-bold">Audience large recommandée</p>
                                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                                            Wasplex cherche les membres éligibles. Tu peux préciser un pays ou affiner
                                            seulement si ton offre l’exige.
                                        </p>
                                    </div>
                                </div>

                                <label class="mt-3 flex flex-col gap-1.5">
                                    <span class="text-wpx-muted-dark text-[11px] font-bold"
                                        >Pays <span class="font-normal">(facultatif)</span></span
                                    >
                                    <select
                                        v-model="form.country_code"
                                        class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft rounded-lg border px-3 py-2.5 text-sm"
                                    >
                                        <option value="">Tous les pays disponibles</option>
                                        <option
                                            v-for="country in TARGET_COUNTRIES"
                                            :key="country[0]"
                                            :value="country[0]"
                                        >
                                            {{ country[1] }}
                                        </option>
                                    </select>
                                </label>

                                <button
                                    type="button"
                                    class="text-wpx-cyan mt-3 text-xs font-bold"
                                    @click="showAdvancedTargeting = !showAdvancedTargeting"
                                >
                                    {{
                                        showAdvancedTargeting
                                            ? '− Masquer les critères avancés'
                                            : '+ Affiner mon audience (facultatif)'
                                    }}
                                </button>

                                <div
                                    v-if="showAdvancedTargeting"
                                    class="border-wpx-border-dark mt-3 flex flex-col gap-4 border-t pt-3"
                                >
                                    <div v-for="(criteria, category) in profileCriteria" :key="category">
                                        <p class="text-wpx-white-soft text-[11px] font-bold">
                                            {{ categoryLabel(category) }}
                                        </p>
                                        <div class="mt-2 flex flex-wrap gap-1.5">
                                            <button
                                                v-for="criterion in criteria"
                                                :key="criterion.code"
                                                type="button"
                                                class="rounded-full border px-2.5 py-1.5 text-[11px] font-semibold"
                                                :class="
                                                    form.profile_taxonomies.includes(criterion.code)
                                                        ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan'
                                                        : 'border-wpx-border-dark text-wpx-muted-dark'
                                                "
                                                @click="toggleProfileCriterion(criterion.code)"
                                            >
                                                {{ form.profile_taxonomies.includes(criterion.code) ? '✓ ' : '+ '
                                                }}{{ criterion.label }}
                                            </button>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="bg-wpx-navy-950 text-wpx-cyan self-start rounded-lg px-3 py-2 text-[11px] font-bold disabled:opacity-50"
                                        :disabled="estimating"
                                        @click="runEstimate"
                                    >
                                        {{ estimating ? 'Estimation…' : 'Estimer cette audience' }}
                                    </button>
                                    <p v-if="estimate" class="text-wpx-muted-dark text-xs">
                                        <template v-if="estimate.too_small"
                                            >Audience encore limitée. La diffusion progressera avec les membres
                                            éligibles.</template
                                        >
                                        <template v-else
                                            >Audience estimée :
                                            <strong class="text-wpx-white-soft"
                                                >{{ estimate.estimated_min }} – {{ estimate.estimated_max }}</strong
                                            >
                                            comptes.</template
                                        >
                                    </p>
                                </div>
                            </div>

                            <div>
                                <p class="text-wpx-white-soft text-sm font-bold">Combien veux-tu investir par jour ?</p>
                                <p class="text-wpx-muted-dark mt-1 text-[11px]">
                                    Tu peux commencer petit. Wasplex calcule immédiatement le budget total.
                                </p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="amount in budgetOptions"
                                        :key="amount"
                                        type="button"
                                        class="rounded-xl border px-3 py-2 text-xs font-bold"
                                        :class="
                                            form.daily_budget_minor === amount
                                                ? 'border-wpx-gold bg-wpx-gold/10 text-wpx-gold'
                                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-muted-dark'
                                        "
                                        @click="form.daily_budget_minor = amount"
                                    >
                                        {{ numberFormatter.format(amount) }} FCFA
                                    </button>
                                </div>
                                <label class="mt-3 flex max-w-xs flex-col gap-1.5">
                                    <span class="text-wpx-muted-dark text-[11px]">Autre montant par jour</span>
                                    <input
                                        v-model.number="form.daily_budget_minor"
                                        type="number"
                                        :min="advertisingRules.minimum_daily_budget_minor"
                                        step="100"
                                        class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-xl border px-3 py-2.5 text-sm"
                                    />
                                </label>
                            </div>

                            <div>
                                <p class="text-wpx-white-soft text-sm font-bold">Pendant combien de jours ?</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <button
                                        v-for="days in durationOptions"
                                        :key="days"
                                        type="button"
                                        class="rounded-xl border px-3 py-2 text-xs font-bold"
                                        :class="
                                            form.duration_days === days
                                                ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan'
                                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-muted-dark'
                                        "
                                        @click="form.duration_days = days"
                                    >
                                        {{ days }} jour{{ days > 1 ? 's' : '' }}
                                    </button>
                                </div>
                            </div>

                            <div class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-2xl bg-gradient-to-r p-4">
                                <p class="text-[11px] font-bold uppercase opacity-70">Budget total</p>
                                <p class="mt-1 text-2xl font-black">
                                    {{ numberFormatter.format(totalBudgetMinor) }} FCFA
                                </p>
                                <p class="mt-1 text-xs font-semibold opacity-75">
                                    {{ numberFormatter.format(form.daily_budget_minor) }} FCFA ×
                                    {{ form.duration_days }} jour{{ form.duration_days > 1 ? 's' : '' }}
                                </p>
                                <div class="mt-3 border-t border-black/10 pt-3 text-xs font-bold">
                                    Solde disponible : {{ numberFormatter.format(walletAvailableMinor) }} FCFA
                                </div>
                            </div>
                            <p
                                v-if="!hasEnoughWallet"
                                class="bg-wpx-danger/10 text-wpx-danger-light rounded-xl p-3 text-xs"
                            >
                                Il manque {{ numberFormatter.format(totalBudgetMinor - walletAvailableMinor) }} FCFA. Tu
                                pourras recharger ton solde avant l’envoi.
                            </p>
                        </div>

                        <div v-else class="flex flex-col gap-4">
                            <div>
                                <p class="text-wpx-white-soft text-lg font-extrabold">Vérifie et envoie</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">
                                    Tout est prêt. Le budget sera réservé sur ton solde, puis la publicité partira en
                                    vérification.
                                </p>
                            </div>

                            <div
                                class="border-wpx-border-dark bg-wpx-navy-750 grid gap-3 rounded-xl border p-4 sm:grid-cols-2"
                            >
                                <div>
                                    <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Activité</p>
                                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                        {{ selectedBrand?.name ?? '—' }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Objectif</p>
                                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ ctaLabel ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Audience</p>
                                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                        {{ form.country_code || 'Large'
                                        }}<span v-if="form.profile_taxonomies.length">
                                            · {{ form.profile_taxonomies.length }} critère(s)</span
                                        >
                                    </p>
                                </div>
                                <div>
                                    <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Budget</p>
                                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                        {{ numberFormatter.format(totalBudgetMinor) }} FCFA
                                    </p>
                                </div>
                            </div>

                            <template v-if="selectedCampaign?.status === 'changes_requested'">
                                <div class="bg-wpx-gold/10 text-wpx-gold rounded-xl p-3 text-xs">
                                    <p class="font-bold">Wasplex demande une correction.</p>
                                    <p v-if="latestReviewCase?.reason" class="mt-1 opacity-90">
                                        {{ latestReviewCase.reason }}
                                    </p>
                                </div>
                                <p class="text-wpx-muted-dark text-xs">
                                    Le budget déjà réservé reste verrouillé : aucune nouvelle recharge n’est nécessaire
                                    pour resoumettre.
                                </p>
                                <button
                                    type="button"
                                    class="bg-wpx-gold text-wpx-navy-950 rounded-xl px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                                    :disabled="resubmitting"
                                    @click="runResubmit"
                                >
                                    {{ resubmitting ? 'Resoumission…' : 'Resoumettre ma publicité' }}
                                </button>
                            </template>

                            <template v-else>
                                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Mon solde</p>
                                            <p class="text-wpx-white-soft mt-1 text-xl font-black">
                                                {{ numberFormatter.format(walletAvailableMinor) }} FCFA
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                                            :class="
                                                hasEnoughWallet
                                                    ? 'bg-wpx-success/12 text-wpx-success-light'
                                                    : 'bg-wpx-danger/12 text-wpx-danger-light'
                                            "
                                        >
                                            {{ hasEnoughWallet ? 'Solde suffisant' : 'À recharger' }}
                                        </span>
                                    </div>
                                </div>

                                <button
                                    v-if="hasEnoughWallet"
                                    type="button"
                                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-4 py-3.5 text-sm font-black shadow-lg disabled:opacity-50"
                                    :disabled="sending || totalBudgetMinor < advertisingRules.minimum_budget_minor"
                                    @click="financeAndSubmit"
                                >
                                    {{
                                        sending
                                            ? 'Envoi en cours…'
                                            : `Réserver ${numberFormatter.format(totalBudgetMinor)} FCFA et envoyer`
                                    }}
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="bg-wpx-gold text-wpx-navy-950 rounded-xl px-4 py-3.5 text-sm font-black"
                                    @click="emit('navigateWallet')"
                                >
                                    Recharger mon solde de
                                    {{ numberFormatter.format(totalBudgetMinor - walletAvailableMinor) }} FCFA
                                </button>
                                <p class="text-wpx-muted-dark text-center text-[11px] leading-relaxed">
                                    Le budget est réservé, pas dépensé d’un coup. La facturation publicitaire suit les
                                    vues complètes effectivement diffusées.
                                </p>
                            </template>
                        </div>

                        <div v-if="step < 2" class="border-wpx-border-dark mt-6 border-t pt-4">
                            <div
                                v-if="actionError || (step === 1 && budgetValidationMessage)"
                                class="mb-3 flex flex-col gap-2"
                            >
                                <p
                                    v-if="actionError"
                                    class="bg-wpx-danger/10 text-wpx-danger-light rounded-xl p-3 text-xs"
                                >
                                    {{ actionError }}
                                </p>
                                <p
                                    v-if="step === 1 && budgetValidationMessage"
                                    class="bg-wpx-gold/10 text-wpx-gold rounded-xl p-3 text-xs font-semibold"
                                >
                                    {{ budgetValidationMessage }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button
                                    type="button"
                                    class="text-wpx-muted-dark rounded-xl px-3 py-3 text-xs font-bold disabled:opacity-30"
                                    :disabled="step === 0 || preparingStep"
                                    @click="step--"
                                >
                                    ← Retour
                                </button>
                                <button
                                    type="button"
                                    class="bg-wpx-gold text-wpx-navy-950 min-h-12 flex-1 rounded-xl px-5 py-3 text-sm font-black disabled:opacity-30 sm:flex-none"
                                    :disabled="!canContinue || preparingStep"
                                    @click="nextStep"
                                >
                                    {{
                                        preparingStep
                                            ? 'Sauvegarde…'
                                            : step === 0
                                              ? 'Audience & budget →'
                                              : 'Vérifier & envoyer →'
                                    }}
                                </button>
                            </div>
                        </div>
                        <div v-else class="mt-5">
                            <button type="button" class="text-wpx-muted-dark text-xs font-bold" @click="step--">
                                ← Modifier l’audience ou le budget
                            </button>
                        </div>
                    </section>

                    <aside class="hidden lg:block">
                        <div class="sticky top-24">
                            <CampaignPreviewPhone
                                :brand-name="selectedBrand?.name ?? null"
                                :title="form.title || null"
                                :cta-label="ctaLabel"
                                :asset-url="selectedAsset?.url ?? null"
                                :asset-type="selectedAsset?.type ?? null"
                                :gain-label="gainLabel"
                                :progress-percent="((step + 1) / STEPS.length) * 100"
                            />
                            <div
                                v-if="selectedVideoDurationMs > 0"
                                class="border-wpx-border-dark bg-wpx-navy-850 mt-3 rounded-2xl border p-3 text-xs"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-wpx-muted-dark">Durée détectée</span>
                                    <strong class="text-wpx-white-soft">{{
                                        formatVideoDuration(selectedVideoDurationMs)
                                    }}</strong>
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span class="text-wpx-muted-dark">Tarification Wasplex</span>
                                    <strong class="text-wpx-cyan"
                                        >{{ selectedVideoUnits }} tranche{{ selectedVideoUnits > 1 ? 's' : '' }} de 15
                                        s</strong
                                    >
                                </div>
                                <p class="text-wpx-muted-dark mt-2 leading-relaxed">
                                    Le membre est rémunéré uniquement après la fin réelle de la vidéo.
                                </p>
                            </div>
                        </div>
                    </aside>
                </div>
            </template>
        </template>
    </div>
</template>
