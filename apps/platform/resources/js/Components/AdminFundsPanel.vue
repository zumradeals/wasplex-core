<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

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
};
type Program = { id: string; code: string; name: string; status: string; versions: ProgramVersion[] };
type Category = { id: string; code: string; name: string; icon: string | null; is_active: boolean };
type Wish = {
    id: string;
    status: string;
    title: string;
    description: string;
    estimated_amount_minor: number | null;
    currency: string;
    review_note: string | null;
    category?: { name: string; icon: string | null };
    membership?: { program?: { name: string } };
};
type Dashboard = {
    programs: Program[];
    categories: Category[];
    wishes: Wish[];
    metrics: { active_programs: number; submitted_wishes: number; approved_wishes: number };
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const dashboard = ref<Dashboard | null>(null);
const showProgram = ref(false);
const showCategory = ref(false);
const editingProgram = ref<Program | null>(null);
const reviewWish = ref<Wish | null>(null);

const programForm = ref({
    code: '',
    name: '',
    membership_fee_minor: 0,
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
});
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
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Impossible de charger le pilotage Fonds.';
    } finally {
        loading.value = false;
    }
}

function resetProgram(): void {
    editingProgram.value = null;
    programForm.value = {
        code: '',
        name: '',
        membership_fee_minor: 0,
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
    };
    showProgram.value = true;
}

async function createProgram(): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        const created = await http.post('/admin/funds/programs', {
            code: programForm.value.code,
            name: programForm.value.name,
        });
        const version = await http.post(`/admin/funds/programs/${created.data.id}/versions`, {
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
        });
        await http.post(`/admin/funds/program-versions/${version.data.id}/publish`);
        showProgram.value = false;
        await load();
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Le programme n’a pas pu être créé.';
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
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'Le statut du programme n’a pas pu être changé.';
    } finally {
        busy.value = false;
    }
}

async function createCategory(): Promise<void> {
    busy.value = true;
    try {
        await http.post('/admin/funds/categories', categoryForm.value);
        categoryForm.value = { code: '', name: '', icon: '🎯', description: '' };
        showCategory.value = false;
        await load();
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'La catégorie n’a pas pu être créée.';
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
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? 'La décision n’a pas pu être enregistrée.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5">
        <section class="from-wpx-navy-750 to-wpx-navy-950 border-wpx-border-dark rounded-wpx-xl border bg-gradient-to-br p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-bold tracking-[0.16em] uppercase">Pilotage Fonds</p>
                    <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Programmes, catégories et vœux</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm">
                        Configurez le produit sans toucher au code. Les nouvelles règles sont versionnées ; les engagements existants restent liés à la version acceptée.
                    </p>
                </div>
                <div class="flex gap-2">
                    <button class="border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-2 text-xs font-bold" @click="showCategory = true">+ Catégorie</button>
                    <button class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-2 text-xs font-extrabold" @click="resetProgram">+ Programme</button>
                </div>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger rounded-lg px-3 py-2 text-sm">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-if="dashboard && !loading">
            <section class="grid gap-3 sm:grid-cols-3">
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Programmes actifs</p>
                    <p class="text-wpx-white-soft mt-2 text-2xl font-extrabold">{{ dashboard.metrics.active_programs }}</p>
                </div>
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Vœux à vérifier</p>
                    <p class="text-wpx-gold mt-2 text-2xl font-extrabold">{{ dashboard.metrics.submitted_wishes }}</p>
                </div>
                <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <p class="text-wpx-muted-dark text-xs">Vœux validés</p>
                    <p class="text-wpx-success-light mt-2 text-2xl font-extrabold">{{ dashboard.metrics.approved_wishes }}</p>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-wpx-white-soft font-bold">Programmes</h3>
                        <p class="text-wpx-muted-dark text-xs">Activation et paramètres visibles par les membres.</p>
                    </div>
                </div>
                <div v-if="dashboard.programs.length === 0" class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-5 text-center text-sm text-wpx-muted-dark">Aucun programme. Créez le premier programme Fonds.</div>
                <article v-for="program in dashboard.programs" :key="program.id" class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-wpx-white-soft text-base font-bold">{{ program.name }}</h4>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold" :class="program.status === 'active' ? 'bg-wpx-success/10 text-wpx-success-light' : 'bg-white/5 text-wpx-muted-dark'">{{ program.status === 'active' ? 'Actif' : program.status === 'disabled' ? 'Désactivé' : 'Brouillon' }}</span>
                            </div>
                            <p class="text-wpx-muted-dark mt-1 font-mono text-[11px]">{{ program.code }}</p>
                        </div>
                        <button v-if="program.status !== 'draft'" class="border-wpx-border-dark rounded-lg border px-3 py-2 text-xs font-bold" :class="program.status === 'active' ? 'text-wpx-danger' : 'text-wpx-cyan'" :disabled="busy" @click="toggleProgram(program)">{{ program.status === 'active' ? 'Désactiver' : 'Activer' }}</button>
                    </div>
                    <div v-if="program.versions[0]" class="mt-4 grid gap-2 sm:grid-cols-4">
                        <div class="bg-wpx-navy-950 rounded-lg p-3"><p class="text-wpx-muted-dark text-[10px] uppercase">Apport</p><p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ program.versions[0].personal_contribution_percent }}%</p></div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3"><p class="text-wpx-muted-dark text-[10px] uppercase">Débit</p><p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ money(program.versions[0].min_debit_minor) }} – {{ money(program.versions[0].max_debit_minor) }}</p></div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3"><p class="text-wpx-muted-dark text-[10px] uppercase">Frais Wasplex</p><p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ money(program.versions[0].wasplex_fee_minor) }}</p></div>
                        <div class="bg-wpx-navy-950 rounded-lg p-3"><p class="text-wpx-muted-dark text-[10px] uppercase">Grâce</p><p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ program.versions[0].grace_period_days }} jours</p></div>
                    </div>
                </article>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h3 class="text-wpx-white-soft font-bold">Catégories de vœux</h3>
                    <p class="text-wpx-muted-dark text-xs">Administrables : rien n’est figé dans l’interface.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="category in dashboard.categories" :key="category.id" type="button" class="border-wpx-border-dark rounded-full border px-3 py-2 text-xs font-bold" :class="category.is_active ? 'bg-wpx-blue/10 text-wpx-cyan' : 'text-wpx-muted-dark opacity-60'" @click="toggleCategory(category)">{{ category.icon }} {{ category.name }}</button>
                    <span v-if="dashboard.categories.length === 0" class="text-wpx-muted-dark text-sm">Aucune catégorie configurée.</span>
                </div>
            </section>

            <section class="flex flex-col gap-3">
                <div>
                    <h3 class="text-wpx-white-soft font-bold">Vœux à vérifier</h3>
                    <p class="text-wpx-muted-dark text-xs">Aucune validation automatique : chaque état doit correspondre à une décision réelle.</p>
                </div>
                <div v-if="pendingWishes.length === 0" class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-5 text-center text-sm text-wpx-muted-dark">Aucun vœu en attente.</div>
                <article v-for="wish in pendingWishes" :key="wish.id" class="bg-wpx-navy-850 border-wpx-border-dark rounded-xl border p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-wpx-cyan text-[10px] font-bold uppercase">{{ wish.category?.icon }} {{ wish.category?.name }} · {{ wish.membership?.program?.name }}</p>
                            <h4 class="text-wpx-white-soft mt-1 text-base font-bold">{{ wish.title }}</h4>
                            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm leading-relaxed">{{ wish.description }}</p>
                            <p v-if="wish.estimated_amount_minor" class="text-wpx-gold mt-2 text-sm font-bold">{{ money(wish.estimated_amount_minor, wish.currency) }}</p>
                        </div>
                        <button class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-lg bg-gradient-to-r px-3 py-2 text-xs font-extrabold" @click="openReview(wish)">Examiner</button>
                    </div>
                </article>
            </section>
        </template>

        <Teleport to="body">
            <div v-if="showProgram" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="showProgram = false">
                <div class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl">
                    <div class="flex items-start justify-between gap-3"><div><p class="text-wpx-cyan text-xs font-bold uppercase">Configuration</p><h3 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Nouveau programme Fonds</h3></div><button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="showProgram = false">×</button></div>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="text-wpx-muted-dark text-xs">Nom<input v-model="programForm.name" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" placeholder="Ex. Gold" /></label>
                        <label class="text-wpx-muted-dark text-xs">Code<input v-model="programForm.code" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" placeholder="gold" /></label>
                        <label class="text-wpx-muted-dark text-xs">Apport personnel (%)<input v-model.number="programForm.personal_contribution_percent" type="number" min="0" max="100" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Vœux / période<input v-model.number="programForm.max_wishes_per_period" type="number" min="1" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Montant max d’un vœu<input v-model.number="programForm.max_wish_amount_minor" type="number" min="1" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Plafond mensuel<input v-model.number="programForm.monthly_cap_minor" type="number" min="0" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Contribution min.<input v-model.number="programForm.min_debit_minor" type="number" min="0" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Contribution max.<input v-model.number="programForm.max_debit_minor" type="number" min="0" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Frais Wasplex / débit<input v-model.number="programForm.wasplex_fee_minor" type="number" min="0" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Préavis (heures)<input v-model.number="programForm.notice_hours" type="number" min="1" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                        <label class="text-wpx-muted-dark text-xs">Délai de grâce (jours)<input v-model.number="programForm.grace_period_days" type="number" min="1" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3" /></label>
                    </div>
                    <button class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold disabled:opacity-40" :disabled="busy || !programForm.name || !programForm.code" @click="createProgram">Créer et publier le programme</button>
                </div>
            </div>

            <div v-if="showCategory" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="showCategory = false"><div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl"><div class="flex justify-between"><h3 class="text-wpx-white-soft text-lg font-bold">Nouvelle catégorie</h3><button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="showCategory = false">×</button></div><input v-model="categoryForm.name" placeholder="Nom" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3" /><input v-model="categoryForm.code" placeholder="code" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border px-3 py-3" /><div class="mt-2 grid grid-cols-[84px_1fr] gap-2"><input v-model="categoryForm.icon" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3" /><input v-model="categoryForm.description" placeholder="Description courte" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3" /></div><button class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold" :disabled="busy || !categoryForm.name || !categoryForm.code" @click="createCategory">Ajouter</button></div></div>

            <div v-if="reviewWish" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="reviewWish = null"><div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-lg rounded-t-3xl border p-5 sm:rounded-3xl"><div class="flex justify-between"><div><p class="text-wpx-cyan text-xs font-bold uppercase">Revue du vœu</p><h3 class="text-wpx-white-soft mt-1 text-lg font-bold">{{ reviewWish.title }}</h3></div><button class="bg-wpx-navy-750 h-9 w-9 rounded-full" @click="reviewWish = null">×</button></div><select v-model="reviewForm.decision" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3"><option value="approve">Valider</option><option value="request_information">Demander des informations</option><option value="reject">Rejeter</option></select><textarea v-model="reviewForm.note" rows="4" placeholder="Note de décision (obligatoire si complément ou rejet)" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border px-3 py-3"></textarea><button class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold" :disabled="busy" @click="submitReview">Enregistrer la décision</button></div></div>
        </Teleport>
    </div>
</template>
