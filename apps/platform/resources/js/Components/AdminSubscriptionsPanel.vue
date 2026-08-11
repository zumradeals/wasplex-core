<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface EconomicClass {
    id: string;
    code: string;
    versions: Array<{ quota_monthly: number; reward_per_complete_view_minor: number; currency: string }>;
}

interface PlanVersion {
    id: string;
    status: string;
    price_minor: number;
    currency: string;
    duration_days: number;
    plan: { code: string; name: string };
    economic_class_link: { economic_class: { id: string; code: string } } | null;
}

const LAUNCH_PRICES: Record<string, number> = {
    FREE: 0,
    PREMIUM: 1500,
    GOLD: 4000,
    PLATINUM: 7500,
};

const LEVEL_ORDER: Record<string, number> = {
    FREE: 0,
    PREMIUM: 1,
    GOLD: 2,
    PLATINUM: 3,
};

const levels = ref<EconomicClass[]>([]);
const planVersions = ref<PlanVersion[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const showCreate = ref(false);
const editing = ref<string | null>(null);
const simulatorBudget = ref(100000);

const createForm = reactive({
    plan_code: '',
    plan_name: '',
    economic_class_id: '',
    price_minor: 0,
    duration_days: 30,
});
const editForm = reactive({ price_minor: 0, duration_days: 30 });
const rewardForms = reactive<Record<string, { quota_monthly: number; reward_per_complete_view_minor: number }>>({});
const numberFormatter = new Intl.NumberFormat('fr-FR');

const orderedLevels = computed(() =>
    [...levels.value].sort((a, b) => (LEVEL_ORDER[a.code] ?? 99) - (LEVEL_ORDER[b.code] ?? 99)),
);
const orderedPlanVersions = computed(() =>
    [...planVersions.value].sort((a, b) => (LEVEL_ORDER[a.plan.code] ?? 99) - (LEVEL_ORDER[b.plan.code] ?? 99)),
);
const freeReward = computed(() => {
    const free = levels.value.find((level) => level.code === 'FREE');
    return free ? (rewardForms[free.id]?.reward_per_complete_view_minor ?? 30) : 30;
});
const publishedCount = computed(() => planVersions.value.filter((version) => version.status === 'published').length);
const draftCount = computed(() => planVersions.value.filter((version) => version.status === 'draft').length);
const memberEnvelope = computed(() => Math.floor(Math.max(0, simulatorBudget.value || 0) / 2));
const wasplexEnvelope = computed(() => Math.max(0, simulatorBudget.value || 0) - memberEnvelope.value);

function rewardFor(level: EconomicClass): number {
    return (
        rewardForms[level.id]?.reward_per_complete_view_minor ?? level.versions[0]?.reward_per_complete_view_minor ?? 0
    );
}

function quotaFor(level: EconomicClass): number {
    return rewardForms[level.id]?.quota_monthly ?? level.versions[0]?.quota_monthly ?? 0;
}

function rewardAdvantage(level: EconomicClass): string {
    const reward = rewardFor(level);
    if (level.code === 'FREE' || freeReward.value <= 0) return 'Récompense de base';
    return `×${(reward / freeReward.value).toFixed(2).replace('.', ',')} · +${Math.round(
        ((reward - freeReward.value) / freeReward.value) * 100,
    )} %`;
}

function advertiserCostPerView(level: EconomicClass): number {
    return rewardFor(level) * 2;
}

function monthlyCeiling(level: EconomicClass): number {
    return rewardFor(level) * quotaFor(level);
}

function simulatedViews(level: EconomicClass): number {
    const reward = rewardFor(level);
    return reward > 0 ? Math.floor(memberEnvelope.value / reward) : 0;
}

function simulatedConsumed(level: EconomicClass): number {
    return simulatedViews(level) * advertiserCostPerView(level);
}

function simulatedRemainder(level: EconomicClass): number {
    return Math.max(0, Math.max(0, simulatorBudget.value || 0) - simulatedConsumed(level));
}

function planForLevel(level: EconomicClass): PlanVersion | undefined {
    return planVersions.value.find(
        (version) =>
            version.economic_class_link?.economic_class.id === level.id ||
            version.economic_class_link?.economic_class.code === level.code ||
            version.plan.code === level.code,
    );
}

function launchPrice(code: string): number | null {
    return Object.prototype.hasOwnProperty.call(LAUNCH_PRICES, code) ? LAUNCH_PRICES[code] : null;
}

function formatAmount(value: number): string {
    return numberFormatter.format(Math.max(0, value || 0));
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [levelsRes, plansRes] = await Promise.all([
            http.get('/admin/economic-classes'),
            http.get('/admin/subscriptions/plans'),
        ]);
        levels.value = levelsRes.data.economic_classes;
        for (const level of levels.value) {
            rewardForms[level.id] = {
                quota_monthly: level.versions[0]?.quota_monthly ?? 1,
                reward_per_complete_view_minor: level.versions[0]?.reward_per_complete_view_minor ?? 30,
            };
        }
        planVersions.value = plansRes.data.plan_versions;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger les offres.';
    } finally {
        loading.value = false;
    }
}

async function saveReward(level: EconomicClass): Promise<void> {
    busy.value = level.id;
    error.value = null;
    try {
        await http.patch(`/admin/economic-classes/${level.id}`, {
            ...rewardForms[level.id],
            country_code: null,
            currency: 'XOF',
        });
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Modification de la récompense impossible.';
    } finally {
        busy.value = null;
    }
}

async function createPlan(): Promise<void> {
    busy.value = 'create';
    error.value = null;
    try {
        await http.post('/admin/subscriptions/plans', {
            ...createForm,
            plan_code: createForm.plan_code.trim().toUpperCase(),
            plan_name: createForm.plan_name.trim(),
            currency: 'XOF',
        });
        Object.assign(createForm, {
            plan_code: '',
            plan_name: '',
            economic_class_id: '',
            price_minor: 0,
            duration_days: 30,
        });
        showCreate.value = false;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Création de l’offre impossible.';
    } finally {
        busy.value = null;
    }
}

function startEdit(version: PlanVersion): void {
    editing.value = version.id;
    editForm.price_minor = version.price_minor;
    editForm.duration_days = version.duration_days;
}

function useLaunchPrice(version: PlanVersion): void {
    const approvedPrice = launchPrice(version.plan.code);
    if (approvedPrice !== null) editForm.price_minor = approvedPrice;
}

async function saveEdit(version: PlanVersion): Promise<void> {
    busy.value = version.id;
    error.value = null;
    try {
        await http.patch(`/admin/subscriptions/plans/${version.id}`, {
            price_minor: editForm.price_minor,
            duration_days: editForm.duration_days,
        });
        editing.value = null;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Modification impossible.';
    } finally {
        busy.value = null;
    }
}

async function publish(version: PlanVersion): Promise<void> {
    const confirmed = window.confirm(`Publier l’offre ${version.plan.name} ? Elle deviendra visible par les membres.`);
    if (!confirmed) return;

    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/publish`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function suspend(version: PlanVersion): Promise<void> {
    if (!window.confirm(`Suspendre l’offre ${version.plan.name} ? Elle ne sera plus proposée aux membres.`)) return;
    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/suspend`);
        await load();
    } finally {
        busy.value = null;
    }
}

function statusLabel(status: string): string {
    return { draft: 'Brouillon', published: 'Publié', suspended: 'Suspendu' }[status] ?? status;
}

function statusClasses(status: string): string {
    if (status === 'published') return 'bg-wpx-success/15 text-wpx-success';
    if (status === 'suspended') return 'bg-wpx-danger/15 text-wpx-danger';
    return 'bg-wpx-warning/15 text-wpx-gold';
}

onMounted(load);
</script>

<template>
    <div class="text-wpx-white-soft flex flex-col gap-5">
        <section
            class="border-wpx-border-dark from-wpx-navy-850 to-wpx-navy-950 rounded-wpx-xl shadow-wpx-card-dark border bg-gradient-to-br p-5 md:p-6"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">
                        Offres & modèle économique
                    </p>
                    <h2 class="mt-1 text-2xl font-extrabold">Comprendre, régler et publier les offres Wasplex.</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                        Une vue complète partage sa valeur à parts égales : <strong>50 % au membre</strong> et
                        <strong>50 % à Wasplex</strong>. Le membre reçoit un montant fixe selon son niveau, jamais un
                        pourcentage du budget total de l’annonceur.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan rounded-wpx-md text-wpx-navy-950 bg-gradient-to-br px-4 py-2.5 text-xs font-extrabold"
                    @click="showCreate = !showCreate"
                >
                    {{ showCreate ? 'Fermer' : '+ Créer une offre' }}
                </button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950/80 border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Part membres</p>
                    <p class="text-wpx-success mt-1 text-2xl font-extrabold">50 %</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Financée uniquement par les vues complètes.</p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950/80 border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Part Wasplex</p>
                    <p class="text-wpx-cyan mt-1 text-2xl font-extrabold">50 %</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Comptabilisée au même moment que la récompense.</p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950/80 border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        Revenus abonnements
                    </p>
                    <p class="text-wpx-gold mt-1 text-lg font-extrabold">Séparés de la publicité</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs">
                        Le prix Premium, Gold ou Platine est un revenu commercial distinct.
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <span class="bg-wpx-success/15 text-wpx-success rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ publishedCount }} publiée{{ publishedCount > 1 ? 's' : '' }}
                </span>
                <span class="bg-wpx-warning/15 text-wpx-gold rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ draftCount }} brouillon{{ draftCount > 1 ? 's' : '' }}
                </span>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/15 text-wpx-danger rounded-wpx-md p-3 text-sm">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5 md:p-6"
            >
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.14em] uppercase">Niveaux membres</p>
                    <h3 class="mt-1 text-xl font-extrabold">Récompenses & quotas</h3>
                    <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                        Ce sont les deux avantages économiques de l’abonnement : gagner davantage par publicité et
                        pouvoir recevoir davantage de publicités dans le mois.
                    </p>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="level in orderedLevels"
                        :key="level.id"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-lg font-extrabold">{{ planForLevel(level)?.plan.name ?? level.code }}</p>
                                <p class="text-wpx-cyan mt-1 text-xs font-bold">{{ rewardAdvantage(level) }}</p>
                            </div>
                            <span
                                v-if="planForLevel(level)"
                                class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                :class="statusClasses(planForLevel(level)?.status ?? '')"
                            >
                                {{ statusLabel(planForLevel(level)?.status ?? '') }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <div class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border p-3">
                                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Membre / vue</p>
                                <p class="mt-1 text-xl font-extrabold">{{ formatAmount(rewardFor(level)) }} WP</p>
                            </div>
                            <div class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border p-3">
                                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Coût annonceur / vue</p>
                                <p class="mt-1 text-xl font-extrabold">
                                    {{ formatAmount(advertiserCostPerView(level)) }} WP
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 text-xs">
                            <p class="flex items-center justify-between gap-3">
                                <span class="text-wpx-muted-dark">Publicités max / mois</span>
                                <strong>{{ formatAmount(quotaFor(level)) }}</strong>
                            </p>
                            <p class="mt-2 flex items-center justify-between gap-3">
                                <span class="text-wpx-muted-dark">Plafond théorique membre</span>
                                <strong>{{ formatAmount(monthlyCeiling(level)) }} WP</strong>
                            </p>
                        </div>

                        <div class="border-wpx-border-dark mt-4 border-t pt-4">
                            <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                                Modifier ce niveau
                            </p>
                            <label class="mt-3 block text-xs">
                                <span class="text-wpx-muted-dark">Gain par vue complète (WP)</span>
                                <input
                                    v-model.number="rewardForms[level.id].reward_per_complete_view_minor"
                                    type="number"
                                    min="1"
                                    class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 mt-1 w-full border px-3 py-2 text-sm"
                                />
                            </label>
                            <label class="mt-3 block text-xs">
                                <span class="text-wpx-muted-dark">Publicités maximum / mois</span>
                                <input
                                    v-model.number="rewardForms[level.id].quota_monthly"
                                    type="number"
                                    min="1"
                                    class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 mt-1 w-full border px-3 py-2 text-sm"
                                />
                            </label>
                            <button
                                type="button"
                                :disabled="busy === level.id"
                                class="text-wpx-cyan mt-3 text-xs font-extrabold disabled:opacity-50"
                                @click="saveReward(level)"
                            >
                                Enregistrer récompense & quota
                            </button>
                        </div>
                    </article>
                </div>

                <p class="text-wpx-muted-dark mt-4 text-xs">
                    Le plafond théorique suppose qu’un membre reçoit toutes les publicités permises par son quota. Il ne
                    constitue jamais une promesse de revenu : la disponibilité dépend des campagnes et de l’éligibilité
                    du profil.
                </p>
            </section>

            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5 md:p-6"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.14em] uppercase">
                            Simulateur économique
                        </p>
                        <h3 class="mt-1 text-xl font-extrabold">Que devient un budget annonceur ?</h3>
                        <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm">
                            Le calcul montre la capacité maximale si toutes les vues rémunérées appartenaient au même
                            niveau membre.
                        </p>
                    </div>
                    <label class="w-full max-w-xs text-xs">
                        <span class="text-wpx-muted-dark font-bold">Budget campagne (FCFA)</span>
                        <input
                            v-model.number="simulatorBudget"
                            type="number"
                            min="0"
                            step="1000"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 mt-1 w-full border px-3 py-2.5 text-sm"
                        />
                    </label>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="border-wpx-success/30 rounded-wpx-lg bg-wpx-success/10 border p-4">
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase">Enveloppe membres</p>
                        <p class="text-wpx-success mt-1 text-2xl font-extrabold">
                            {{ formatAmount(memberEnvelope) }} FCFA
                        </p>
                    </div>
                    <div class="border-wpx-cyan/30 rounded-wpx-lg bg-wpx-cyan/10 border p-4">
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase">
                            Part Wasplex correspondante
                        </p>
                        <p class="text-wpx-cyan mt-1 text-2xl font-extrabold">
                            {{ formatAmount(wasplexEnvelope) }} FCFA
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="level in orderedLevels"
                        :key="`sim-${level.id}`"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4"
                    >
                        <p class="font-extrabold">{{ planForLevel(level)?.plan.name ?? level.code }}</p>
                        <p class="text-wpx-cyan mt-2 text-2xl font-extrabold">
                            ≈ {{ formatAmount(simulatedViews(level)) }} vues
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            {{ formatAmount(rewardFor(level)) }} WP membre + {{ formatAmount(rewardFor(level)) }} WP
                            Wasplex par vue.
                        </p>
                        <p v-if="simulatedRemainder(level) > 0" class="text-wpx-muted-dark mt-2 text-[11px]">
                            Reste non consommé par ce scénario : {{ formatAmount(simulatedRemainder(level)) }}.
                        </p>
                    </div>
                </div>
            </section>

            <form
                v-if="showCreate"
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5"
                @submit.prevent="createPlan"
            >
                <h3 class="text-base font-extrabold">Nouvelle offre commerciale</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Une nouvelle offre reste en brouillon jusqu’à sa publication explicite.
                </p>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Nom</span>
                        <input
                            v-model="createForm.plan_name"
                            required
                            placeholder="Premium"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 border px-3 py-2.5 text-sm"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Code</span>
                        <input
                            v-model="createForm.plan_code"
                            required
                            placeholder="PREMIUM"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 border px-3 py-2.5 text-sm"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Niveau de récompense</span>
                        <select
                            v-model="createForm.economic_class_id"
                            required
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 border px-3 py-2.5 text-sm"
                        >
                            <option value="" disabled>Choisir</option>
                            <option v-for="level in orderedLevels" :key="level.id" :value="level.id">
                                {{ level.code }}
                            </option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Prix (FCFA)</span>
                        <input
                            v-model.number="createForm.price_minor"
                            type="number"
                            min="0"
                            required
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 border px-3 py-2.5 text-sm"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Durée (jours)</span>
                        <input
                            v-model.number="createForm.duration_days"
                            type="number"
                            min="1"
                            required
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 border px-3 py-2.5 text-sm"
                        />
                    </label>
                </div>
                <button
                    type="submit"
                    :disabled="busy === 'create'"
                    class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 mt-4 px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                >
                    Créer le brouillon
                </button>
            </form>

            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5 md:p-6"
            >
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.14em] uppercase">
                        Offres commerciales
                    </p>
                    <h3 class="mt-1 text-xl font-extrabold">Prix, durée et publication</h3>
                    <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                        Le prix de l’abonnement est distinct de la récompense publicitaire. Les prix de lancement
                        approuvés sont Gratuit 0, Premium 1 500, Gold 4 000 et Platine 7 500 FCFA pour 30 jours.
                    </p>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="version in orderedPlanVersions"
                        :key="version.id"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-extrabold">{{ version.plan.name }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">{{ version.plan.code }}</p>
                            </div>
                            <span
                                class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                :class="statusClasses(version.status)"
                            >
                                {{ statusLabel(version.status) }}
                            </span>
                        </div>

                        <template v-if="editing === version.id">
                            <div class="mt-4 flex flex-col gap-3">
                                <label class="flex flex-col gap-1 text-xs">
                                    <span class="text-wpx-muted-dark">Prix (FCFA)</span>
                                    <input
                                        v-model.number="editForm.price_minor"
                                        type="number"
                                        min="0"
                                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                                    />
                                </label>
                                <button
                                    v-if="launchPrice(version.plan.code) !== null"
                                    type="button"
                                    class="text-wpx-gold text-left text-xs font-bold"
                                    @click="useLaunchPrice(version)"
                                >
                                    Appliquer le prix de lancement :
                                    {{ formatAmount(launchPrice(version.plan.code) ?? 0) }} FCFA
                                </button>
                                <label class="flex flex-col gap-1 text-xs">
                                    <span class="text-wpx-muted-dark">Durée (jours)</span>
                                    <input
                                        v-model.number="editForm.duration_days"
                                        type="number"
                                        min="1"
                                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                                    />
                                </label>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        :disabled="busy === version.id"
                                        class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-3 py-2 text-xs font-extrabold"
                                        @click="saveEdit(version)"
                                    >
                                        Enregistrer
                                    </button>
                                    <button
                                        type="button"
                                        class="text-wpx-muted-dark px-2 text-xs font-bold"
                                        @click="editing = null"
                                    >
                                        Annuler
                                    </button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="mt-5">
                                <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                                    Prix membre
                                </p>
                                <p class="mt-1 text-2xl font-extrabold">
                                    {{
                                        version.price_minor === 0
                                            ? 'Gratuit'
                                            : `${formatAmount(version.price_minor)} FCFA`
                                    }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">{{ version.duration_days }} jours</p>
                                <p
                                    v-if="
                                        launchPrice(version.plan.code) !== null &&
                                        version.price_minor !== launchPrice(version.plan.code)
                                    "
                                    class="text-wpx-gold mt-2 text-xs font-bold"
                                >
                                    Prix de lancement approuvé :
                                    {{ formatAmount(launchPrice(version.plan.code) ?? 0) }} FCFA
                                </p>
                            </div>

                            <div
                                v-if="version.economic_class_link"
                                class="border-wpx-border-dark mt-4 border-t pt-4 text-xs"
                            >
                                <p class="flex items-center justify-between gap-3">
                                    <span class="text-wpx-muted-dark">Niveau lié</span>
                                    <strong>{{ version.economic_class_link.economic_class.code }}</strong>
                                </p>
                            </div>

                            <div class="border-wpx-border-dark mt-4 border-t pt-4">
                                <p v-if="version.status === 'published'" class="text-wpx-success text-xs font-bold">
                                    Visible actuellement dans Mon Espace.
                                </p>
                                <p v-else-if="version.status === 'draft'" class="text-wpx-gold text-xs font-bold">
                                    Invisible pour les membres tant qu’elle reste en brouillon.
                                </p>
                                <p v-else class="text-wpx-danger text-xs font-bold">Cette offre n’est plus proposée.</p>
                            </div>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <button
                                    v-if="version.status === 'draft'"
                                    type="button"
                                    class="border-wpx-border-dark text-wpx-cyan rounded-wpx-md border px-3 py-2 text-xs font-bold"
                                    @click="startEdit(version)"
                                >
                                    Modifier
                                </button>
                                <button
                                    v-if="version.status === 'draft'"
                                    type="button"
                                    :disabled="busy === version.id"
                                    class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-3 py-2 text-xs font-extrabold disabled:opacity-50"
                                    @click="publish(version)"
                                >
                                    Publier
                                </button>
                                <button
                                    v-if="version.status === 'published'"
                                    type="button"
                                    :disabled="busy === version.id"
                                    class="border-wpx-danger/40 text-wpx-danger rounded-wpx-md border px-3 py-2 text-xs font-bold disabled:opacity-50"
                                    @click="suspend(version)"
                                >
                                    Suspendre
                                </button>
                            </div>
                        </template>
                    </article>
                </div>
            </section>
        </template>
    </div>
</template>
