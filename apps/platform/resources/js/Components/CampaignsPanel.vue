<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import http from '@/lib/http';
import { CAMPAIGN_OBJECTIVES } from '@/lib/campaignObjectives';
import { economicClassLabel } from '@/lib/economicClasses';
import { TARGET_COUNTRIES } from '@/lib/countries';
import CampaignPerformancePanel from '@/Components/CampaignPerformancePanel.vue';
import CampaignPreviewPhone from '@/Components/CampaignPreviewPhone.vue';

interface Brand {
    id: string;
    name: string;
    status: string;
}

interface Asset {
    id: string;
    type: string;
    filename: string;
    url?: string;
}

interface EconomicClass {
    code: string;
    weight_percent: number;
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
}

interface CampaignVersion {
    version_number: number;
    creative_configuration: Record<string, unknown> | null;
    audience_configuration: {
        territory?: { country_code?: string };
        economic_classes?: string[];
        profile_taxonomies?: string[];
    } | null;
    budget_configuration: { budget_amount_minor?: number } | null;
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

const OBJECTIVES = CAMPAIGN_OBJECTIVES;

const STEPS = ['Marque', 'Objectif', 'Contenu', 'Audience', 'Budget', 'Vérification', 'Soumission'] as const;

const STEP_META = [
    {
        title: 'Pour quelle marque est cette campagne ?',
        subtitle: 'Les visuels et couleurs de cette marque seront proposés automatiquement.',
    },
    {
        title: 'Quel est ton objectif ?',
        subtitle: "C'est ce que les gens pourront faire en touchant ta publicité.",
    },
    {
        title: 'Quel contenu veux-tu diffuser ?',
        subtitle: 'Choisis un visuel de ta bibliothèque créative et donne un titre court à ta publicité.',
    },
    {
        title: 'Qui veux-tu toucher ?',
        subtitle: 'Choisis les profils qui verront ta publicité en priorité, et un pays si tu veux la limiter.',
    },
    {
        title: 'Combien veux-tu dépenser ?',
        subtitle: 'Ce montant sera réservé sur ton Wallet annonceur — 1 WP = 1 FCFA.',
    },
    {
        title: 'Vérifie et confirme ton budget',
        subtitle: 'Génère un devis détaillé, puis finance la campagne pour verrouiller le montant.',
    },
    {
        title: 'Envoie ta campagne en revue',
        subtitle: 'Un administrateur Wasplex vérifie chaque campagne avant sa diffusion dans le Feed.',
    },
] as const;

const campaigns = ref<Campaign[]>([]);
const brands = ref<Brand[]>([]);
const assets = ref<Asset[]>([]);
const economicClasses = ref<EconomicClass[]>([]);
const profileCriteria = ref<Record<string, ProfileCriterion[]>>({});
const selectedCampaignId = ref<string | null>(null);
const step = ref(0);
const loading = ref(true);
const loadError = ref<string | null>(null);
const actionError = ref<string | null>(null);
const estimating = ref(false);
const estimate = ref<{ estimated_min: number; estimated_max: number; too_small: boolean } | null>(null);
const quoting = ref(false);
const funding = ref(false);
const submitting = ref(false);
const resubmitting = ref(false);

const form = reactive({
    brand_id: '',
    objective_code: '' as string | null,
    asset_id: '' as string | null,
    title: '',
    country_code: '',
    economic_classes: [] as string[],
    profile_taxonomies: [] as string[],
    budget_amount_minor: null as number | null,
});

const selectedCampaign = computed(() => campaigns.value.find((c) => c.id === selectedCampaignId.value) ?? null);
const currentVersion = computed<CampaignVersion | null>(() => {
    const campaign = selectedCampaign.value;
    if (!campaign || campaign.versions.length === 0) {
        return null;
    }
    return [...campaign.versions].sort((a, b) => b.version_number - a.version_number)[0];
});
const latestQuote = computed<Quote | null>(() => {
    const version = currentVersion.value;
    if (!version?.quotes || version.quotes.length === 0) {
        return null;
    }
    return version.quotes[version.quotes.length - 1];
});
const latestReviewCase = computed<ReviewCase | null>(() => {
    const cases = selectedCampaign.value?.review_cases ?? [];
    return cases.length > 0 ? cases[0] : null;
});
const selectedAsset = computed(() => assets.value.find((a) => a.id === form.asset_id) ?? null);
const ctaLabel = computed(() => (form.objective_code ? OBJECTIVES[form.objective_code] : null));
const gainLabel = computed(() => {
    const quote = latestQuote.value;
    if (!quote || form.economic_classes.length === 0) {
        return null;
    }
    const gains = form.economic_classes
        .map((code) => quote.class_breakdown[code]?.gain_unitaire_minor)
        .filter((v): v is number => typeof v === 'number');
    if (gains.length === 0) {
        return null;
    }
    return `+${Math.min(...gains)} WP`;
});

async function loadAll(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [campaignsRes, brandsRes, classesRes, criteriaRes] = await Promise.all([
            http.get('/advertiser/campaigns'),
            http.get('/advertiser/brands'),
            http.get('/advertiser/economic-classes'),
            http.get('/advertiser/targeting/profile-criteria'),
        ]);
        campaigns.value = campaignsRes.data.campaigns;
        brands.value = brandsRes.data.brands;
        economicClasses.value = classesRes.data.economic_classes;
        profileCriteria.value = criteriaRes.data.profile_criteria;
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Les campagnes sont momentanément indisponibles.';
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
    form.economic_classes = version?.audience_configuration?.economic_classes ?? [];
    form.profile_taxonomies = version?.audience_configuration?.profile_taxonomies ?? [];
    form.budget_amount_minor = version?.budget_configuration?.budget_amount_minor ?? null;
    estimate.value = null;
}

async function selectCampaign(campaign: Campaign): Promise<void> {
    selectedCampaignId.value = campaign.id;
    step.value = 0;
    hydrateFormFromCampaign(campaign);
    if (campaign.brand_id) {
        const { data } = await http.get(`/advertiser/assets?brand_id=${campaign.brand_id}`);
        assets.value = data.assets;
    }
}

async function createCampaign(brandId: string): Promise<void> {
    actionError.value = null;
    const { data } = await http.post('/advertiser/campaigns', { brand_id: brandId });
    campaigns.value = [data.campaign, ...campaigns.value];
    await selectCampaign(data.campaign);
}

async function refreshSelected(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    const { data } = await http.get(`/advertiser/campaigns/${selectedCampaignId.value}`);
    const index = campaigns.value.findIndex((c) => c.id === data.campaign.id);
    if (index !== -1) {
        campaigns.value[index] = data.campaign;
    }
}

let autosaveTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleAutosave(): void {
    if (!selectedCampaignId.value) {
        return;
    }
    if (autosaveTimer) {
        clearTimeout(autosaveTimer);
    }
    autosaveTimer = setTimeout(() => void autosave(), 500);
}

async function autosave(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    actionError.value = null;
    try {
        await http.patch(`/advertiser/campaigns/${selectedCampaignId.value}`, {
            objective_code: form.objective_code || null,
            creative_configuration: form.asset_id ? { asset_id: form.asset_id, title: form.title } : undefined,
            audience_configuration:
                form.economic_classes.length > 0
                    ? {
                          economic_classes: form.economic_classes,
                          ...(form.profile_taxonomies.length > 0
                              ? { profile_taxonomies: form.profile_taxonomies }
                              : {}),
                          ...(form.country_code
                              ? { territory: { country_code: form.country_code.toUpperCase() } }
                              : {}),
                      }
                    : undefined,
            budget_configuration: form.budget_amount_minor
                ? { budget_amount_minor: form.budget_amount_minor }
                : undefined,
        });
        await refreshSelected();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "Échec de l'enregistrement automatique.";
    }
}

watch(
    () => [
        form.objective_code,
        form.asset_id,
        form.title,
        form.country_code,
        [...form.economic_classes],
        [...form.profile_taxonomies],
        form.budget_amount_minor,
    ],
    () => scheduleAutosave(),
);

function toggleClass(code: string): void {
    const index = form.economic_classes.indexOf(code);
    if (index === -1) {
        form.economic_classes.push(code);
    } else {
        form.economic_classes.splice(index, 1);
    }
    estimate.value = null;
}

function toggleProfileCriterion(code: string): void {
    if (code.startsWith('demographic.gender.') && !form.profile_taxonomies.includes(code)) {
        form.profile_taxonomies = form.profile_taxonomies.filter((item) => !item.startsWith('demographic.gender.'));
    }
    const index = form.profile_taxonomies.indexOf(code);
    if (index === -1) form.profile_taxonomies.push(code);
    else form.profile_taxonomies.splice(index, 1);
    estimate.value = null;
}

async function runEstimate(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    estimating.value = true;
    actionError.value = null;
    try {
        await autosave();
        const { data } = await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/estimate-audience`);
        estimate.value = data.estimate;
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Estimation impossible.';
    } finally {
        estimating.value = false;
    }
}

async function runQuote(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    quoting.value = true;
    actionError.value = null;
    try {
        await autosave();
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/quote`);
        await refreshSelected();
        step.value = 5;
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Devis impossible.';
    } finally {
        quoting.value = false;
    }
}

async function runFund(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    funding.value = true;
    actionError.value = null;
    try {
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/fund`);
        await refreshSelected();
        step.value = 6;
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Financement impossible.';
    } finally {
        funding.value = false;
    }
}

async function runSubmit(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    submitting.value = true;
    actionError.value = null;
    try {
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/submit`);
        await refreshSelected();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Soumission impossible.';
    } finally {
        submitting.value = false;
    }
}

async function runResubmit(): Promise<void> {
    if (!selectedCampaignId.value) {
        return;
    }
    resubmitting.value = true;
    actionError.value = null;
    try {
        await http.post(`/advertiser/campaigns/${selectedCampaignId.value}/resubmit`);
        await refreshSelected();
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Resoumission impossible.';
    } finally {
        resubmitting.value = false;
    }
}

function statusLabel(status: string): string {
    return (
        {
            draft: 'Brouillon',
            quoted: 'Devisée',
            funded: 'Financée',
            submitted: 'Soumise',
            changes_requested: 'Correction demandée',
            approved: 'Approuvée',
            rejected: 'Rejetée',
            suspended: 'Suspendue',
        }[status] ?? status
    );
}

void loadAll();
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <div class="flex flex-col gap-4 lg:flex-row">
                <!-- Liste des campagnes -->
                <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex w-full flex-col gap-3 p-4 lg:w-64">
                    <h2 class="text-wpx-text text-sm font-semibold">Mes campagnes</h2>
                    <div class="flex flex-col gap-1">
                        <label v-if="brands.length > 0" class="text-wpx-text-muted text-xs"
                            >Créer une campagne pour :</label
                        >
                        <select
                            v-if="brands.length > 0"
                            class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1.5 text-sm"
                            @change="(e) => createCampaign((e.target as HTMLSelectElement).value)"
                        >
                            <option value="" disabled selected>Choisir une marque…</option>
                            <option v-for="brand in brands" :key="brand.id" :value="brand.id">{{ brand.name }}</option>
                        </select>
                        <p v-else class="text-wpx-text-muted text-xs">
                            Créez d'abord une marque dans l'onglet Marques.
                        </p>
                    </div>
                    <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
                    <ul v-else class="flex flex-col gap-1">
                        <li v-for="campaign in campaigns" :key="campaign.id">
                            <button
                                type="button"
                                class="rounded-wpx-sm flex w-full items-center justify-between px-2 py-1.5 text-left text-sm"
                                :class="
                                    selectedCampaignId === campaign.id ? 'bg-wpx-canvas font-semibold' : 'text-wpx-text'
                                "
                                @click="selectCampaign(campaign)"
                            >
                                <span>{{
                                    campaign.objective_code ? OBJECTIVES[campaign.objective_code] : 'Campagne'
                                }}</span>
                                <span class="text-wpx-text-muted text-[10px]">{{ statusLabel(campaign.status) }}</span>
                            </button>
                        </li>
                        <li v-if="!loading && campaigns.length === 0" class="text-wpx-text-muted text-sm">
                            Aucune campagne encore.
                        </li>
                    </ul>
                </div>

                <!-- Assistant -->
                <div v-if="selectedCampaign" class="flex flex-1 flex-col gap-4 lg:flex-row">
                    <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex-1 p-4">
                        <div class="mb-6 flex items-center overflow-x-auto pb-1">
                            <template v-for="(label, i) in STEPS" :key="label">
                                <div class="flex flex-col items-center gap-1.5">
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                                        :class="
                                            i <= step
                                                ? 'bg-wpx-blue-light text-white'
                                                : 'border-wpx-border text-wpx-text-muted border-[1.5px] bg-white'
                                        "
                                    >
                                        {{ i + 1 }}
                                    </span>
                                    <span
                                        class="text-[11px] font-semibold whitespace-nowrap"
                                        :class="i === step ? 'text-wpx-blue-light' : 'text-wpx-text-muted'"
                                    >
                                        {{ label }}
                                    </span>
                                </div>
                                <div v-if="i < STEPS.length - 1" class="bg-wpx-border mx-1.5 mb-4 h-0.5 w-9 shrink-0" />
                            </template>
                        </div>

                        <p
                            v-if="actionError"
                            class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-sm mb-3 p-2 text-xs"
                        >
                            {{ actionError }}
                        </p>

                        <div class="mb-4.5">
                            <p class="text-wpx-text text-[15px] font-bold">{{ STEP_META[step].title }}</p>
                            <p class="text-wpx-text-muted mt-1 text-xs">{{ STEP_META[step].subtitle }}</p>
                        </div>

                        <!-- 1. Marque -->
                        <div v-if="step === 0" class="flex flex-col gap-2">
                            <div
                                class="border-wpx-blue-light bg-wpx-blue-light/5 rounded-wpx-md flex max-w-xs items-center gap-3.5 border-[1.5px] p-3.5"
                            >
                                <span
                                    class="from-wpx-blue to-wpx-cyan flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-sm font-extrabold text-white"
                                >
                                    {{
                                        (brands.find((b) => b.id === form.brand_id)?.name ?? '??')
                                            .slice(0, 2)
                                            .toUpperCase()
                                    }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="text-wpx-text block truncate text-sm font-bold">
                                        {{ brands.find((b) => b.id === form.brand_id)?.name }}
                                    </span>
                                    <span class="text-wpx-text-muted block text-xs">Marque de cette campagne</span>
                                </span>
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="shrink-0">
                                    <path
                                        d="M5 13l4 4L19 7"
                                        stroke="#075CCF"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                        </div>

                        <!-- 2. Objectif -->
                        <div v-else-if="step === 1" class="grid grid-cols-2 gap-2.5">
                            <button
                                v-for="(label, code) in OBJECTIVES"
                                :key="code"
                                type="button"
                                class="rounded-wpx-md flex items-center justify-between border-[1.5px] p-3 text-left text-sm font-semibold"
                                :class="
                                    form.objective_code === code
                                        ? 'border-wpx-blue-light bg-wpx-blue-light/5 text-wpx-text'
                                        : 'border-wpx-border text-wpx-text'
                                "
                                @click="form.objective_code = code"
                            >
                                {{ label }}
                                <svg
                                    v-if="form.objective_code === code"
                                    width="16"
                                    height="16"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                >
                                    <path
                                        d="M5 13l4 4L19 7"
                                        stroke="#075CCF"
                                        stroke-width="2.2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- 3. Contenu -->
                        <div v-else-if="step === 2" class="flex flex-col gap-3">
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-text font-semibold">Visuel à diffuser</span>
                                <select
                                    v-model="form.asset_id"
                                    class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                                >
                                    <option :value="null" disabled>Choisir dans ma bibliothèque…</option>
                                    <option v-for="asset in assets" :key="asset.id" :value="asset.id">
                                        {{ asset.filename }}
                                    </option>
                                </select>
                            </label>
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-text font-semibold">Titre de la publicité</span>
                                <input
                                    v-model="form.title"
                                    placeholder="Ex. Livraison gratuite ce week-end"
                                    class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                                />
                            </label>
                        </div>

                        <!-- 4. Audience -->
                        <div v-else-if="step === 3" class="flex flex-col gap-4">
                            <div>
                                <span class="text-wpx-text text-xs font-semibold">Profils à toucher en priorité</span>
                                <div class="mt-2 grid grid-cols-2 gap-2.5">
                                    <button
                                        v-for="ec in economicClasses"
                                        :key="ec.code"
                                        type="button"
                                        class="rounded-wpx-md flex items-center justify-between border-[1.5px] p-3 text-left text-sm font-semibold"
                                        :class="
                                            form.economic_classes.includes(ec.code)
                                                ? 'border-wpx-blue-light bg-wpx-blue-light/5 text-wpx-text'
                                                : 'border-wpx-border text-wpx-text'
                                        "
                                        @click="toggleClass(ec.code)"
                                    >
                                        {{ economicClassLabel(ec.code) }}
                                        <svg
                                            v-if="form.economic_classes.includes(ec.code)"
                                            width="16"
                                            height="16"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                        >
                                            <path
                                                d="M5 13l4 4L19 7"
                                                stroke="#075CCF"
                                                stroke-width="2.2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-text font-semibold">Pays ciblé (optionnel)</span>
                                <select
                                    v-model="form.country_code"
                                    class="rounded-wpx-sm border-wpx-border w-full max-w-sm border px-2 py-2 text-sm"
                                >
                                    <option value="">Tous les pays</option>
                                    <option v-for="country in TARGET_COUNTRIES" :key="country[0]" :value="country[0]">
                                        {{ country[1] }} ({{ country[0] }})
                                    </option>
                                </select>
                            </label>
                            <div v-for="(criteria, category) in profileCriteria" :key="category">
                                <span class="text-wpx-text text-xs font-semibold">
                                    {{
                                        {
                                            demographic: 'Genre déclaré',
                                            interest: 'Centres d’intérêt',
                                            usage: 'Usages',
                                            possession: 'Équipement',
                                            project: 'Projets',
                                            situation: 'Situation',
                                            territory: 'Zone approximative',
                                        }[category] ?? category
                                    }}
                                </span>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="criterion in criteria"
                                        :key="criterion.code"
                                        type="button"
                                        class="rounded-full border px-3 py-1.5 text-xs font-medium"
                                        :class="
                                            form.profile_taxonomies.includes(criterion.code)
                                                ? 'border-wpx-blue bg-wpx-blue/10 text-wpx-blue'
                                                : 'border-wpx-border text-wpx-text'
                                        "
                                        @click="toggleProfileCriterion(criterion.code)"
                                    >
                                        {{ form.profile_taxonomies.includes(criterion.code) ? '✓ ' : '+ '
                                        }}{{ criterion.label }}
                                    </button>
                                </div>
                            </div>
                            <p class="text-wpx-text-muted text-xs">
                                Les critères de profil sont déclarés volontairement par les utilisateurs. Wasplex ne
                                révèle jamais leur identité à l’annonceur.
                            </p>
                            <button
                                type="button"
                                class="rounded-wpx-md bg-wpx-navy-950 self-start px-3.5 py-2 text-xs font-semibold text-white disabled:opacity-50"
                                :disabled="estimating || form.economic_classes.length === 0"
                                @click="runEstimate"
                            >
                                Estimer l'audience
                            </button>
                            <p v-if="estimate" class="text-wpx-text text-sm">
                                Audience estimée :
                                <strong>{{ estimate.estimated_min }} – {{ estimate.estimated_max }}</strong> comptes.
                                <span v-if="estimate.too_small" class="text-wpx-danger-light">Segment trop petit.</span>
                            </p>
                        </div>

                        <!-- 5. Budget -->
                        <div v-else-if="step === 4" class="flex flex-col gap-3">
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-text font-semibold">Budget total à réserver (FCFA)</span>
                                <input
                                    v-model.number="form.budget_amount_minor"
                                    type="number"
                                    min="1"
                                    placeholder="Ex. 10000"
                                    class="rounded-wpx-sm border-wpx-border w-40 border px-2 py-1.5 text-sm"
                                />
                            </label>
                        </div>

                        <!-- 6. Vérification -->
                        <div v-else-if="step === 5" class="flex flex-col gap-3 text-sm">
                            <button
                                type="button"
                                class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 self-start bg-gradient-to-br px-4 py-1.5 text-sm font-semibold disabled:opacity-50"
                                :disabled="quoting"
                                @click="runQuote"
                            >
                                Générer le devis
                            </button>
                            <div v-if="latestQuote" class="rounded-wpx-sm bg-wpx-canvas flex flex-col gap-1 p-3">
                                <p>
                                    Budget : <strong>{{ latestQuote.gross_amount_minor }} FCFA</strong>
                                </p>
                                <p>
                                    Événements estimés : <strong>{{ latestQuote.estimated_events }}</strong>
                                </p>
                                <p>
                                    Portée estimée :
                                    <strong
                                        >{{ latestQuote.estimated_reach_min }} –
                                        {{ latestQuote.estimated_reach_max }}</strong
                                    >
                                </p>
                                <p class="text-wpx-text-muted text-xs">
                                    Expire le {{ new Date(latestQuote.expires_at).toLocaleString() }}
                                </p>
                                <button
                                    type="button"
                                    class="rounded-wpx-sm bg-wpx-navy-950 mt-2 self-start px-4 py-1.5 text-sm font-semibold text-white disabled:opacity-50"
                                    :disabled="funding"
                                    @click="runFund"
                                >
                                    Financer la campagne
                                </button>
                            </div>
                        </div>

                        <!-- 7. Soumission -->
                        <div v-else-if="step === 6" class="flex flex-col gap-3 text-sm">
                            <p v-if="selectedCampaign.status === 'submitted'" class="text-wpx-success-light">
                                Campagne soumise — en attente de revue administrative.
                            </p>
                            <p v-else-if="selectedCampaign.status === 'approved'" class="text-wpx-success-light">
                                Campagne approuvée.
                            </p>
                            <p v-else-if="selectedCampaign.status === 'suspended'" class="text-wpx-warning-light">
                                Campagne suspendue par l'administration.
                            </p>
                            <template v-else-if="selectedCampaign.status === 'rejected'">
                                <p class="text-wpx-danger-light">Campagne rejetée.</p>
                                <p v-if="latestReviewCase?.reason" class="text-wpx-text-muted">
                                    Motif : {{ latestReviewCase.reason }}
                                </p>
                            </template>
                            <template v-else-if="selectedCampaign.status === 'changes_requested'">
                                <p class="text-wpx-warning-light">Correction demandée par l'administration.</p>
                                <p
                                    v-if="latestReviewCase?.reason"
                                    class="bg-wpx-canvas rounded-wpx-sm text-wpx-text p-2"
                                >
                                    {{ latestReviewCase.reason }}
                                </p>
                                <p class="text-wpx-text-muted text-xs">
                                    Corrigez le contenu, l'audience ou le budget ci-dessus puis resoumettez — le budget
                                    déjà réservé reste verrouillé, aucun nouveau financement n'est nécessaire.
                                </p>
                                <button
                                    type="button"
                                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 self-start bg-gradient-to-br px-4 py-1.5 text-sm font-semibold disabled:opacity-50"
                                    :disabled="resubmitting"
                                    @click="runResubmit"
                                >
                                    Resoumettre la campagne
                                </button>
                            </template>
                            <template v-else>
                                <p class="text-wpx-text-muted">Budget réservé. Prête à être soumise pour revue.</p>
                                <button
                                    type="button"
                                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 self-start bg-gradient-to-br px-4 py-1.5 text-sm font-semibold disabled:opacity-50"
                                    :disabled="submitting || selectedCampaign.status !== 'funded'"
                                    @click="runSubmit"
                                >
                                    Soumettre la campagne
                                </button>
                            </template>
                        </div>

                        <CampaignPerformancePanel
                            v-if="selectedCampaign.status === 'approved' || selectedCampaign.status === 'suspended'"
                            :campaign-id="selectedCampaign.id"
                            class="mt-4"
                        />

                        <div class="mt-5 flex justify-between">
                            <button
                                type="button"
                                class="rounded-wpx-md border-wpx-border text-wpx-text border-[1.5px] bg-white px-4 py-2 text-xs font-semibold disabled:opacity-30"
                                :disabled="step === 0"
                                @click="step--"
                            >
                                ← Précédent
                            </button>
                            <button
                                type="button"
                                class="rounded-wpx-md bg-wpx-blue-light px-6 py-2 text-xs font-bold text-white disabled:opacity-30"
                                :disabled="step === STEPS.length - 1"
                                @click="step++"
                            >
                                Suivant →
                            </button>
                        </div>
                    </div>

                    <div class="lg:w-56">
                        <CampaignPreviewPhone
                            :brand-name="brands.find((b) => b.id === form.brand_id)?.name ?? null"
                            :title="form.title || null"
                            :cta-label="ctaLabel"
                            :asset-url="selectedAsset?.url ?? null"
                            :asset-type="selectedAsset?.type ?? null"
                            :gain-label="gainLabel"
                            :progress-percent="((step + 1) / STEPS.length) * 100"
                        />
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface text-wpx-text-muted flex flex-1 items-center justify-center p-4 text-sm"
                >
                    Choisissez une marque pour créer une campagne.
                </div>
            </div>
        </template>
    </div>
</template>
