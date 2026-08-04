<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Option = {
    value: string;
    label: string;
};

type Taxonomy = {
    code: string;
    category: string;
    label: string;
    valueType: string;
    options: Option[];
    purposeCodes: string[];
};

type SegmentRule = {
    taxonomyCode: string;
    taxonomyLabel: string;
    operator: 'equals' | 'in';
    values: string[];
    required: boolean;
};

type Segment = {
    id: string;
    campaignId: string;
    campaignVersionId: string;
    version: number;
    status: string;
    ruleVersion: string;
    minimumSize: number;
    rules: SegmentRule[];
    membersExported: false;
};

type Estimate = {
    id: string;
    status: 'available' | 'withheld';
    threshold: number;
    bucket: string;
    approximateCount: number | null;
    protectedRange: { min: number | null; max: number | null } | null;
    reasonCode: string | null;
    estimatedAt: string;
    membersExported: false;
};

type RuleInput = {
    taxonomy_code: string;
    operator: 'equals';
    values: string[];
    required: boolean;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaign: {
        id: string;
        name: string;
        status: string;
        brand: { id: string; name: string };
        version: {
            territoryName: string | null;
            radiusKm: number | null;
            selectedClasses: string[];
        };
    };
    taxonomies: Taxonomy[];
    segment: Segment | null;
    estimate: Estimate | null;
    privacy: {
        membersExported: false;
        minimumSegmentSize: number;
        roundingStep: number;
    };
    flash: { success: string | null };
}>();

const existingRules = new Map(
    (props.segment?.rules ?? []).map((rule) => [rule.taxonomyCode, rule] as const),
);

const form = useForm<{ rules: RuleInput[] }>({
    rules: props.taxonomies.map((taxonomy) => ({
        taxonomy_code: taxonomy.code,
        operator: 'equals',
        values: existingRules.get(taxonomy.code)?.values ?? [],
        required: existingRules.get(taxonomy.code)?.required ?? true,
    })),
});

const editable = computed(() =>
    ['draft', 'quoted', 'changes_requested'].includes(props.campaign.status),
);
const selectedRules = computed(() => form.rules.filter((rule) => rule.values.length > 0));
const canEstimate = computed(() => props.segment !== null && !form.processing);

const select = (rule: RuleInput, value: string) => {
    if (!editable.value) return;
    rule.values = [value];
};

const clear = (rule: RuleInput) => {
    if (!editable.value) return;
    rule.values = [];
};

const saveSegment = () => {
    form.transform(() => ({ rules: selectedRules.value })).post(
        `/studio/campagnes/${props.campaign.id}/ciblage`,
        {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        },
    );
};

const estimate = () => {
    if (!canEstimate.value) return;
    router.post(
        `/studio/campagnes/${props.campaign.id}/ciblage/estimation`,
        {},
        {
            preserveScroll: true,
        },
    );
};

const number = (value: number | null) =>
    value === null ? '—' : new Intl.NumberFormat('fr-FR').format(value);

const date = (value: string) =>
    new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));

const categoryLabel = (category: string) =>
    ({
        usage: 'Usage déclaré',
        interest: 'Intérêt volontaire',
        territory: 'Zone approximative',
        project: 'Projet déclaré',
    })[category] ?? 'Critère autorisé';
</script>

<template>
    <Head :title="`Ciblage — ${campaign.name}`" />
    <StudioLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <Link
                    :href="`/studio/campagnes/${campaign.id}/modifier`"
                    class="text-xs font-black text-cyan-300/70"
                >
                    ← Revenir à la campagne
                </Link>
                <p class="mt-5 text-xs font-black tracking-[0.22em] text-cyan-300 uppercase">
                    Matching protégé P008
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Segment de {{ campaign.name }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/42">
                    Sélectionnez seulement des critères autorisés. Wasplex comparera ces critères
                    aux choix volontaires des utilisateurs sans vous transmettre leur identité,
                    leurs réponses ni une liste de membres.
                </p>
            </div>
            <span
                class="w-fit rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-black text-white/55"
            >
                {{ campaign.brand.name }} · {{ campaign.status }}
            </span>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/8 px-5 py-4 text-sm font-bold text-emerald-100"
        >
            {{ flash.success }}
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
            <section class="space-y-5">
                <article
                    class="rounded-[2rem] border border-cyan-300/15 bg-cyan-300/[0.045] p-5 sm:p-7"
                >
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p
                                class="text-[0.68rem] font-black tracking-widest text-white/35 uppercase"
                            >
                                Classes
                            </p>
                            <p class="mt-2 font-black">
                                {{ campaign.version.selectedClasses.join(', ') || 'Toutes' }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[0.68rem] font-black tracking-widest text-white/35 uppercase"
                            >
                                Territoire déclaré
                            </p>
                            <p class="mt-2 font-black">
                                {{ campaign.version.territoryName || 'Non défini' }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[0.68rem] font-black tracking-widest text-white/35 uppercase"
                            >
                                Rayon
                            </p>
                            <p class="mt-2 font-black">
                                {{
                                    campaign.version.radiusKm
                                        ? `${campaign.version.radiusKm} km`
                                        : '—'
                                }}
                            </p>
                        </div>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-white/35">
                        Les classes et la période de la campagne restent des conditions obligatoires
                        du Matching, même lorsqu’aucun critère SmartProfile n’est affiché ici.
                    </p>
                </article>

                <article
                    v-for="(taxonomy, index) in taxonomies"
                    :key="taxonomy.code"
                    class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p
                                class="text-[0.68rem] font-black tracking-[0.15em] text-amber-200 uppercase"
                            >
                                {{ categoryLabel(taxonomy.category) }}
                            </p>
                            <h2 class="mt-2 text-xl font-black">{{ taxonomy.label }}</h2>
                            <p class="mt-2 text-xs leading-5 text-white/32">
                                Finalité(s) requise(s) : {{ taxonomy.purposeCodes.join(', ') }}
                            </p>
                        </div>
                        <button
                            v-if="form.rules[index].values.length"
                            type="button"
                            class="text-xs font-black text-rose-300/75"
                            :disabled="!editable"
                            @click="clear(form.rules[index])"
                        >
                            Ne pas utiliser
                        </button>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="option in taxonomy.options"
                            :key="option.value"
                            type="button"
                            class="rounded-2xl border px-4 py-3 text-left text-sm font-black transition"
                            :class="
                                form.rules[index].values[0] === option.value
                                    ? 'border-cyan-300/55 bg-cyan-300/12 text-cyan-100'
                                    : 'border-white/8 bg-black/15 text-white/48 hover:border-white/20 hover:text-white'
                            "
                            :disabled="!editable"
                            @click="select(form.rules[index], option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </article>

                <div
                    v-if="form.errors.rules"
                    class="rounded-2xl border border-rose-300/20 bg-rose-300/8 px-5 py-4 text-sm font-bold text-rose-200"
                >
                    {{ form.errors.rules }}
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-black text-white/55 disabled:cursor-not-allowed disabled:opacity-35"
                        :disabled="!editable || selectedRules.length === 0 || form.processing"
                        @click="saveSegment"
                    >
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer le segment' }}
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e] disabled:cursor-not-allowed disabled:opacity-35"
                        :disabled="!canEstimate"
                        @click="estimate"
                    >
                        Calculer l’estimation protégée
                    </button>
                </div>
            </section>

            <aside class="space-y-5">
                <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                        Segment actif
                    </p>
                    <template v-if="segment">
                        <p class="mt-3 text-3xl font-black">{{ segment.rules.length }}</p>
                        <p class="mt-1 text-sm text-white/40">critère(s) protégé(s)</p>
                        <div class="mt-5 space-y-2">
                            <div
                                v-for="rule in segment.rules"
                                :key="rule.taxonomyCode"
                                class="rounded-2xl bg-black/15 px-3 py-3"
                            >
                                <p class="text-xs font-black">{{ rule.taxonomyLabel }}</p>
                                <p class="mt-1 text-[0.68rem] text-white/35">
                                    {{ rule.values.join(', ') }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-4 text-[0.68rem] text-white/28">
                            Version {{ segment.version }} · seuil minimum {{ segment.minimumSize }}
                        </p>
                    </template>
                    <p v-else class="mt-4 text-sm leading-6 text-white/38">
                        Aucun segment n’est encore enregistré pour la version active de la campagne.
                    </p>
                </article>

                <article
                    class="rounded-[2rem] border p-6"
                    :class="
                        estimate?.status === 'available'
                            ? 'border-emerald-300/20 bg-emerald-300/[0.055]'
                            : 'border-amber-300/20 bg-amber-300/[0.045]'
                    "
                >
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                        Estimation agrégée
                    </p>
                    <template v-if="estimate?.status === 'available'">
                        <p class="mt-3 text-4xl font-black text-emerald-200">
                            ≈ {{ number(estimate.approximateCount) }}
                        </p>
                        <p class="mt-2 text-sm leading-6 text-white/45">
                            Fourchette protégée :
                            {{ number(estimate.protectedRange?.min ?? null) }} à
                            {{ number(estimate.protectedRange?.max ?? null) }} utilisateurs.
                        </p>
                        <p class="mt-3 text-xs text-white/28">
                            Calculée le {{ date(estimate.estimatedAt) }}
                        </p>
                    </template>
                    <template v-else-if="estimate?.status === 'withheld'">
                        <p class="mt-3 text-xl font-black text-amber-200">Volume masqué</p>
                        <p class="mt-2 text-sm leading-6 text-white/45">
                            Le segment est inférieur au seuil de confidentialité de
                            {{ estimate.threshold }}. Wasplex ne révèle aucun nombre précis.
                        </p>
                    </template>
                    <p v-else class="mt-3 text-sm leading-6 text-white/38">
                        Enregistrez le segment, puis lancez l’estimation.
                    </p>
                </article>

                <article class="rounded-[2rem] border border-rose-300/15 bg-rose-300/[0.035] p-6">
                    <p class="font-black text-rose-200">Barrière anti-réidentification</p>
                    <p class="mt-3 text-xs leading-5 text-white/38">
                        Aucun nom, compte, téléphone, réponse individuelle ou liste d’utilisateurs
                        n’est exporté. Les volumes disponibles sont arrondis par pas de
                        {{ privacy.roundingStep }} et masqués sous {{ privacy.minimumSegmentSize }}.
                    </p>
                </article>
            </aside>
        </div>
    </StudioLayout>
</template>
