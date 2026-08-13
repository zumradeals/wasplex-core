<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import FundBalanceCard from '@/Components/FundBalanceCard.vue';
import FundWishContribution from '@/Components/FundWishContribution.vue';
import FundRealizationStatusCard from '@/Components/FundRealizationStatusCard.vue';
import FundPilotageCard from '@/Components/FundPilotageCard.vue';

type Program = {
    id: string;
    name: string;
    code: string;
    version_id: string;
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

type Category = { id: string; name: string; icon: string | null; description: string | null };
type Membership = {
    id: string;
    status: string;
    program: { id: string; name: string; code: string };
    started_at: string;
    grace_ends_at: string | null;
    mandate: { is_active: boolean; personal_cap_minor: number; accepted_at: string } | null;
};
type Wish = {
    id: string;
    status: string;
    title: string;
    description: string;
    city: string | null;
    estimated_amount_minor: number | null;
    required_personal_contribution_minor: number | null;
    personal_contribution_minor: number;
    personal_contribution_remaining_minor: number | null;
    personal_contribution_progress_percent: number;
    personal_contribution_completed_at: string | null;
    personal_contributions: Array<{
        id: string;
        source: 'wallet' | 'fund_balance';
        amount_minor: number;
        created_at: string;
    }>;
    currency: string;
    review_note: string | null;
    category: { id: string; name: string; icon: string | null } | null;
};
type Overview = {
    eligible: boolean;
    membership: Membership | null;
    balances: { wallet_available_minor: number; fund_balance_minor: number; unit: string; wp_to_xof: number };
    programs: Program[];
    categories: Category[];
    wishes: Wish[];
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const data = ref<Overview | null>(null);
const selectedProgram = ref<Program | null>(null);
const personalCap = ref(0);
const acceptMandate = ref(false);
const showJoin = ref(false);
const showWish = ref(false);
const wish = ref({
    category_id: '',
    title: '',
    description: '',
    city: '',
    estimated_amount_minor: null as number | null,
});

const activeMembership = computed(() => data.value?.membership ?? null);
const wishes = computed(() => data.value?.wishes ?? []);

function money(value: number | null, currency = 'XOF'): string {
    if (value === null) return 'À définir';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

function statusLabel(status: string): string {
    return (
        {
            active: 'Actif',
            grace_period: 'Délai de grâce',
            suspended: 'Suspendu',
            draft: 'Brouillon',
            submitted: 'En vérification',
            needs_information: 'Information requise',
            approved: 'Validé',
            rejected: 'Non retenu',
        }[status] ?? status
    );
}

function statusClass(status: string): string {
    if (['active', 'approved'].includes(status)) return 'text-wpx-success-light bg-wpx-success/10';
    if (['grace_period', 'needs_information'].includes(status)) return 'text-wpx-gold bg-wpx-gold/10';
    if (['suspended', 'rejected'].includes(status)) return 'text-wpx-danger bg-wpx-danger/10';
    return 'text-wpx-blue bg-wpx-blue/10';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/funds');
        data.value = response.data;
    } catch {
        error.value = 'Impossible de charger Fonds pour le moment.';
    } finally {
        loading.value = false;
    }
}

function chooseProgram(program: Program): void {
    selectedProgram.value = program;
    personalCap.value = program.max_debit_minor ?? program.min_debit_minor;
    acceptMandate.value = false;
    showJoin.value = true;
}

async function join(): Promise<void> {
    if (!selectedProgram.value || !acceptMandate.value) return;
    busy.value = true;
    error.value = '';
    try {
        await http.post('/funds/membership', {
            program_id: selectedProgram.value.id,
            personal_cap_minor: personalCap.value,
            accept_mandate: true,
        });
        showJoin.value = false;
        await load();
    } catch {
        error.value = 'L’adhésion n’a pas pu être enregistrée.';
    } finally {
        busy.value = false;
    }
}

async function createWish(): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post('/funds/wishes', {
            ...wish.value,
            estimated_amount_minor: wish.value.estimated_amount_minor || null,
        });
        showWish.value = false;
        wish.value = { category_id: '', title: '', description: '', city: '', estimated_amount_minor: null };
        await load();
    } catch {
        error.value = 'Le brouillon n’a pas pu être créé.';
    } finally {
        busy.value = false;
    }
}

async function submitWish(item: Wish): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/funds/wishes/${item.id}/submit`);
        await load();
    } catch {
        error.value = 'Le vœu n’a pas pu être soumis.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4 pb-4">
        <section
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-border-dark rounded-wpx-xl relative overflow-hidden border bg-gradient-to-br p-5"
        >
            <div class="bg-wpx-cyan/8 absolute -top-12 -right-12 h-36 w-36 rounded-full blur-2xl"></div>
            <div class="relative">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-wpx-cyan text-[11px] font-bold tracking-[0.18em] uppercase">
                            Solidarité Wasplex
                        </p>
                        <h1 class="text-wpx-white-soft mt-1 text-2xl font-extrabold">Fonds</h1>
                        <p class="text-wpx-muted-dark mt-2 max-w-xs text-sm leading-relaxed">
                            Construis ton projet avec ton apport, puis avance avec la force d’une communauté organisée
                            et vérifiée.
                        </p>
                    </div>
                    <span
                        class="from-wpx-blue/20 to-wpx-cyan/10 border-wpx-cyan/20 flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border bg-gradient-to-br"
                    >
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="8" stroke="#2BC4DE" stroke-width="1.7" />
                            <path d="M8.5 12h7M12 8.5v7" stroke="#F2C14E" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>
            </div>
        </section>

        <div v-if="loading" class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-5 text-center">
            <p class="text-wpx-muted-dark text-sm">Chargement de votre espace Fonds…</p>
        </div>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger rounded-wpx-md px-3 py-2 text-sm">{{ error }}</p>

        <template v-if="!loading && data">
            <section v-if="activeMembership" class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-muted-dark text-[11px] font-bold tracking-wide uppercase">Mon programme</p>
                        <p class="text-wpx-white-soft mt-1 text-lg font-bold">{{ activeMembership.program.name }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[11px] font-bold"
                        :class="statusClass(activeMembership.status)"
                    >
                        {{ statusLabel(activeMembership.status) }}
                    </span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2.5">
                    <div class="bg-wpx-navy-950 rounded-wpx-lg p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Plafond personnel</p>
                        <p class="text-wpx-white-soft mt-1 text-sm font-bold">
                            {{ money(activeMembership.mandate?.personal_cap_minor ?? null) }}
                        </p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-wpx-lg p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Mandat</p>
                        <p
                            class="mt-1 text-sm font-bold"
                            :class="activeMembership.mandate?.is_active ? 'text-wpx-success-light' : 'text-wpx-danger'"
                        >
                            {{ activeMembership.mandate?.is_active ? 'Actif' : 'Inactif' }}
                        </p>
                    </div>
                </div>
                <p v-if="activeMembership.status === 'grace_period'" class="text-wpx-gold mt-3 text-xs leading-relaxed">
                    Votre abonnement Wasplex doit être renouvelé. Aucun nouveau vœu ni nouvel engagement Fonds n’est
                    créé pendant ce délai.
                </p>
                <button
                    v-if="activeMembership.status === 'active'"
                    type="button"
                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold"
                    @click="showWish = true"
                >
                    Déclarer un vœu
                </button>
            </section>

            <section
                v-else-if="!data.eligible"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-5 text-center"
            >
                <span class="bg-wpx-gold/10 mx-auto flex h-12 w-12 items-center justify-center rounded-full">🔒</span>
                <h2 class="text-wpx-white-soft mt-3 text-base font-bold">Fonds est réservé aux abonnés payants</h2>
                <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                    Active un abonnement Wasplex payant éligible pour rejoindre un programme Fonds.
                </p>
            </section>

            <section
                v-else-if="data.programs.length === 0"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-5 text-center"
            >
                <p class="text-wpx-white-soft font-bold">Les programmes arrivent bientôt</p>
                <p class="text-wpx-muted-dark mt-1 text-sm">
                    Aucun programme Fonds n’est actuellement ouvert aux nouvelles adhésions.
                </p>
            </section>

            <section v-else-if="!activeMembership" class="flex flex-col gap-3">
                <div>
                    <h2 class="text-wpx-white-soft text-base font-bold">Choisis ton programme</h2>
                    <p class="text-wpx-muted-dark mt-1 text-xs">
                        Les règles et plafonds restent visibles avant toute adhésion.
                    </p>
                </div>
                <button
                    v-for="program in data.programs"
                    :key="program.id"
                    type="button"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4 text-left transition active:scale-[0.99]"
                    @click="chooseProgram(program)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-wpx-white-soft text-lg font-bold">{{ program.name }}</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-xs">
                                {{ program.max_wishes_per_period }} vœu(x) · apport
                                {{ program.personal_contribution_percent }}%
                            </p>
                        </div>
                        <span class="text-wpx-gold text-sm font-extrabold">Choisir ›</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div class="bg-wpx-navy-950 rounded-lg p-2.5">
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Contribution</p>
                            <p class="text-wpx-white-soft mt-1 text-xs font-bold">
                                {{ money(program.min_debit_minor) }} min.
                            </p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-2.5">
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Préavis</p>
                            <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ program.notice_hours }} h</p>
                        </div>
                        <div class="bg-wpx-navy-950 rounded-lg p-2.5">
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Grâce</p>
                            <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ program.grace_period_days }} j</p>
                        </div>
                    </div>
                </button>
            </section>

            <FundBalanceCard v-if="activeMembership" :balances="data.balances" @refresh="load" />

            <FundRealizationStatusCard v-if="activeMembership" />

            <FundPilotageCard v-if="activeMembership" />

            <section v-if="wishes.length" class="flex flex-col gap-2.5">
                <div class="flex items-center justify-between">
                    <h2 class="text-wpx-white-soft text-base font-bold">Mes vœux</h2>
                    <span class="text-wpx-muted-dark text-xs">{{ wishes.length }}</span>
                </div>
                <article
                    v-for="item in wishes"
                    :key="item.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">
                                {{ item.category?.icon }} {{ item.category?.name }}
                            </p>
                            <h3 class="text-wpx-white-soft mt-1 truncate text-sm font-bold">{{ item.title }}</h3>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold"
                            :class="statusClass(item.status)"
                            >{{ statusLabel(item.status) }}</span
                        >
                    </div>
                    <div
                        v-if="item.estimated_amount_minor"
                        class="mt-3 flex items-center justify-between border-t border-white/5 pt-3 text-xs"
                    >
                        <span class="text-wpx-muted-dark">Budget estimé</span>
                        <span class="text-wpx-white-soft font-bold">{{
                            money(item.estimated_amount_minor, item.currency)
                        }}</span>
                    </div>
                    <FundWishContribution
                        v-if="item.status !== 'draft' && item.status !== 'rejected'"
                        :wish="item"
                        :balances="data.balances"
                        @refresh="load"
                    />
                    <p v-if="item.review_note" class="text-wpx-gold mt-2 text-xs">{{ item.review_note }}</p>
                    <button
                        v-if="item.status === 'draft' || item.status === 'needs_information'"
                        type="button"
                        class="text-wpx-cyan mt-3 text-xs font-bold"
                        :disabled="busy"
                        @click="submitWish(item)"
                    >
                        Envoyer pour vérification ›
                    </button>
                </article>
            </section>
        </template>

        <Teleport to="body">
            <div
                v-if="showJoin && selectedProgram"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showJoin = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-cyan text-xs font-bold uppercase">Adhésion Fonds</p>
                            <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">{{ selectedProgram.name }}</h2>
                        </div>
                        <button
                            class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full"
                            @click="showJoin = false"
                        >
                            ×
                        </button>
                    </div>
                    <div class="bg-wpx-navy-850 mt-4 rounded-xl p-4">
                        <p class="text-wpx-white-soft text-sm font-bold">Ce que vous autorisez</p>
                        <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                            Wasplex pourra débiter automatiquement votre Solde Fonds lorsqu’une collecte éligible est
                            programmée, sans confirmation à chaque vœu, dans la limite du plafond choisi. Vous serez
                            informé avant le débit.
                        </p>
                    </div>
                    <label class="text-wpx-muted-dark mt-4 block text-xs font-semibold"
                        >Mon plafond par contribution</label
                    >
                    <input
                        v-model.number="personalCap"
                        type="number"
                        :min="selectedProgram.min_debit_minor"
                        :max="selectedProgram.max_debit_minor ?? undefined"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    />
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                        Minimum {{ money(selectedProgram.min_debit_minor)
                        }}<template v-if="selectedProgram.max_debit_minor">
                            · maximum {{ money(selectedProgram.max_debit_minor) }}</template
                        >
                    </p>
                    <label class="mt-4 flex items-start gap-3">
                        <input v-model="acceptMandate" type="checkbox" class="mt-1" />
                        <span class="text-wpx-muted-dark text-xs leading-relaxed"
                            >J’accepte le mandat de contributions automatiques plafonnées et les règles de ce
                            programme.</span
                        >
                    </label>
                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold disabled:opacity-40"
                        :disabled="busy || !acceptMandate"
                        @click="join"
                    >
                        Confirmer mon adhésion
                    </button>
                </div>
            </div>

            <div
                v-if="showWish"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showWish = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-cyan text-xs font-bold uppercase">Mon projet</p>
                            <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Déclarer un vœu</h2>
                        </div>
                        <button
                            class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full"
                            @click="showWish = false"
                        >
                            ×
                        </button>
                    </div>
                    <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                        Commence par l’essentiel. Le dossier pourra être complété et vérifié avant toute collecte.
                    </p>
                    <label class="text-wpx-muted-dark mt-4 block text-xs font-semibold">Catégorie</label>
                    <select
                        v-model="wish.category_id"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    >
                        <option value="" disabled>Choisir une catégorie</option>
                        <option v-for="category in data?.categories ?? []" :key="category.id" :value="category.id">
                            {{ category.icon }} {{ category.name }}
                        </option>
                    </select>
                    <label class="text-wpx-muted-dark mt-3 block text-xs font-semibold">Titre</label>
                    <input
                        v-model="wish.title"
                        maxlength="120"
                        placeholder="Ex. Une moto pour mon activité"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    />
                    <label class="text-wpx-muted-dark mt-3 block text-xs font-semibold">Explique ton besoin</label>
                    <textarea
                        v-model="wish.description"
                        rows="4"
                        placeholder="Décris simplement le besoin, son utilité et ta situation."
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    ></textarea>
                    <div class="mt-3 grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="text-wpx-muted-dark block text-xs font-semibold">Ville</label>
                            <input
                                v-model="wish.city"
                                placeholder="Abidjan"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                            />
                        </div>
                        <div>
                            <label class="text-wpx-muted-dark block text-xs font-semibold">Budget estimé</label>
                            <input
                                v-model.number="wish.estimated_amount_minor"
                                type="number"
                                min="1"
                                placeholder="FCFA"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                            />
                        </div>
                    </div>
                    <button
                        type="button"
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-extrabold disabled:opacity-40"
                        :disabled="busy || !wish.category_id || wish.title.length < 2 || wish.description.length < 20"
                        @click="createWish"
                    >
                        Enregistrer mon brouillon
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
