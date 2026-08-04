<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Brand = {
    id: string;
    name: string;
    slogan: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
};

type Asset = {
    id: string;
    kind: 'image' | 'video';
    name: string;
    mimeType: string;
    url: string | null;
};

type Quote = {
    id: string;
    status: 'issued' | 'funded' | 'void';
    currency: string;
    grossAmountMinor: number;
    platformShareMinor: number;
    userShareMinor: number;
    estimatedImpressions: number;
    catalogVersion: number | null;
    expiresAt: string;
};

type Campaign = {
    id: string;
    name: string;
    status: 'draft' | 'quoted' | 'funded' | 'submitted' | 'cancelled';
    currentStep: number;
    brand: { id: string; name: string; primaryColor: string | null; secondaryColor: string | null };
    version: {
        objective: string;
        headline: string | null;
        body: string | null;
        callToAction: string | null;
        destinationUrl: string | null;
        startsOn: string | null;
        endsOn: string | null;
        territoryName: string | null;
        radiusKm: number | null;
        selectedClasses: string[];
        budgetMinor: number;
        creatives: Asset[];
    } | null;
    audience: {
        estimatedMin: number;
        estimatedMax: number;
        notice: string | null;
    } | null;
    quote: Quote | null;
    funding: {
        status: string;
        amountMinor: number;
        reservationStatus: string | null;
    } | null;
};

type Wallet = {
    id: string;
    currency: string;
    balances: { available: number; reserved: number; pending: number; total: number };
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaign: Campaign | null;
    brands: Brand[];
    assets: Asset[];
    wallet: Wallet;
    economicClasses: Array<{ code: string; label: string }>;
    objectives: Array<{ code: string; label: string }>;
    minimumBudgetMinor: number;
    flash: { success: string | null };
}>();

const steps = [
    { number: 1, label: 'Marque' },
    { number: 2, label: 'Contenu' },
    { number: 3, label: 'Objectif' },
    { number: 4, label: 'Audience' },
    { number: 5, label: 'Territoire' },
    { number: 6, label: 'Devis' },
    { number: 7, label: 'Validation' },
] as const;

const version = props.campaign?.version;
const currentStep = ref(props.campaign?.currentStep ?? 1);
const saveState = ref<'idle' | 'saving' | 'saved'>('idle');
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;

const form = useForm({
    name: props.campaign?.name ?? '',
    brand_id: props.campaign?.brand.id ?? props.brands[0]?.id ?? '',
    objective: version?.objective ?? 'notoriety',
    headline: version?.headline ?? '',
    body: version?.body ?? '',
    call_to_action: version?.callToAction ?? '',
    destination_url: version?.destinationUrl ?? '',
    starts_on: version?.startsOn ?? '',
    ends_on: version?.endsOn ?? '',
    territory_name: version?.territoryName ?? 'Abidjan',
    radius_km: version?.radiusKm ?? 10,
    selected_classes: version?.selectedClasses ?? ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'],
    budget_minor: version?.budgetMinor ?? props.minimumBudgetMinor,
    creative_asset_ids: version?.creatives.map((asset) => asset.id) ?? [],
    current_step: currentStep.value,
});

const editable = computed(() => !props.campaign || ['draft', 'quoted'].includes(props.campaign.status));
const selectedBrand = computed(() => props.brands.find((brand) => brand.id === form.brand_id) ?? null);
const selectedAssets = computed(() => props.assets.filter((asset) => form.creative_asset_ids.includes(asset.id)));
const quote = computed(() => props.campaign?.quote ?? null);
const canFund = computed(
    () => quote.value?.status === 'issued' && props.wallet.balances.available >= quote.value.grossAmountMinor,
);

const money = (value: number, currency = 'XOF') =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);

const save = (after?: () => void) => {
    form.current_step = currentStep.value;
    saveState.value = 'saving';
    const options = {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            saveState.value = 'saved';
            window.setTimeout(() => (saveState.value = 'idle'), 1600);
            after?.();
        },
    };

    if (props.campaign) {
        form.patch(`/studio/campagnes/${props.campaign.id}`, options);
    } else {
        form.post('/studio/campagnes', options);
    }
};

const scheduleAutosave = () => {
    if (!props.campaign || !editable.value || form.processing) return;
    if (autosaveTimer) window.clearTimeout(autosaveTimer);
    autosaveTimer = window.setTimeout(() => save(), 1200);
};

watch(
    () => [
        form.name,
        form.brand_id,
        form.objective,
        form.headline,
        form.body,
        form.call_to_action,
        form.destination_url,
        form.starts_on,
        form.ends_on,
        form.territory_name,
        form.radius_km,
        form.budget_minor,
        JSON.stringify(form.selected_classes),
        JSON.stringify(form.creative_asset_ids),
    ],
    scheduleAutosave,
);

onBeforeUnmount(() => {
    if (autosaveTimer) window.clearTimeout(autosaveTimer);
});

const previous = () => {
    currentStep.value = Math.max(1, currentStep.value - 1);
};

const next = () => {
    currentStep.value = Math.min(7, currentStep.value + 1);
    if (props.campaign && editable.value) scheduleAutosave();
};

const generateQuote = () => {
    if (!props.campaign) {
        save();
        return;
    }
    save(() => router.post(`/studio/campagnes/${props.campaign?.id}/devis`));
};

const fund = () => {
    if (!props.campaign || !quote.value) return;
    router.post(`/studio/campagnes/${props.campaign.id}/devis/${quote.value.id}/financer`);
};

const submit = () => {
    if (!props.campaign) return;
    router.post(`/studio/campagnes/${props.campaign.id}/soumettre`);
};
</script>

<template>
    <Head :title="campaign ? campaign.name : 'Nouvelle campagne'" />
    <StudioLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <Link href="/studio/campagnes" class="text-xs font-black text-cyan-300/70">
                    ← Toutes les campagnes
                </Link>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    {{ campaign ? campaign.name : 'Nouvelle campagne' }}
                </h1>
                <p class="mt-2 text-sm text-white/38">
                    {{
                        campaign?.status === 'submitted'
                            ? 'Campagne soumise : les informations sont maintenant en lecture seule.'
                            : 'Sept étapes simples, avec sauvegarde automatique du brouillon.'
                    }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold text-white/30">
                    {{ saveState === 'saving' ? 'Enregistrement…' : saveState === 'saved' ? 'Enregistré' : '' }}
                </span>
                <button
                    v-if="editable"
                    class="rounded-2xl border border-white/10 px-4 py-3 text-xs font-black text-white/60"
                    :disabled="form.processing"
                    @click="save()"
                >
                    Enregistrer
                </button>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <div class="mt-7 flex gap-2 overflow-x-auto pb-2">
            <button
                v-for="step in steps"
                :key="step.number"
                class="flex min-w-28 items-center gap-2 rounded-2xl border px-3 py-2.5 text-left text-xs font-black transition"
                :class="
                    currentStep === step.number
                        ? 'border-cyan-300 bg-cyan-300 text-[#04111e]'
                        : step.number < currentStep
                          ? 'border-emerald-300/20 bg-emerald-300/8 text-emerald-200'
                          : 'border-white/8 bg-white/[0.025] text-white/35'
                "
                @click="currentStep = step.number"
            >
                <span class="grid size-6 place-items-center rounded-full bg-black/10">{{ step.number }}</span>
                {{ step.label }}
            </button>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-8">
                <div v-if="currentStep === 1">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 1</p>
                    <h2 class="mt-3 text-2xl font-black">Choisissez la marque</h2>
                    <p class="mt-2 text-sm text-white/38">La campagne héritera de son identité visuelle.</p>

                    <label class="mt-7 block">
                        <span class="text-xs font-black text-white/55">Nom interne de la campagne</span>
                        <input
                            v-model="form.name"
                            :disabled="!editable"
                            placeholder="Ex. Autodesk Abidjan — août"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50 disabled:opacity-50"
                        />
                        <span v-if="form.errors.name" class="mt-2 block text-xs text-rose-300">{{ form.errors.name }}</span>
                    </label>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="brand in brands"
                            :key="brand.id"
                            class="cursor-pointer rounded-2xl border p-4 transition"
                            :class="form.brand_id === brand.id ? 'border-cyan-300/60 bg-cyan-300/8' : 'border-white/8 bg-black/10'"
                        >
                            <input v-model="form.brand_id" type="radio" :value="brand.id" class="sr-only" :disabled="!editable" />
                            <div
                                class="h-2 rounded-full"
                                :style="{ background: `linear-gradient(90deg, ${brand.primaryColor ?? '#22D3EE'}, ${brand.secondaryColor ?? '#38BDF8'})` }"
                            />
                            <p class="mt-4 font-black">{{ brand.name }}</p>
                            <p class="mt-1 text-xs text-white/35">{{ brand.slogan || 'Aucun slogan' }}</p>
                        </label>
                    </div>
                </div>

                <div v-else-if="currentStep === 2">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 2</p>
                    <h2 class="mt-3 text-2xl font-black">Sélectionnez le contenu</h2>
                    <p class="mt-2 text-sm text-white/38">Images et vidéos déjà présentes dans votre médiathèque.</p>

                    <div v-if="assets.length" class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="asset in assets"
                            :key="asset.id"
                            class="cursor-pointer overflow-hidden rounded-2xl border transition"
                            :class="form.creative_asset_ids.includes(asset.id) ? 'border-cyan-300/60 bg-cyan-300/8' : 'border-white/8 bg-black/10'"
                        >
                            <input
                                v-model="form.creative_asset_ids"
                                type="checkbox"
                                :value="asset.id"
                                class="sr-only"
                                :disabled="!editable"
                            />
                            <div class="grid h-28 place-items-center overflow-hidden bg-black/25">
                                <img v-if="asset.kind === 'image' && asset.url" :src="asset.url" :alt="asset.name" class="size-full object-cover" />
                                <video v-else-if="asset.kind === 'video' && asset.url" :src="asset.url" muted class="size-full object-cover" />
                                <span v-else class="text-2xl">{{ asset.kind === 'video' ? '▶' : '▧' }}</span>
                            </div>
                            <p class="truncate p-3 text-xs font-black">{{ asset.name }}</p>
                        </label>
                    </div>
                    <div v-else class="mt-7 rounded-2xl border border-dashed border-white/12 p-8 text-center">
                        <p class="text-sm text-white/40">Votre médiathèque est vide.</p>
                        <Link href="/studio/medias" class="mt-4 inline-flex text-sm font-black text-cyan-300">Ajouter un média</Link>
                    </div>
                    <span v-if="form.errors.creative_asset_ids" class="mt-3 block text-xs text-rose-300">{{ form.errors.creative_asset_ids }}</span>
                </div>

                <div v-else-if="currentStep === 3">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 3</p>
                    <h2 class="mt-3 text-2xl font-black">Définissez l’objectif</h2>
                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="objective in objectives"
                            :key="objective.code"
                            class="cursor-pointer rounded-2xl border p-4 text-sm font-black"
                            :class="form.objective === objective.code ? 'border-cyan-300/60 bg-cyan-300/8' : 'border-white/8 bg-black/10'"
                        >
                            <input v-model="form.objective" type="radio" :value="objective.code" class="sr-only" :disabled="!editable" />
                            {{ objective.label }}
                        </label>
                    </div>
                    <div class="mt-6 grid gap-5">
                        <label>
                            <span class="text-xs font-black text-white/55">Titre publicitaire</span>
                            <input v-model="form.headline" :disabled="!editable" maxlength="180" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50 disabled:opacity-50" />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Message</span>
                            <textarea v-model="form.body" :disabled="!editable" rows="5" maxlength="2000" class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm leading-6 outline-none focus:border-cyan-300/50 disabled:opacity-50" />
                        </label>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label>
                                <span class="text-xs font-black text-white/55">Bouton d’action</span>
                                <input v-model="form.call_to_action" :disabled="!editable" placeholder="Découvrir l’offre" maxlength="80" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none disabled:opacity-50" />
                            </label>
                            <label>
                                <span class="text-xs font-black text-white/55">Lien de destination</span>
                                <input v-model="form.destination_url" :disabled="!editable" type="url" placeholder="https://…" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none disabled:opacity-50" />
                            </label>
                        </div>
                    </div>
                </div>

                <div v-else-if="currentStep === 4">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 4</p>
                    <h2 class="mt-3 text-2xl font-black">Choisissez les classes</h2>
                    <p class="mt-2 text-sm text-white/38">Sélection simple, sans coefficient technique.</p>
                    <div class="mt-7 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="economicClass in economicClasses"
                            :key="economicClass.code"
                            class="cursor-pointer rounded-2xl border p-5"
                            :class="form.selected_classes.includes(economicClass.code) ? 'border-cyan-300/60 bg-cyan-300/8' : 'border-white/8 bg-black/10'"
                        >
                            <input v-model="form.selected_classes" type="checkbox" :value="economicClass.code" class="sr-only" :disabled="!editable" />
                            <p class="font-black">{{ economicClass.label }}</p>
                            <p class="mt-1 text-xs text-white/32">{{ economicClass.code }}</p>
                        </label>
                    </div>
                    <span v-if="form.errors.selected_classes" class="mt-3 block text-xs text-rose-300">{{ form.errors.selected_classes }}</span>
                </div>

                <div v-else-if="currentStep === 5">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 5</p>
                    <h2 class="mt-3 text-2xl font-black">Territoire et calendrier</h2>
                    <div class="mt-7 grid gap-5 sm:grid-cols-2">
                        <label>
                            <span class="text-xs font-black text-white/55">Ville ou territoire</span>
                            <input v-model="form.territory_name" :disabled="!editable" placeholder="Abidjan" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none disabled:opacity-50" />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Rayon autour du territoire</span>
                            <div class="mt-2 flex items-center rounded-2xl border border-white/10 bg-black/20 px-4">
                                <input v-model.number="form.radius_km" :disabled="!editable" type="number" min="1" max="100" class="min-w-0 flex-1 bg-transparent py-3.5 text-sm outline-none disabled:opacity-50" />
                                <span class="text-xs font-black text-white/35">km</span>
                            </div>
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Début souhaité</span>
                            <input v-model="form.starts_on" :disabled="!editable" type="date" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none disabled:opacity-50" />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Fin souhaitée</span>
                            <input v-model="form.ends_on" :disabled="!editable" type="date" class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none disabled:opacity-50" />
                        </label>
                    </div>
                </div>

                <div v-else-if="currentStep === 6">
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 6</p>
                    <h2 class="mt-3 text-2xl font-black">Budget et devis</h2>
                    <p class="mt-2 text-sm text-white/38">Saisissez uniquement le budget global souhaité.</p>

                    <label class="mt-7 block">
                        <span class="text-xs font-black text-white/55">Budget global</span>
                        <div class="mt-2 flex items-center rounded-2xl border border-white/10 bg-black/20 px-4">
                            <input v-model.number="form.budget_minor" :disabled="!editable" type="number" :min="minimumBudgetMinor" step="1000" class="min-w-0 flex-1 bg-transparent py-4 text-xl font-black outline-none disabled:opacity-50" />
                            <span class="text-sm font-black text-white/35">FCFA</span>
                        </div>
                        <p class="mt-2 text-xs text-white/28">Minimum sandbox : {{ money(minimumBudgetMinor) }}</p>
                    </label>

                    <button
                        v-if="editable"
                        class="mt-7 w-full rounded-2xl bg-cyan-300 px-5 py-4 text-sm font-black text-[#04111e] disabled:opacity-50"
                        :disabled="form.processing || !campaign"
                        @click="generateQuote"
                    >
                        {{ campaign ? 'Obtenir mon devis' : 'Créez d’abord le brouillon' }}
                    </button>

                    <div v-if="campaign?.audience" class="mt-6 rounded-2xl border border-white/8 bg-black/15 p-5">
                        <p class="text-xs font-black text-white/40 uppercase">Audience de planification</p>
                        <p class="mt-2 text-2xl font-black">
                            {{ number(campaign.audience.estimatedMin) }} à {{ number(campaign.audience.estimatedMax) }} personnes
                        </p>
                        <p class="mt-2 text-xs leading-5 text-white/30">{{ campaign.audience.notice }}</p>
                    </div>

                    <div v-if="quote && quote.status !== 'void'" class="mt-5 rounded-2xl border border-amber-300/20 bg-amber-300/7 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-black text-amber-200 uppercase">Devis v{{ quote.catalogVersion }}</p>
                                <p class="mt-2 text-3xl font-black">{{ money(quote.grossAmountMinor, quote.currency) }}</p>
                            </div>
                            <span class="rounded-full bg-amber-200/10 px-3 py-1 text-[10px] font-black text-amber-100 uppercase">Figé</span>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-black/15 p-3">
                                <p class="text-xs text-white/35">Valeur utilisateurs</p>
                                <p class="mt-1 font-black">{{ money(quote.userShareMinor, quote.currency) }}</p>
                            </div>
                            <div class="rounded-xl bg-black/15 p-3">
                                <p class="text-xs text-white/35">Part plateforme</p>
                                <p class="mt-1 font-black">{{ money(quote.platformShareMinor, quote.currency) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else>
                    <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">Étape 7</p>
                    <h2 class="mt-3 text-2xl font-black">Prévisualiser et soumettre</h2>

                    <div class="mt-7 overflow-hidden rounded-[2rem] border border-white/10 bg-[#081522]">
                        <div
                            class="h-3"
                            :style="{ background: `linear-gradient(90deg, ${selectedBrand?.primaryColor ?? '#22D3EE'}, ${selectedBrand?.secondaryColor ?? '#38BDF8'})` }"
                        />
                        <div class="p-6">
                            <p class="text-xs font-black text-cyan-300">{{ selectedBrand?.name || 'Marque' }}</p>
                            <h3 class="mt-3 text-2xl font-black">{{ form.headline || form.name || 'Votre campagne' }}</h3>
                            <p class="mt-3 text-sm leading-6 text-white/45">{{ form.body || 'Votre message publicitaire apparaîtra ici.' }}</p>
                            <div class="mt-5 flex flex-wrap gap-2">
                                <span class="rounded-full bg-white/6 px-3 py-1 text-xs font-bold text-white/45">{{ form.territory_name }} + {{ form.radius_km }} km</span>
                                <span class="rounded-full bg-white/6 px-3 py-1 text-xs font-bold text-white/45">{{ form.selected_classes.length }} classe(s)</span>
                                <span class="rounded-full bg-white/6 px-3 py-1 text-xs font-bold text-white/45">{{ selectedAssets.length }} contenu(s)</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-white/8 bg-black/15 p-5">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-sm text-white/40">Wallet disponible</span>
                            <strong>{{ money(wallet.balances.available, wallet.currency) }}</strong>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-4">
                            <span class="text-sm text-white/40">Budget du devis</span>
                            <strong>{{ quote ? money(quote.grossAmountMinor, quote.currency) : 'Aucun devis' }}</strong>
                        </div>
                    </div>

                    <button
                        v-if="campaign?.status === 'quoted' && quote?.status === 'issued'"
                        class="mt-6 w-full rounded-2xl bg-cyan-300 px-5 py-4 text-sm font-black text-[#04111e] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!canFund"
                        @click="fund"
                    >
                        {{ canFund ? 'Réserver le budget dans mon Wallet' : 'Solde insuffisant — approvisionnez le Wallet' }}
                    </button>
                    <button
                        v-else-if="campaign?.status === 'funded'"
                        class="mt-6 w-full rounded-2xl bg-emerald-300 px-5 py-4 text-sm font-black text-[#04111e]"
                        @click="submit"
                    >
                        Soumettre pour revue administrative
                    </button>
                    <div v-else-if="campaign?.status === 'submitted'" class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/8 p-5 text-center">
                        <p class="font-black text-emerald-200">Campagne soumise avec succès</p>
                        <p class="mt-2 text-xs text-white/35">La réservation budgétaire reste protégée jusqu’à la décision P007.</p>
                    </div>
                    <Link v-else-if="!quote" href="#" class="mt-6 block text-center text-sm font-black text-cyan-300" @click.prevent="currentStep = 6">
                        Générer d’abord le devis
                    </Link>
                </div>

                <div class="mt-9 flex items-center justify-between border-t border-white/7 pt-6">
                    <button
                        class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-black text-white/55 disabled:opacity-30"
                        :disabled="currentStep === 1"
                        @click="previous"
                    >
                        Précédent
                    </button>
                    <button
                        v-if="currentStep < 7"
                        class="rounded-2xl bg-white px-5 py-3 text-sm font-black text-[#04111e]"
                        @click="next"
                    >
                        Continuer
                    </button>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6">
                    <p class="text-xs font-black text-white/35 uppercase">Résumé</p>
                    <dl class="mt-5 space-y-4 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-white/35">Marque</dt>
                            <dd class="text-right font-black">{{ selectedBrand?.name || 'À choisir' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-white/35">Contenus</dt>
                            <dd class="font-black">{{ selectedAssets.length }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-white/35">Territoire</dt>
                            <dd class="text-right font-black">{{ form.territory_name || 'À définir' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-white/35">Budget</dt>
                            <dd class="font-black">{{ money(form.budget_minor) }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="rounded-[2rem] border border-cyan-300/12 bg-cyan-300/[0.045] p-6">
                    <p class="text-xs font-black text-cyan-200 uppercase">Protection Wasplex</p>
                    <p class="mt-3 text-xs leading-5 text-white/38">
                        Le devis est versionné. Le financement réserve la valeur dans le Wallet sans la
                        consommer. Aucune diffusion ni rémunération utilisateur n’est déclenchée avant
                        les étapes suivantes.
                    </p>
                </div>
            </aside>
        </div>
    </StudioLayout>
</template>
