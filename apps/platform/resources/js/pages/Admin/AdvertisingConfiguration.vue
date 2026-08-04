<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Configuration = {
    version: number | null;
    minimumSegmentSize: number;
    estimateRoundingStep: number;
    frequencyWindowHours: number;
    frequencyLimit: number;
    fatigueLimit: number;
    effectiveFrom: string | null;
};

type Purpose = {
    id: string;
    code: string;
    domain: string;
    status: 'active' | 'suspended';
    version: number | null;
    title: string;
    description: string;
    consentRequired: boolean;
    publishedAt: string | null;
};

type Taxonomy = {
    id: string;
    code: string;
    category: string;
    label: string;
    valueType: string;
    status: 'active' | 'suspended';
    allowedForTargeting: boolean;
    sensitive: boolean;
    question: string | null;
};

type Audit = {
    periodDays: number;
    generatedAt: string;
    matches: {
        total: number;
        eligible: number;
        ineligible: number;
        withheld: number;
        campaigns: number;
        sampleCapped: boolean;
    };
    scoreBands: { high: number; medium: number; low: number };
    topReasons: Array<{ code: string; count: number }>;
    estimates: { available: number; withheld: number };
    events: Array<{
        id: string;
        eventType: string;
        resourceType: string;
        resourceCode: string | null;
        beforeState: string | null;
        afterState: string | null;
        occurredAt: string;
        actorRecorded: boolean;
    }>;
    privacy: {
        subjectHashesReturned: false;
        accountIdsReturned: false;
        profilesReturned: false;
    };
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    configuration: Configuration;
    configurationHistory: Configuration[];
    purposes: Purpose[];
    taxonomies: Taxonomy[];
    audit: Audit;
    flash: { success: string | null };
}>();

const form = useForm({
    minimum_segment_size: props.configuration.minimumSegmentSize,
    estimate_rounding_step: props.configuration.estimateRoundingStep,
    frequency_window_hours: props.configuration.frequencyWindowHours,
    frequency_limit: props.configuration.frequencyLimit,
    fatigue_limit: props.configuration.fatigueLimit,
});

const hasChanges = computed(
    () =>
        form.minimum_segment_size !== props.configuration.minimumSegmentSize ||
        form.estimate_rounding_step !== props.configuration.estimateRoundingStep ||
        form.frequency_window_hours !== props.configuration.frequencyWindowHours ||
        form.frequency_limit !== props.configuration.frequencyLimit ||
        form.fatigue_limit !== props.configuration.fatigueLimit,
);

const publish = () => {
    if (!hasChanges.value || form.processing) return;
    if (!window.confirm('Publier cette nouvelle version des contrôles du Matching ?')) return;

    form.post('/administration/publicite/configuration', {
        preserveScroll: true,
    });
};

const reset = () => {
    form.minimum_segment_size = props.configuration.minimumSegmentSize;
    form.estimate_rounding_step = props.configuration.estimateRoundingStep;
    form.frequency_window_hours = props.configuration.frequencyWindowHours;
    form.frequency_limit = props.configuration.frequencyLimit;
    form.fatigue_limit = props.configuration.fatigueLimit;
    form.clearErrors();
};

const changePurposeStatus = (purpose: Purpose) => {
    const status = purpose.status === 'active' ? 'suspended' : 'active';
    const action = status === 'suspended' ? 'suspendre' : 'réactiver';

    if (!window.confirm(`${action} la finalité « ${purpose.title} » ?`)) return;

    router.patch(
        `/administration/publicite/finalites/${purpose.id}/statut`,
        { status },
        { preserveScroll: true },
    );
};

const changeTaxonomyStatus = (taxonomy: Taxonomy) => {
    const status = taxonomy.status === 'active' ? 'suspended' : 'active';
    const action = status === 'suspended' ? 'suspendre' : 'réactiver';

    if (!window.confirm(`${action} la taxonomie « ${taxonomy.label} » ?`)) return;

    router.patch(
        `/administration/publicite/taxonomies/${taxonomy.id}/statut`,
        { status },
        { preserveScroll: true },
    );
};

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Configuration de repli';

const category = (value: string) =>
    ({
        usage: 'Usage déclaré',
        interest: 'Intérêt volontaire',
        territory: 'Zone approximative',
        project: 'Projet déclaré',
    })[value] ?? value;

const eventLabel = (value: string) =>
    ({
        'advertising.configuration.published': 'Configuration publiée',
        'advertising.purpose.status_changed': 'Finalité modifiée',
        'advertising.taxonomy.status_changed': 'Taxonomie modifiée',
    })[value] ?? value;
</script>

<template>
    <Head title="Matching et confidentialité" />
    <AdminLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="advertising"
    >
        <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.22em] text-cyan-300 uppercase">
                    Administration P008
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Matching et confidentialité
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-white/42">
                    Pilote les seuils de confidentialité, la fréquence, la fatigue, les finalités et
                    les taxonomies autorisées. Aucune identité utilisateur ni réponse individuelle
                    n’est exposée dans cette console.
                </p>
            </div>
            <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.055] px-5 py-4">
                <p class="text-xs font-black text-emerald-200">Configuration active</p>
                <p class="mt-1 text-sm text-white/45">
                    Version {{ configuration.version ?? 'environnement' }} ·
                    {{ date(configuration.effectiveFrom) }}
                </p>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.07] px-5 py-4 text-sm font-bold text-emerald-100"
        >
            {{ flash.success }}
        </div>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <article class="rounded-2xl border border-white/8 bg-white/[0.035] p-5">
                <p class="text-xs font-black text-white/35 uppercase">Matchings · 30 jours</p>
                <p class="mt-3 text-3xl font-black">{{ number(audit.matches.total) }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.045] p-5">
                <p class="text-xs font-black text-emerald-200/65 uppercase">Éligibles</p>
                <p class="mt-3 text-3xl font-black text-emerald-200">
                    {{ number(audit.matches.eligible) }}
                </p>
            </article>
            <article class="rounded-2xl border border-rose-300/15 bg-rose-300/[0.04] p-5">
                <p class="text-xs font-black text-rose-200/65 uppercase">Inéligibles</p>
                <p class="mt-3 text-3xl font-black text-rose-200">
                    {{ number(audit.matches.ineligible) }}
                </p>
            </article>
            <article class="rounded-2xl border border-amber-300/15 bg-amber-300/[0.04] p-5">
                <p class="text-xs font-black text-amber-200/65 uppercase">Retenus</p>
                <p class="mt-3 text-3xl font-black text-amber-200">
                    {{ number(audit.matches.withheld) }}
                </p>
            </article>
            <article class="rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.04] p-5">
                <p class="text-xs font-black text-cyan-200/65 uppercase">Campagnes</p>
                <p class="mt-3 text-3xl font-black text-cyan-200">
                    {{ number(audit.matches.campaigns) }}
                </p>
            </article>
        </section>

        <section class="mt-7 grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_minmax(24rem,0.85fr)]">
            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-black tracking-widest text-cyan-300 uppercase">
                            Contrôles publiés
                        </p>
                        <h2 class="mt-2 text-2xl font-black">Seuils du moteur</h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-white/38">
                            Toute publication crée une nouvelle version atomique. Les anciens
                            matchings restent reproductibles avec leur version historique.
                        </p>
                    </div>
                    <span class="rounded-full bg-white/7 px-3 py-2 text-xs font-black text-white/45">
                        MFA récente obligatoire
                    </span>
                </div>

                <form class="mt-6 grid gap-5 sm:grid-cols-2" @submit.prevent="publish">
                    <label class="block">
                        <span class="text-sm font-black">Seuil minimal du segment</span>
                        <input
                            v-model.number="form.minimum_segment_size"
                            type="number"
                            min="5"
                            max="100000"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-black outline-none focus:border-cyan-300/55"
                        />
                        <span class="mt-2 block text-xs leading-5 text-white/30">
                            En dessous, le volume et le Matching sont masqués.
                        </span>
                        <span v-if="form.errors.minimum_segment_size" class="mt-2 block text-xs text-rose-300">
                            {{ form.errors.minimum_segment_size }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-black">Pas d’arrondi des estimations</span>
                        <input
                            v-model.number="form.estimate_rounding_step"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-black outline-none focus:border-cyan-300/55"
                        />
                        <span class="mt-2 block text-xs leading-5 text-white/30">
                            Aucun compte exact n’est présenté à l’annonceur.
                        </span>
                        <span v-if="form.errors.estimate_rounding_step" class="mt-2 block text-xs text-rose-300">
                            {{ form.errors.estimate_rounding_step }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="text-sm font-black">Fenêtre de fréquence · heures</span>
                        <input
                            v-model.number="form.frequency_window_hours"
                            type="number"
                            min="1"
                            max="168"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-black outline-none focus:border-cyan-300/55"
                        />
                    </label>

                    <label class="block">
                        <span class="text-sm font-black">Nombre maximal d’impressions</span>
                        <input
                            v-model.number="form.frequency_limit"
                            type="number"
                            min="1"
                            max="100"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-black outline-none focus:border-cyan-300/55"
                        />
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black">Seuil de fatigue de sécurité</span>
                        <input
                            v-model.number="form.fatigue_limit"
                            type="number"
                            min="1"
                            max="10000"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-black outline-none focus:border-cyan-300/55"
                        />
                        <span class="mt-2 block text-xs leading-5 text-white/30">
                            Au seuil, le moteur produit <code>withheld</code> sans consommer de quota.
                        </span>
                    </label>

                    <div class="flex flex-wrap justify-end gap-3 border-t border-white/8 pt-5 sm:col-span-2">
                        <button
                            type="button"
                            class="rounded-xl border border-white/10 px-5 py-3 text-sm font-black text-white/55 disabled:opacity-30"
                            :disabled="form.processing || !hasChanges"
                            @click="reset"
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-cyan-300 px-6 py-3 text-sm font-black text-[#04111e] disabled:opacity-30"
                            :disabled="form.processing || !hasChanges"
                        >
                            {{ form.processing ? 'Publication…' : 'Publier une nouvelle version' }}
                        </button>
                    </div>
                </form>
            </article>

            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                    Historique des contrôles
                </p>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="item in configurationHistory"
                        :key="item.version ?? item.effectiveFrom ?? 'fallback'"
                        class="rounded-2xl border border-white/7 bg-black/15 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-black">Version {{ item.version }}</p>
                            <p class="text-xs text-white/30">{{ date(item.effectiveFrom) }}</p>
                        </div>
                        <p class="mt-2 text-xs leading-5 text-white/38">
                            Seuil {{ item.minimumSegmentSize }} · arrondi {{ item.estimateRoundingStep }} ·
                            fréquence {{ item.frequencyLimit }}/{{ item.frequencyWindowHours }} h · fatigue
                            {{ item.fatigueLimit }}
                        </p>
                    </div>
                    <p v-if="configurationHistory.length === 0" class="text-sm text-white/38">
                        Aucune version en base. Les valeurs sûres de l’environnement sont utilisées
                        jusqu’à la première publication.
                    </p>
                </div>
            </article>
        </section>

        <section class="mt-7 grid gap-6 xl:grid-cols-2">
            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <div>
                    <p class="text-xs font-black tracking-widest text-amber-200 uppercase">
                        Finalités et consentements
                    </p>
                    <h2 class="mt-2 text-2xl font-black">Finalités publiées</h2>
                </div>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="purpose in purposes"
                        :key="purpose.id"
                        class="rounded-2xl border border-white/7 bg-black/15 p-5"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-black">{{ purpose.title }}</p>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[0.65rem] font-black uppercase"
                                        :class="
                                            purpose.status === 'active'
                                                ? 'bg-emerald-300/10 text-emerald-200'
                                                : 'bg-amber-300/10 text-amber-200'
                                        "
                                    >
                                        {{ purpose.status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs font-bold text-white/30">
                                    {{ purpose.code }} · version {{ purpose.version ?? '—' }}
                                </p>
                                <p class="mt-3 text-sm leading-6 text-white/42">
                                    {{ purpose.description }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-white/10 px-4 py-2 text-xs font-black text-white/55"
                                @click="changePurposeStatus(purpose)"
                            >
                                {{ purpose.status === 'active' ? 'Suspendre' : 'Réactiver' }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-cyan-200 uppercase">
                    Critères de ciblage
                </p>
                <h2 class="mt-2 text-2xl font-black">Taxonomies autorisées</h2>
                <div class="mt-5 space-y-3">
                    <div
                        v-for="taxonomy in taxonomies"
                        :key="taxonomy.id"
                        class="rounded-2xl border border-white/7 bg-black/15 p-5"
                    >
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-black">{{ taxonomy.label }}</p>
                                    <span class="rounded-full bg-white/7 px-2.5 py-1 text-[0.65rem] font-black text-white/45">
                                        {{ category(taxonomy.category) }}
                                    </span>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-[0.65rem] font-black uppercase"
                                        :class="
                                            taxonomy.status === 'active'
                                                ? 'bg-emerald-300/10 text-emerald-200'
                                                : 'bg-amber-300/10 text-amber-200'
                                        "
                                    >
                                        {{ taxonomy.status }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs font-bold text-white/30">{{ taxonomy.code }}</p>
                                <p class="mt-3 text-sm leading-6 text-white/42">
                                    {{ taxonomy.question || 'Aucune question publiée.' }}
                                </p>
                                <p v-if="taxonomy.sensitive || !taxonomy.allowedForTargeting" class="mt-2 text-xs font-black text-rose-300">
                                    Taxonomie non activable pour le ciblage.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-white/10 px-4 py-2 text-xs font-black text-white/55 disabled:cursor-not-allowed disabled:opacity-25"
                                :disabled="taxonomy.sensitive || !taxonomy.allowedForTargeting"
                                @click="changeTaxonomyStatus(taxonomy)"
                            >
                                {{ taxonomy.status === 'active' ? 'Suspendre' : 'Réactiver' }}
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)]">
            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                    Audit agrégé · {{ audit.periodDays }} jours
                </p>
                <h2 class="mt-2 text-2xl font-black">Décisions et estimations</h2>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-2xl bg-black/15 p-4">
                        <p class="text-xs text-white/35">Scores élevés</p>
                        <p class="mt-2 text-2xl font-black">{{ number(audit.scoreBands.high) }}</p>
                    </div>
                    <div class="rounded-2xl bg-black/15 p-4">
                        <p class="text-xs text-white/35">Scores moyens</p>
                        <p class="mt-2 text-2xl font-black">{{ number(audit.scoreBands.medium) }}</p>
                    </div>
                    <div class="rounded-2xl bg-black/15 p-4">
                        <p class="text-xs text-white/35">Estimations visibles</p>
                        <p class="mt-2 text-2xl font-black">{{ number(audit.estimates.available) }}</p>
                    </div>
                    <div class="rounded-2xl bg-black/15 p-4">
                        <p class="text-xs text-white/35">Estimations masquées</p>
                        <p class="mt-2 text-2xl font-black">{{ number(audit.estimates.withheld) }}</p>
                    </div>
                </div>

                <h3 class="mt-6 font-black">Principaux codes de décision</h3>
                <div class="mt-3 space-y-2">
                    <div
                        v-for="reason in audit.topReasons"
                        :key="reason.code"
                        class="flex items-center justify-between gap-4 rounded-xl bg-black/15 px-4 py-3"
                    >
                        <code class="text-xs text-white/50">{{ reason.code }}</code>
                        <span class="text-sm font-black">{{ number(reason.count) }}</span>
                    </div>
                    <p v-if="audit.topReasons.length === 0" class="text-sm text-white/35">
                        Aucun Matching audité pendant cette période.
                    </p>
                </div>
            </article>

            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                    Journal d’administration
                </p>
                <h2 class="mt-2 text-2xl font-black">Actions traçables</h2>
                <div class="mt-5 space-y-2">
                    <div
                        v-for="event in audit.events"
                        :key="event.id"
                        class="rounded-2xl border border-white/7 bg-black/15 p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-black">{{ eventLabel(event.eventType) }}</p>
                            <p class="text-xs text-white/30">{{ date(event.occurredAt) }}</p>
                        </div>
                        <p class="mt-2 text-xs text-white/40">
                            {{ event.resourceType }} · {{ event.resourceCode || 'ressource interne' }} ·
                            {{ event.beforeState || '—' }} → {{ event.afterState || '—' }}
                        </p>
                        <p class="mt-1 text-[0.68rem] text-white/25">
                            Auteur interne enregistré : {{ event.actorRecorded ? 'oui' : 'non' }} ·
                            identité non affichée dans cette vue agrégée
                        </p>
                    </div>
                    <p v-if="audit.events.length === 0" class="text-sm text-white/35">
                        Aucune action d’administration P008 enregistrée.
                    </p>
                </div>
            </article>
        </section>

        <aside class="mt-7 rounded-[2rem] border border-rose-300/15 bg-rose-300/[0.035] p-5 sm:p-7">
            <p class="font-black text-rose-200">Barrière de confidentialité de la console</p>
            <p class="mt-3 text-sm leading-6 text-white/42">
                L’audit ne retourne aucun identifiant de compte, aucune empreinte pseudonymisée,
                aucun SmartProfile et aucune réponse utilisateur. Les actions administratives sont
                traçables en base, mais présentées ici sous forme strictement agrégée.
            </p>
        </aside>
    </AdminLayout>
</template>
