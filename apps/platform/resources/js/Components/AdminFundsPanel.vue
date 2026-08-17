<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import AdminFundPartnerQuotes from '@/Components/AdminFundPartnerQuotes.vue';
import AdminFundCollections from '@/Components/AdminFundCollections.vue';
import AdminFundRealizations from '@/Components/AdminFundRealizations.vue';
import AdminFundPilotage from '@/Components/AdminFundPilotage.vue';

type ProgramVersion = {
    id: string;
    version: number;
    status: string;
    currency: string;
    membership_fee_minor: number;
    duration_days: number;
    max_active_wishes: number;
    max_wishes_per_period: number;
    max_wish_amount_minor: number | null;
    personal_contribution_percent: number;
    min_debit_minor: number;
    max_debit_minor: number | null;
    monthly_cap_minor: number | null;
    wasplex_fee_minor: number;
    notice_hours: number;
    grace_period_days: number;
    arrears_grace_days: number;
    max_simultaneous_collections: number;
    emergency_queue_share_percent: number;
    reserve_min_balance_minor: number;
    reciprocity_min_score: number;
    rehabilitation_incident_threshold: number;
    eligible_subscription_classes: string[] | null;
};
type Program = { id: string; code: string; name: string; status: string; versions: ProgramVersion[] };

// Classes économiques payantes existantes (App\Modules\Subscriptions\Infrastructure\Models\EconomicClass).
// FREE est volontairement exclue : "les membres gratuits de Wasplex ne peuvent pas adhérer à Fonds"
// (docs/01-module-fonds-wasplex.md §5).
const ELIGIBLE_CLASS_OPTIONS = [
    { code: 'PREMIUM', label: 'Premium' },
    { code: 'GOLD', label: 'Gold' },
    { code: 'PLATINUM', label: 'Platinum' },
];
type Category = { id: string; code: string; name: string; icon: string | null; is_active: boolean };
type Wish = {
    id: string;
    status: string;
    title: string;
    description: string;
    estimated_amount_minor: number | null;
    required_personal_contribution_minor: number | null;
    personal_contribution_minor: number;
    personal_contribution_remaining_minor: number | null;
    personal_contribution_progress_percent: number;
    currency: string;
    review_note: string | null;
    category?: { name: string; icon: string | null };
    membership?: { program?: { name: string } };
};
type Dashboard = {
    programs: Program[];
    categories: Category[];
    wishes: Wish[];
    metrics: {
        active_programs: number;
        submitted_wishes: number;
        approved_wishes: number;
        personal_contributions_count: number;
        personal_contributions_minor: number;
    };
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const dashboard = ref<Dashboard | null>(null);
const showProgram = ref(false);
const showCategory = ref(false);
const editingProgram = ref<Program | null>(null);
const reviewWish = ref<Wish | null>(null);

function defaultProgramForm() {
    return {
        code: '',
        name: '',
        membership_fee_minor: null as number | null,
        duration_days: 365,
        max_active_wishes: 1,
        max_wishes_per_period: 1,
        max_wish_amount_minor: 1000000,
        personal_contribution_percent: 30,
        min_debit_minor: 100,
        max_debit_minor: 1000,
        monthly_cap_minor: 5000,
        wasplex_fee_minor: 100,
        notice_hours: 24,
        grace_period_days: 7,
        arrears_grace_days: 7,
        max_simultaneous_collections: 1,
        emergency_queue_share_percent: 20,
        reserve_min_balance_minor: 0,
        reciprocity_min_score: 0,
        rehabilitation_incident_threshold: 3,
        eligible_subscription_classes: [] as string[],
    };
}

const programForm = ref(defaultProgramForm());
// Programme déjà créé (étape 1 réussie) mais dont la version ou la
// publication a échoué : une relance reprend ce même programme au lieu
// d'en recréer un autre — sinon le code reste "coincé" sur un brouillon
// orphelin (aucune suppression possible une fois une version publiée).
const pendingProgramId = ref<string | null>(null);
const categoryForm = ref({ code: '', name: '', icon: '🎯', description: '' });
const reviewForm = ref({ decision: 'approve', note: '' });

const pendingWishes = computed(() => dashboard.value?.wishes.filter((wish) => wish.status === 'submitted') ?? []);

function money(value: number | null, currency = 'XOF'): string {
    if (value === null) return '—';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/admin/funds');
        dashboard.value = response.data;
    } catch {
        error.value = 'Impossible de charger le pilotage Fonds.';
    } finally {
        loading.value = false;
    }
}

function resetProgram(): void {
    editingProgram.value = null;
    pendingProgramId.value = null;
    programForm.value = defaultProgramForm();
    showProgram.value = true;
}

function apiErrorMessage(e: unknown, fallback: string): string {
    return (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;
}

async function createProgram(): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        let programId = pendingProgramId.value;
        if (programId === null) {
            const created = await http.post('/admin/funds/programs', {
                code: programForm.value.code.trim().toLowerCase(),
                name: programForm.value.name,
            });
            programId = created.data.id;
            // Mémorisé dès que le programme existe : si l'étape suivante
            // échoue, "Créer et publier" pourra reprendre sans recréer un
            // programme (et sans se heurter à un code déjà pris).
            pendingProgramId.value = programId;
        }

        const version = await http.post(`/admin/funds/programs/${programId}/versions`, {
            currency: 'XOF',
            membership_fee_minor: programForm.value.membership_fee_minor,
            duration_days: programForm.value.duration_days,
            minimum_subscription_age_days: 0,
            max_active_wishes: programForm.value.max_active_wishes,
            max_wishes_per_period: programForm.value.max_wishes_per_period,
            max_wish_amount_minor: programForm.value.max_wish_amount_minor,
            personal_contribution_percent: programForm.value.personal_contribution_percent,
            min_debit_minor: programForm.value.min_debit_minor,
            max_debit_minor: programForm.value.max_debit_minor,
            daily_cap_minor: programForm.value.max_debit_minor,
            monthly_cap_minor: programForm.value.monthly_cap_minor,
            annual_cap_minor: programForm.value.monthly_cap_minor * 12,
            wasplex_fee_minor: programForm.value.wasplex_fee_minor,
            notice_hours: programForm.value.notice_hours,
            grace_period_days: programForm.value.grace_period_days,
            arrears_grace_days: programForm.value.arrears_grace_days,
            max_simultaneous_collections: programForm.value.max_simultaneous_collections,
            emergency_queue_share_percent: programForm.value.emergency_queue_share_percent,
            reserve_min_balance_minor: programForm.value.reserve_min_balance_minor,
            reciprocity_min_score: programForm.value.reciprocity_min_score,
            rehabilitation_incident_threshold: programForm.value.rehabilitation_incident_threshold,
            eligible_subscription_classes: programForm.value.eligible_subscription_classes,
        });
        await http.post(`/admin/funds/program-versions/${version.data.id}/publish`);
        showProgram.value = false;
        pendingProgramId.value = null;
        await load();
    } catch (e) {
        error.value = apiErrorMessage(e, 'Le programme n’a pas pu être créé.');
    } finally {
        busy.value = false;
    }
}

async function deleteProgram(program: Program): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.delete(`/admin/funds/programs/${program.id}`);
        await load();
    } catch (e) {
        error.value = apiErrorMessage(e, 'Le programme n’a pas pu être supprimé.');
    } finally {
        busy.value = false;
    }
}

async function toggleProgram(program: Program): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/funds/programs/${program.id}/status`, {
            status: program.status === 'active' ? 'disabled' : 'active',
        });
        await load();
    } catch (e) {
        error.value = apiErrorMessage(e, 'Le statut du programme n’a pas pu être changé.');
    } finally {
        busy.value = false;
    }
}

async function createCategory(): Promise<void> {
    busy.value = true;
    try {
        await http.post('/admin/funds/categories', {
            ...categoryForm.value,
            code: categoryForm.value.code.trim().toLowerCase(),
        });
        categoryForm.value = { code: '', name: '', icon: '🎯', description: '' };
        showCategory.value = false;
        await load();
    } catch (e) {
        error.value = apiErrorMessage(e, 'La catégorie n’a pas pu être créée.');
    } finally {
        busy.value = false;
    }
}

async function toggleCategory(category: Category): Promise<void> {
    busy.value = true;
    try {
        await http.patch(`/admin/funds/categories/${category.id}`, { is_active: !category.is_active });
        await load();
    } finally {
        busy.value = false;
    }
}

function openReview(wish: Wish): void {
    reviewWish.value = wish;
    reviewForm.value = { decision: 'approve', note: '' };
}

async function submitReview(): Promise<void> {
    if (!reviewWish.value) return;
    busy.value = true;
    try {
        await http.post(`/admin/funds/wishes/${reviewWish.value.id}/review`, reviewForm.value);
        reviewWish.value = null;
        await load();
    } catch (e) {
        error.value = apiErrorMessage(e, 'La décision n’a pas pu être enregistrée.');
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5">
        <section
            class="from-wpx-navy-750 to-wpx-navy-950 border-wpx-border-dark rounded-wpx-xl border bg-gradient-to-br p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-bold tracking-[0.16em] uppercase">Pilotage Fonds</p>
                    <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Programmes, catégories et vœux</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm">
                        Configurez le produit sans toucher au code. Les nouvelles règles sont versionnées ; les
                        engagements existants restent liés à la version acceptée.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button
                        class="border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-2 text-xs font-bold"
                        @click="showCategory = true"
                    >
                        + Catégorie
                    </button>
                    <button
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-2 text-xs font-extrabold"
                        @click="resetProgram"
                    >
                        + Programme
                    </button>
                </div>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger rounded-lg px-3 py-2 text-sm">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-if="dashboard && !loading">
            <section class="grid gap-3 sm:grid-cols-4">
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Programmes actifs</p>
                    <p class="text-wpx-white-soft mt-2 text-2xl font-extrabold">
                        {{ dashboard.metrics.active_programs }}
                    </p>
                </div>
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Vœux à vérifier</p>
                    <p class="text-wpx-gold mt-2 text-2xl font-extrabold">{{ dashboard.metrics.submitted_wishes }}</p>
                </div>
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Vœux validés</p>
                    <p class="text-wpx-success-light mt-2 text-2xl font-extrabold">
                        {{ dashboard.metrics.approved_wishes }}
                    </p>
                </div>
            </section>

            <AdminFundPartnerQuotes />

            <AdminFundCollections />

            <AdminFundRealizations />

            <AdminFundPilotage />

            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-wpx-white-soft font-bold">Programmes</h3>
                        <p class="text-wpx-muted-dark text-xs">Activation et paramètres visibles par les membres.</p>
                    </div>
                </div>
                <div
                    v-if="dashboard.programs.length === 0"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-muted-dark rounded-xl border p-5 text-center text-sm"
                >
                    Aucun programme. Créez le premier programme Fonds.
                </div>
                <article
                    v-for="program in dashboard.programs"
                    :key="program.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-wpx-white-soft text-base font-bold">{{ program.name }}</h4>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="
                                        program.status === 'active'
                                            ? 'bg-wpx-success/10 text-wpx-success-light'
                                            : 'text-wpx-muted-dark bg-white/5'
                                    "
                                    >{{
                                        program.status === 'active'
                                            ? 'Actif'
                                            : program.status === 'disabled'
                                              ? 'Désactivé'
                                              : 'Brouillon'
                                    }}</span
                                >
                            </div>
                            <p class="text-wpx-muted-dark mt-1 font-mono text-[11px]">{{ program.code }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button
                                v-if="program.status === 'draft' && program.versions.length === 0"
                                class="border-wpx-border-dark text-wpx-danger rounded-lg border px-3 py-2 text-xs font-bold"
                                :disabled="busy"
                                @click="deleteProgram(program)"
                            >
                                Supprimer
                            </button>
                            <button
                                v-if="program.status !== 'draft'"
                                class="border-wpx-border-dark rounded-lg border px-3 py-2 text-xs font-bold"
                                :class="program.status === 'active' ? 'text-wpx-danger' : 'text-wpx-cyan'"
                                :disabled="busy"
                                @click="toggleProgram(program)"
                            >
                                {{ program.status === 'active' ? 'Désactiver' : 'Activer' }}
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="program.status === 'draft' && program.versions.length === 0"
                        class="text-wpx-danger-light mt-2 text-xs"
                    >
                        Brouillon incomplet : aucune version n’a été publiée (création interrompue). Supprimez-le et
                        recréez le programme.
                    </p>
                    <div v-if="program.versions[0]" class="mt-4 grid gap-2 sm:grid-cols-5">
                        <div class="bg-wpx-navy-950 rounded-lg p-3">
                            <p class="text-wpx-muted-dark text-[10px] uppercase">Adhésion</p>
                            <p class="text-wpx-gold mt-1 text-sm font-bold">
                                {{ money(program.versions[0].membership_fee_minor) }}
                            </p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3">
                            <p class="text-wpx-muted-dark text-[10px] uppercase">Apport</p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                {{ program.versions[0].personal_contribution_percent }}%
                            </p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3">
                            <p class="text-wpx-muted-dark text-[10px] uppercase">Débit</p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                {{ money(program.versions[0].min_debit_minor) }} –
                                {{ money(program.versions[0].max_debit_minor) }}
                            </p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3">
                            <p class="text-wpx-muted-dark text-[10px] uppercase">Frais Wasplex</p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                {{ money(program.versions[0].wasplex_fee_minor) }}
                            </p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3">
                            <p class="text-wpx-muted-dark text-[10px] uppercase">Grâce</p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                                {{ program.versions[0].grace_period_days }} jours
                            </p>
                        </div>
                    </div>
                    <p v-if="program.versions[0]" class="text-wpx-muted-dark mt-2 text-[11px]">
                        Éligible :
                        <span class="text-wpx-cyan font-semibold">{{
                            program.versions[0].eligible_subscription_classes &&
                            program.versions[0].eligible_subscription_classes.length > 0
                                ? program.versions[0].eligible_subscription_classes.join(', ')
                                : 'tout abonnement payant éligible à Fonds'
                        }}</span>
                    </p>
                </article>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h3 class="text-wpx-white-soft font-bold">Catégories de vœux</h3>
                    <p class="text-wpx-muted-dark text-xs">Administrables : rien n’est figé dans l’interface.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="category in dashboard.categories"
                        :key="category.id"
                        type="button"
                        class="border-wpx-border-dark rounded-full border px-3 py-2 text-xs font-bold"
                        :class="category.is_active ? 'bg-wpx-blue/10 text-wpx-cyan' : 'text-wpx-muted-dark opacity-60'"
                        @click="toggleCategory(category)"
                    >
                        {{ category.icon }} {{ category.name }}
                    </button>
                    <span v-if="dashboard.categories.length === 0" class="text-wpx-muted-dark text-sm"
                        >Aucune catégorie configurée.</span
                    >
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h3 class="text-wpx-white-soft font-bold">Vœux à vérifier</h3>
                    <p class="text-wpx-muted-dark text-xs">
                        Aucune validation automatique : chaque état doit correspondre à une décision réelle.
                    </p>
                </div>
                <div
                    v-if="pendingWishes.length === 0"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-muted-dark rounded-xl border p-5 text-center text-sm"
                >
                    Aucun vœu en attente.
                </div>
                <article
                    v-for="wish in pendingWishes"
                    :key="wish.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-wpx-cyan text-[10px] font-bold uppercase">
                                {{ wish.category?.icon }} {{ wish.category?.name }} ·
                                {{ wish.membership?.program?.name }}
                            </p>
                            <h4 class="text-wpx-white-soft mt-1 text-base font-bold">{{ wish.title }}</h4>
                            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm leading-relaxed">
                                {{ wish.description }}
                            </p>
                            <p v-if="wish.estimated_amount_minor" class="text-wpx-gold mt-2 text-sm font-bold">
                                {{ money(wish.estimated_amount_minor, wish.currency) }}
                            </p>
                        </div>
                        <button
                            class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-lg bg-gradient-to-r px-3 py-2 text-xs font-extrabold"
                            @click="openReview(wish)"
                        >
                            Examiner
                        </button>
                    </div>
                </article>
            </section>
        </template>

        <Teleport to="body">
            <div
                v-if="showProgram"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showProgram = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-cyan text-xs font-bold uppercase">Configuration</p>
                            <h3 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Nouveau programme Fonds</h3>
                        </div>
                        <button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="showProgram = false">×</button>
                    </div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="text-wpx-muted-dark text-xs"
                            >Nom<input
                                v-model="programForm.name"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                                placeholder="Ex. Gold"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Code<input
                                v-model="programForm.code"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                                placeholder="gold"
                        /></label>
                        <label class="text-wpx-gold text-xs font-bold sm:col-span-2"
                            >Prix d’adhésion (FCFA) — un programme Fonds n’est jamais gratuit
                            <input
                                v-model.number="programForm.membership_fee_minor"
                                type="number"
                                min="1"
                                inputmode="numeric"
                                class="bg-wpx-navy-850 border-wpx-gold/40 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 font-bold"
                                placeholder="Ex. 2500"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs sm:col-span-2"
                            >Classes d’abonnement éligibles (aucune sélection = tout abonnement payant éligible à Fonds)
                            <div class="mt-2 flex flex-wrap gap-2">
                                <label
                                    v-for="option in ELIGIBLE_CLASS_OPTIONS"
                                    :key="option.code"
                                    class="border-wpx-border-dark has-checked:border-wpx-gold has-checked:text-wpx-gold text-wpx-muted-dark flex cursor-pointer items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold"
                                >
                                    <input
                                        v-model="programForm.eligible_subscription_classes"
                                        type="checkbox"
                                        :value="option.code"
                                        class="accent-wpx-gold"
                                    />
                                    {{ option.label }}
                                </label>
                            </div>
                        </label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Apport personnel (%)<input
                                v-model.number="programForm.personal_contribution_percent"
                                type="number"
                                min="0"
                                max="100"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Vœux / période<input
                                v-model.number="programForm.max_wishes_per_period"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Montant max d’un vœu<input
                                v-model.number="programForm.max_wish_amount_minor"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Plafond mensuel<input
                                v-model.number="programForm.monthly_cap_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Contribution min.<input
                                v-model.number="programForm.min_debit_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Contribution max.<input
                                v-model.number="programForm.max_debit_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Frais Wasplex / débit<input
                                v-model.number="programForm.wasplex_fee_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Préavis (heures)<input
                                v-model.number="programForm.notice_hours"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Délai de grâce (jours)<input
                                v-model.number="programForm.grace_period_days"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Grâce arriérés (jours)<input
                                v-model.number="programForm.arrears_grace_days"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Collectes simultanées<input
                                v-model.number="programForm.max_simultaneous_collections"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Part urgence réservée (%)<input
                                v-model.number="programForm.emergency_queue_share_percent"
                                type="number"
                                min="0"
                                max="100"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Plancher de réserve<input
                                v-model.number="programForm.reserve_min_balance_minor"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Réciprocité minimale<input
                                v-model.number="programForm.reciprocity_min_score"
                                type="number"
                                min="0"
                                max="100"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs"
                            >Incidents avant réhabilitation<input
                                v-model.number="programForm.rehabilitation_incident_threshold"
                                type="number"
                                min="1"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                    </div>
                    <button
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold disabled:opacity-40"
                        :disabled="
                            busy ||
                            !programForm.name ||
                            !programForm.code ||
                            !programForm.membership_fee_minor ||
                            programForm.membership_fee_minor < 1
                        "
                        @click="createProgram"
                    >
                        {{
                            busy
                                ? 'Création…'
                                : pendingProgramId
                                  ? 'Reprendre et publier le programme'
                                  : 'Créer et publier le programme'
                        }}
                    </button>
                    <p v-if="pendingProgramId" class="text-wpx-muted-dark mt-2 text-center text-[11px]">
                        Le programme « {{ programForm.code }} » a déjà été créé ; corrigez les champs puis relancez pour
                        publier sa première version.
                    </p>
                </div>
            </div>

            <div
                v-if="showCategory"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showCategory = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex justify-between">
                        <h3 class="text-wpx-white-soft text-lg font-bold">Nouvelle catégorie</h3>
                        <button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="showCategory = false">×</button>
                    </div>
                    <input
                        v-model="categoryForm.name"
                        placeholder="Nom"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3"
                    /><input
                        v-model="categoryForm.code"
                        placeholder="code"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border px-3 py-3"
                    />
                    <div class="mt-2 grid grid-cols-[84px_1fr] gap-2">
                        <input
                            v-model="categoryForm.icon"
                            class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3"
                        /><input
                            v-model="categoryForm.description"
                            placeholder="Description courte"
                            class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3"
                        />
                    </div>
                    <button
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold"
                        :disabled="busy || !categoryForm.name || !categoryForm.code"
                        @click="createCategory"
                    >
                        Ajouter
                    </button>
                </div>
            </div>

            <div
                v-if="reviewWish"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="reviewWish = null"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-lg rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex justify-between">
                        <div>
                            <p class="text-wpx-cyan text-xs font-bold uppercase">Revue du vœu</p>
                            <h3 class="text-wpx-white-soft mt-1 text-lg font-bold">{{ reviewWish.title }}</h3>
                        </div>
                        <button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="reviewWish = null">×</button>
                    </div>
                    <select
                        v-model="reviewForm.decision"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3"
                    >
                        <option value="approve">Valider</option>
                        <option value="request_information">Demander des informations</option>
                        <option value="reject">Rejeter</option></select
                    ><textarea
                        v-model="reviewForm.note"
                        rows="4"
                        placeholder="Note de décision (obligatoire si complément ou rejet)"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border px-3 py-3"
                    ></textarea
                    ><button
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold"
                        :disabled="busy"
                        @click="submitReview"
                    >
                        Enregistrer la décision
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
