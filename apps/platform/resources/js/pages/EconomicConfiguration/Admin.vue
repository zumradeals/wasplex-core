<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

type ConfigurationState = 'draft' | 'approved' | 'published' | 'suspended';

type EconomicVersion = {
    id: string;
    version: number;
    state: ConfigurationState;
    publicName: string;
    quotaMonthly: number;
    weightBasisPoints: number;
    targetingCoefficientBasisPoints: number;
    fundEligible: boolean;
    effectiveFrom: string | null;
    effectiveTo: string | null;
    approvedAt: string | null;
    publishedAt: string | null;
    createdAt: string | null;
    suspensionReason: string | null;
};

type EconomicClass = {
    id: string;
    code: string;
    status: string;
    latest: EconomicVersion | null;
    published: EconomicVersion | null;
    versions: EconomicVersion[];
};

type Simulation = {
    total_basis_points: number;
    valid: boolean;
};

const props = defineProps<{
    classes: EconomicClass[];
    flash: {
        success: string | null;
        simulation: Simulation | null;
    };
}>();

const selectedClass = ref<EconomicClass | null>(null);
const actionBusy = ref<string | null>(null);
const suspensionReasons = reactive<Record<string, string>>({});
const simulationWeights = reactive<Record<string, number>>({});

const draftForm = useForm({
    public_name: '',
    quota_monthly: 0,
    weight_basis_points: 0,
    targeting_coefficient_basis_points: 10000,
    features: {
        fund_eligible: false,
    },
});

const simulationForm = useForm<{ weights: number[] }>({
    weights: [],
});

watch(
    () => props.classes,
    (classes) => {
        for (const economicClass of classes) {
            simulationWeights[economicClass.code] =
                economicClass.published?.weightBasisPoints ??
                economicClass.latest?.weightBasisPoints ??
                0;
        }
    },
    { immediate: true, deep: true },
);

const publishedCount = computed(
    () => props.classes.filter((economicClass) => economicClass.published !== null).length,
);
const pendingCount = computed(() =>
    props.classes.reduce(
        (total, economicClass) =>
            total +
            economicClass.versions.filter(
                (version) => version.state === 'draft' || version.state === 'approved',
            ).length,
        0,
    ),
);
const totalPublishedWeight = computed(() =>
    props.classes.reduce(
        (total, economicClass) => total + (economicClass.published?.weightBasisPoints ?? 0),
        0,
    ),
);
const simulatedTotal = computed(() =>
    props.classes.reduce(
        (total, economicClass) => total + Number(simulationWeights[economicClass.code] ?? 0),
        0,
    ),
);
const simulationIsValid = computed(() => simulatedTotal.value === 10000);

const percentage = (basisPoints: number) => `${(basisPoints / 100).toLocaleString('fr-FR')} %`;
const coefficient = (basisPoints: number) => (basisPoints / 10000).toLocaleString('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const stateLabel = (state: ConfigurationState) =>
    ({
        draft: 'Brouillon',
        approved: 'Approuvée',
        published: 'Publiée',
        suspended: 'Suspendue',
    })[state];

const stateClass = (state: ConfigurationState) =>
    ({
        draft: 'bg-white/8 text-white/60',
        approved: 'bg-wasplex-gold/10 text-wasplex-gold',
        published: 'bg-emerald-400/10 text-emerald-300',
        suspended: 'bg-red-400/10 text-red-300',
    })[state];

const openDraft = (economicClass: EconomicClass) => {
    const source = economicClass.latest ?? economicClass.published;

    selectedClass.value = economicClass;
    draftForm.clearErrors();
    draftForm.public_name = source?.publicName ?? economicClass.code;
    draftForm.quota_monthly = source?.quotaMonthly ?? 0;
    draftForm.weight_basis_points = source?.weightBasisPoints ?? 0;
    draftForm.targeting_coefficient_basis_points =
        source?.targetingCoefficientBasisPoints ?? 10000;
    draftForm.features.fund_eligible = source?.fundEligible ?? false;
};

const closeDraft = () => {
    selectedClass.value = null;
    draftForm.clearErrors();
};

const submitDraft = () => {
    if (!selectedClass.value) {
        return;
    }

    draftForm.post(`/administration/economie/classes/${selectedClass.value.id}/versions`, {
        preserveScroll: true,
        onSuccess: closeDraft,
    });
};

const runAction = (key: string, url: string, data: Record<string, unknown> = {}) => {
    actionBusy.value = key;
    router.post(url, data, {
        preserveScroll: true,
        onFinish: () => {
            actionBusy.value = null;
        },
    });
};

const approve = (version: EconomicVersion) => {
    runAction(
        `approve-${version.id}`,
        `/administration/economie/versions/${version.id}/approuver`,
    );
};

const publish = (version: EconomicVersion) => {
    if (!window.confirm('Publier cette version économique dans Wasplex ?')) {
        return;
    }

    runAction(
        `publish-${version.id}`,
        `/administration/economie/versions/${version.id}/publier`,
    );
};

const suspend = (version: EconomicVersion) => {
    const reason = suspensionReasons[version.id]?.trim();

    if (!reason || !window.confirm('Suspendre cette version publiée ?')) {
        return;
    }

    runAction(
        `suspend-${version.id}`,
        `/administration/economie/versions/${version.id}/suspendre`,
        { reason },
    );
};

const verifySimulation = () => {
    simulationForm.weights = props.classes.map((economicClass) =>
        Number(simulationWeights[economicClass.code] ?? 0),
    );
    simulationForm.post('/administration/economie/simuler', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Configuration économique" />
    <main class="min-h-screen bg-[#030a12] text-white">
        <section class="mx-auto max-w-[1500px] px-6 py-8 lg:px-10 lg:py-12">
            <header
                class="flex flex-wrap items-end justify-between gap-6 border-b border-white/8 pb-7"
            >
                <div>
                    <WasplexMark />
                    <p
                        class="mt-8 text-xs font-black tracking-[0.22em] text-wasplex-orange uppercase"
                    >
                        P004 · Autorité économique
                    </p>
                    <h1 class="mt-2 text-4xl font-black">Configuration économique</h1>
                    <p class="mt-2 max-w-3xl text-white/40">
                        Versionner les classes, simuler leur répartition et publier uniquement des
                        décisions explicites et traçables.
                    </p>
                </div>
                <Link
                    href="/administration"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold transition hover:bg-white/5"
                >
                    Retour à la console
                </Link>
            </header>

            <div
                v-if="flash.success"
                class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.07] px-5 py-4 text-sm font-semibold text-emerald-200"
            >
                {{ flash.success }}
            </div>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="metric in [
                        { label: 'Classes canoniques', value: classes.length },
                        { label: 'Classes publiées', value: publishedCount },
                        { label: 'Versions en attente', value: pendingCount },
                        {
                            label: 'Poids publié total',
                            value: percentage(totalPublishedWeight),
                        },
                    ]"
                    :key="metric.label"
                    class="rounded-2xl border border-white/8 bg-white/[0.035] p-5"
                >
                    <p class="text-xs font-semibold text-white/35">{{ metric.label }}</p>
                    <p class="mt-4 text-3xl font-black">{{ metric.value }}</p>
                </article>
            </div>

            <div class="mt-7 grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                <section class="rounded-3xl border border-white/8 bg-white/[0.03] p-6 lg:p-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p
                                class="text-xs font-black tracking-[0.18em] text-wasplex-cyan uppercase"
                            >
                                Simulateur de répartition
                            </p>
                            <h2 class="mt-2 text-2xl font-black">Contrôle des 10 000 points</h2>
                            <p class="mt-2 text-sm leading-6 text-white/40">
                                Les quatre classes publiées doivent toujours totaliser exactement
                                100 %. Un point de pourcentage correspond à 100 points de base.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border px-4 py-3 text-right"
                            :class="
                                simulationIsValid
                                    ? 'border-emerald-400/20 bg-emerald-400/[0.07]'
                                    : 'border-red-400/20 bg-red-400/[0.07]'
                            "
                        >
                            <p class="text-xs font-bold text-white/40">Total simulé</p>
                            <p
                                class="mt-1 text-2xl font-black"
                                :class="simulationIsValid ? 'text-emerald-300' : 'text-red-300'"
                            >
                                {{ percentage(simulatedTotal) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <label
                            v-for="economicClass in classes"
                            :key="economicClass.id"
                            class="rounded-2xl border border-white/8 bg-black/10 p-4"
                        >
                            <span class="text-xs font-black text-white/45">
                                {{ economicClass.code }}
                            </span>
                            <input
                                v-model.number="simulationWeights[economicClass.code]"
                                type="number"
                                min="0"
                                max="10000"
                                step="100"
                                class="mt-3 w-full rounded-xl border border-white/10 bg-[#07111d] px-3 py-2 font-black outline-none focus:border-wasplex-cyan/50"
                            />
                            <span class="mt-2 block text-xs text-white/35">
                                {{ percentage(simulationWeights[economicClass.code] ?? 0) }}
                            </span>
                        </label>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
                        <p class="text-sm text-white/40">
                            La simulation ne publie rien. Elle vérifie uniquement la cohérence de
                            la répartition proposée.
                        </p>
                        <button
                            type="button"
                            class="rounded-xl bg-wasplex-cyan px-5 py-3 text-sm font-black text-[#03111a] disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="simulationForm.processing"
                            @click="verifySimulation"
                        >
                            {{ simulationForm.processing ? 'Vérification…' : 'Vérifier côté serveur' }}
                        </button>
                    </div>
                </section>

                <aside
                    class="rounded-3xl border border-wasplex-orange/15 bg-wasplex-orange/[0.05] p-6 lg:p-7"
                >
                    <p class="text-xs font-black tracking-[0.18em] text-wasplex-orange uppercase">
                        Gouvernance
                    </p>
                    <h2 class="mt-3 text-2xl font-black">Aucune modification silencieuse</h2>
                    <div class="mt-6 space-y-4 text-sm leading-6 text-white/45">
                        <p>
                            Une modification crée toujours une nouvelle version brouillon. La
                            version publiée reste intacte jusqu’à l’approbation et la publication.
                        </p>
                        <p>
                            Les prix et l’activation commerciale des plans payants restent hors
                            périmètre tant qu’une décision fondatrice n’a pas été enregistrée.
                        </p>
                        <p>
                            Une suspension exige un motif. Les anciennes versions demeurent
                            visibles dans l’historique.
                        </p>
                    </div>
                    <div
                        v-if="flash.simulation"
                        class="mt-6 rounded-2xl border border-white/10 bg-black/15 p-4"
                    >
                        <p class="text-xs font-bold text-white/40">Dernière vérification serveur</p>
                        <p
                            class="mt-2 font-black"
                            :class="flash.simulation.valid ? 'text-emerald-300' : 'text-red-300'"
                        >
                            {{ percentage(flash.simulation.total_basis_points) }} ·
                            {{ flash.simulation.valid ? 'VALIDE' : 'À CORRIGER' }}
                        </p>
                    </div>
                </aside>
            </div>

            <section class="mt-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.18em] text-wasplex-gold uppercase"
                        >
                            Classes économiques
                        </p>
                        <h2 class="mt-2 text-3xl font-black">FREE, PREMIUM, GOLD, PLATINUM</h2>
                    </div>
                    <p class="max-w-2xl text-sm leading-6 text-white/35">
                        Les valeurs affichées comme « publiées » sont celles actuellement utilisées
                        par le noyau économique.
                    </p>
                </div>

                <div class="mt-6 grid gap-6 xl:grid-cols-2">
                    <article
                        v-for="economicClass in classes"
                        :key="economicClass.id"
                        class="overflow-hidden rounded-3xl border border-white/8 bg-white/[0.03]"
                    >
                        <header class="flex items-center justify-between border-b border-white/8 p-6">
                            <div class="flex items-center gap-4">
                                <span
                                    class="grid size-12 place-items-center rounded-2xl bg-white/8 text-sm font-black text-wasplex-cyan"
                                >
                                    {{ economicClass.code.slice(0, 2) }}
                                </span>
                                <div>
                                    <p class="text-xs font-bold text-white/35">Classe canonique</p>
                                    <h3 class="mt-1 text-2xl font-black">{{ economicClass.code }}</h3>
                                </div>
                            </div>
                            <span
                                class="rounded-full px-3 py-1 text-xs font-black"
                                :class="
                                    economicClass.published
                                        ? 'bg-emerald-400/10 text-emerald-300'
                                        : 'bg-red-400/10 text-red-300'
                                "
                            >
                                {{ economicClass.published ? 'PUBLIÉE' : 'NON PUBLIÉE' }}
                            </span>
                        </header>

                        <div class="p-6">
                            <div v-if="economicClass.published" class="grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-black/15 p-4">
                                    <p class="text-xs text-white/35">Nom public</p>
                                    <p class="mt-2 font-black">
                                        {{ economicClass.published.publicName }}
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-black/15 p-4">
                                    <p class="text-xs text-white/35">Quota mensuel</p>
                                    <p class="mt-2 font-black">
                                        {{ economicClass.published.quotaMonthly.toLocaleString('fr-FR') }}
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-black/15 p-4">
                                    <p class="text-xs text-white/35">Poids</p>
                                    <p class="mt-2 font-black">
                                        {{ percentage(economicClass.published.weightBasisPoints) }}
                                    </p>
                                </div>
                                <div class="rounded-2xl bg-black/15 p-4">
                                    <p class="text-xs text-white/35">Coefficient ciblage</p>
                                    <p class="mt-2 font-black">
                                        ×{{
                                            coefficient(
                                                economicClass.published
                                                    .targetingCoefficientBasisPoints,
                                            )
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else
                                class="rounded-2xl border border-red-400/15 bg-red-400/[0.05] p-5 text-sm text-red-200"
                            >
                                Aucune version active n’est publiée pour cette classe.
                            </div>

                            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-white/35">
                                    Fonds :
                                    <span class="font-black text-white/65">
                                        {{
                                            economicClass.published?.fundEligible
                                                ? 'éligible'
                                                : 'non éligible'
                                        }}
                                    </span>
                                </p>
                                <button
                                    type="button"
                                    class="rounded-xl border border-wasplex-cyan/25 px-4 py-2 text-sm font-black text-wasplex-cyan transition hover:bg-wasplex-cyan/10"
                                    @click="openDraft(economicClass)"
                                >
                                    Préparer une nouvelle version
                                </button>
                            </div>

                            <div class="mt-6 border-t border-white/8 pt-5">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-black">Historique des versions</h4>
                                    <span class="text-xs text-white/30">
                                        {{ economicClass.versions.length }} affichée(s)
                                    </span>
                                </div>

                                <div class="mt-4 space-y-3">
                                    <div
                                        v-for="version in economicClass.versions"
                                        :key="version.id"
                                        class="rounded-2xl border border-white/8 bg-black/10 p-4"
                                    >
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-black">v{{ version.version }}</span>
                                                    <span
                                                        class="rounded-full px-2.5 py-1 text-[11px] font-black"
                                                        :class="stateClass(version.state)"
                                                    >
                                                        {{ stateLabel(version.state) }}
                                                    </span>
                                                </div>
                                                <p class="mt-2 text-sm text-white/45">
                                                    {{ version.publicName }} ·
                                                    {{ version.quotaMonthly.toLocaleString('fr-FR') }}
                                                    unités ·
                                                    {{ percentage(version.weightBasisPoints) }}
                                                </p>
                                                <p class="mt-1 text-xs text-white/25">
                                                    Créée {{ date(version.createdAt) }}
                                                </p>
                                                <p
                                                    v-if="version.suspensionReason"
                                                    class="mt-2 text-xs text-red-300/80"
                                                >
                                                    Motif : {{ version.suspensionReason }}
                                                </p>
                                            </div>

                                            <div class="flex flex-wrap gap-2">
                                                <button
                                                    v-if="version.state === 'draft'"
                                                    type="button"
                                                    class="rounded-lg bg-wasplex-gold/10 px-3 py-2 text-xs font-black text-wasplex-gold disabled:opacity-40"
                                                    :disabled="actionBusy !== null"
                                                    @click="approve(version)"
                                                >
                                                    {{
                                                        actionBusy === `approve-${version.id}`
                                                            ? 'Approbation…'
                                                            : 'Approuver'
                                                    }}
                                                </button>
                                                <button
                                                    v-if="version.state === 'approved'"
                                                    type="button"
                                                    class="rounded-lg bg-emerald-400/10 px-3 py-2 text-xs font-black text-emerald-300 disabled:opacity-40"
                                                    :disabled="actionBusy !== null"
                                                    @click="publish(version)"
                                                >
                                                    {{
                                                        actionBusy === `publish-${version.id}`
                                                            ? 'Publication…'
                                                            : 'Publier'
                                                    }}
                                                </button>
                                            </div>
                                        </div>

                                        <div
                                            v-if="version.state === 'published' && !version.effectiveTo"
                                            class="mt-4 flex flex-wrap gap-2 border-t border-white/8 pt-4"
                                        >
                                            <input
                                                v-model="suspensionReasons[version.id]"
                                                type="text"
                                                maxlength="500"
                                                placeholder="Motif obligatoire de suspension"
                                                class="min-w-0 flex-1 rounded-lg border border-white/10 bg-[#07111d] px-3 py-2 text-sm outline-none focus:border-red-400/40"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-lg bg-red-400/10 px-3 py-2 text-xs font-black text-red-300 disabled:opacity-40"
                                                :disabled="
                                                    actionBusy !== null ||
                                                    !suspensionReasons[version.id]?.trim()
                                                "
                                                @click="suspend(version)"
                                            >
                                                {{
                                                    actionBusy === `suspend-${version.id}`
                                                        ? 'Suspension…'
                                                        : 'Suspendre'
                                                }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </section>

        <div
            v-if="selectedClass"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/75 p-4 backdrop-blur-sm"
            @click.self="closeDraft"
        >
            <form
                class="my-8 w-full max-w-2xl rounded-3xl border border-white/10 bg-[#07111d] p-6 shadow-2xl lg:p-8"
                @submit.prevent="submitDraft"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.18em] text-wasplex-cyan uppercase"
                        >
                            Nouvelle version · {{ selectedClass.code }}
                        </p>
                        <h2 class="mt-2 text-3xl font-black">Créer un brouillon</h2>
                        <p class="mt-2 text-sm text-white/40">
                            La version actuellement publiée ne sera pas modifiée.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl border border-white/10 px-3 py-2 text-sm font-bold text-white/50"
                        @click="closeDraft"
                    >
                        Fermer
                    </button>
                </div>

                <div class="mt-7 grid gap-5 sm:grid-cols-2">
                    <label class="sm:col-span-2">
                        <span class="text-sm font-bold">Nom public</span>
                        <input
                            v-model="draftForm.public_name"
                            type="text"
                            maxlength="120"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/15 px-4 py-3 outline-none focus:border-wasplex-cyan/50"
                        />
                        <span class="mt-1 block text-xs text-red-300">
                            {{ draftForm.errors.public_name }}
                        </span>
                    </label>

                    <label>
                        <span class="text-sm font-bold">Quota mensuel</span>
                        <input
                            v-model.number="draftForm.quota_monthly"
                            type="number"
                            min="0"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/15 px-4 py-3 outline-none focus:border-wasplex-cyan/50"
                        />
                        <span class="mt-1 block text-xs text-red-300">
                            {{ draftForm.errors.quota_monthly }}
                        </span>
                    </label>

                    <label>
                        <span class="text-sm font-bold">Poids en points de base</span>
                        <input
                            v-model.number="draftForm.weight_basis_points"
                            type="number"
                            min="0"
                            max="10000"
                            step="100"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/15 px-4 py-3 outline-none focus:border-wasplex-cyan/50"
                        />
                        <span class="mt-1 block text-xs text-white/30">
                            {{ percentage(draftForm.weight_basis_points) }}
                        </span>
                        <span class="mt-1 block text-xs text-red-300">
                            {{ draftForm.errors.weight_basis_points }}
                        </span>
                    </label>

                    <label>
                        <span class="text-sm font-bold">Coefficient de ciblage</span>
                        <input
                            v-model.number="draftForm.targeting_coefficient_basis_points"
                            type="number"
                            min="1"
                            step="100"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/15 px-4 py-3 outline-none focus:border-wasplex-cyan/50"
                        />
                        <span class="mt-1 block text-xs text-white/30">
                            ×{{ coefficient(draftForm.targeting_coefficient_basis_points) }}
                        </span>
                        <span class="mt-1 block text-xs text-red-300">
                            {{ draftForm.errors.targeting_coefficient_basis_points }}
                        </span>
                    </label>

                    <label
                        class="flex items-center gap-3 rounded-2xl border border-white/8 bg-black/10 p-4"
                    >
                        <input
                            v-model="draftForm.features.fund_eligible"
                            type="checkbox"
                            class="size-5 accent-cyan-400"
                        />
                        <span>
                            <span class="block text-sm font-bold">Éligible au fonds</span>
                            <span class="mt-1 block text-xs text-white/35">
                                Active l’éligibilité économique, sans déclencher de paiement.
                            </span>
                        </span>
                    </label>
                </div>

                <div
                    v-if="Object.keys(draftForm.errors).length"
                    class="mt-5 rounded-2xl border border-red-400/15 bg-red-400/[0.05] p-4 text-sm text-red-200"
                >
                    Corrige les champs signalés avant d’enregistrer le brouillon.
                </div>

                <div class="mt-7 flex flex-wrap items-center justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-white/10 px-5 py-3 text-sm font-black"
                        @click="closeDraft"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-wasplex-cyan px-5 py-3 text-sm font-black text-[#03111a] disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="draftForm.processing"
                    >
                        {{ draftForm.processing ? 'Enregistrement…' : 'Créer le brouillon' }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
