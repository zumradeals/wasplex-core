<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Asset = {
    id: string;
    kind: 'image' | 'video';
    name: string;
    mimeType: string;
    url: string | null;
};

type ReviewEvent = {
    id: string;
    type: string;
    fromStatus: string | null;
    toStatus: string;
    reason: string | null;
    actorName: string | null;
    occurredAt: string;
};

type Review = {
    id: string;
    submissionNumber: number;
    status: string;
    reason: string | null;
    openedAt: string;
    decidedAt: string | null;
    submitterName: string | null;
    deciderName: string | null;
    task: {
        status: string;
        priority: string;
        dueAt: string | null;
        completedAt: string | null;
    } | null;
    events: ReviewEvent[];
};

type CampaignDetail = {
    id: string;
    name: string;
    status: 'submitted' | 'changes_requested' | 'approved' | 'rejected' | 'suspended';
    currentStep: number;
    advertiser: {
        organizationId: string;
        name: string;
        spaceId: string;
        spaceLabel: string;
        creatorName: string | null;
    };
    brand: {
        id: string;
        name: string;
        primaryColor: string | null;
        secondaryColor: string | null;
    };
    version: {
        id: string;
        version: number;
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
        territoryName: string;
        radiusKm: number;
        selectedClasses: string[];
        estimatedMin: number;
        estimatedMax: number;
        notice: string | null;
    } | null;
    quote: {
        id: string;
        version: number;
        status: string;
        currency: string;
        grossAmountMinor: number;
        platformShareMinor: number;
        userShareMinor: number;
        estimatedImpressions: number;
        catalogVersion: number;
        issuedAt: string;
        expiresAt: string;
    } | null;
    funding: {
        id: string;
        status: string;
        amountMinor: number;
        currency: string;
        reservationId: string | null;
        reservationStatus: string | null;
        reservedAt: string;
        submittedAt: string | null;
    } | null;
    reviews: Review[];
    statusHistory: Array<{
        id: string;
        fromStatus: string | null;
        toStatus: string;
        reason: string | null;
        actorName: string | null;
        occurredAt: string;
    }>;
    submittedAt: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaign: CampaignDetail;
    flash: { success: string | null };
}>();

const form = useForm({ reason: '' });

const post = (action: 'corrections' | 'approuver' | 'rejeter' | 'suspendre') => {
    form.post(`/administration/campagnes/${props.campaign.id}/${action}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const money = (value: number, currency = 'XOF') =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);

const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';

const statusLabel: Record<CampaignDetail['status'], string> = {
    submitted: 'En attente de décision',
    changes_requested: 'Corrections demandées',
    approved: 'Approuvée',
    rejected: 'Rejetée',
    suspended: 'Suspendue',
};
</script>

<template>
    <Head :title="`Revue — ${campaign.name}`" />
    <AdminLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <Link href="/administration/campagnes" class="text-xs font-black text-cyan-300/70">
                    ← File des campagnes
                </Link>
                <p class="mt-5 text-xs font-black tracking-[0.22em] text-wasplex-orange uppercase">
                    Revue administrative
                </p>
                <h1 class="mt-3 truncate text-3xl font-black tracking-tight sm:text-4xl">
                    {{ campaign.name }}
                </h1>
                <p class="mt-2 text-sm text-white/42">
                    {{ campaign.advertiser.name }} · {{ campaign.brand.name }} · version
                    {{ campaign.version?.version ?? '—' }}
                </p>
            </div>
            <div class="rounded-2xl border border-white/10 bg-white/[0.035] px-5 py-4">
                <p class="text-xs font-bold text-white/35">État</p>
                <p class="mt-1 font-black">{{ statusLabel[campaign.status] }}</p>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1fr)_23rem]">
            <div class="space-y-6">
                <section class="overflow-hidden rounded-[2rem] border border-white/8 bg-white/[0.035]">
                    <div
                        class="h-2"
                        :style="{
                            background: `linear-gradient(90deg, ${campaign.brand.primaryColor ?? '#22D3EE'}, ${campaign.brand.secondaryColor ?? '#38BDF8'})`,
                        }"
                    />
                    <div class="p-5 sm:p-7">
                        <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">
                            Contenu publicitaire
                        </p>
                        <h2 class="mt-3 text-2xl font-black">
                            {{ campaign.version?.headline || campaign.name }}
                        </h2>
                        <p class="mt-4 whitespace-pre-line text-sm leading-7 text-white/52">
                            {{ campaign.version?.body || 'Aucun message renseigné.' }}
                        </p>
                        <div class="mt-5 flex flex-wrap gap-2 text-xs font-black text-white/55">
                            <span class="rounded-full border border-white/10 px-3 py-2">
                                Objectif : {{ campaign.version?.objective }}
                            </span>
                            <span
                                v-if="campaign.version?.callToAction"
                                class="rounded-full border border-white/10 px-3 py-2"
                            >
                                CTA : {{ campaign.version.callToAction }}
                            </span>
                            <a
                                v-if="campaign.version?.destinationUrl"
                                :href="campaign.version.destinationUrl"
                                target="_blank"
                                rel="noreferrer"
                                class="rounded-full border border-cyan-300/20 px-3 py-2 text-cyan-200"
                            >
                                Vérifier le lien ↗
                            </a>
                        </div>

                        <div
                            v-if="campaign.version?.creatives.length"
                            class="mt-6 grid gap-4 sm:grid-cols-2"
                        >
                            <article
                                v-for="asset in campaign.version.creatives"
                                :key="asset.id"
                                class="overflow-hidden rounded-2xl border border-white/8 bg-black/20"
                            >
                                <div class="grid min-h-52 place-items-center bg-black/35">
                                    <img
                                        v-if="asset.kind === 'image' && asset.url"
                                        :src="asset.url"
                                        :alt="asset.name"
                                        class="max-h-96 w-full object-contain"
                                    />
                                    <video
                                        v-else-if="asset.kind === 'video' && asset.url"
                                        :src="asset.url"
                                        controls
                                        preload="metadata"
                                        class="max-h-[34rem] w-full object-contain"
                                    />
                                    <span v-else class="text-3xl text-white/35">▧</span>
                                </div>
                                <div class="p-4">
                                    <p class="truncate text-sm font-black">{{ asset.name }}</p>
                                    <p class="mt-1 text-xs text-white/30">{{ asset.mimeType }}</p>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6">
                        <p class="text-xs font-black tracking-[0.2em] text-cyan-300 uppercase">
                            Audience de planification
                        </p>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-black/15 p-4">
                                <p class="text-[10px] font-bold text-white/28 uppercase">Minimum</p>
                                <p class="mt-2 text-2xl font-black">
                                    {{ number(campaign.audience?.estimatedMin ?? 0) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-black/15 p-4">
                                <p class="text-[10px] font-bold text-white/28 uppercase">Maximum</p>
                                <p class="mt-2 text-2xl font-black">
                                    {{ number(campaign.audience?.estimatedMax ?? 0) }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-5 text-sm leading-6 text-white/45">
                            {{ campaign.audience?.territoryName }} · rayon
                            {{ campaign.audience?.radiusKm }} km
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="economicClass in campaign.audience?.selectedClasses ?? []"
                                :key="economicClass"
                                class="rounded-full border border-white/10 px-3 py-1.5 text-xs font-black text-white/55"
                            >
                                {{ economicClass }}
                            </span>
                        </div>
                        <p class="mt-5 text-xs leading-5 text-white/30">
                            {{ campaign.audience?.notice }}
                        </p>
                    </article>

                    <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6">
                        <p class="text-xs font-black tracking-[0.2em] text-wasplex-orange uppercase">
                            Budget protégé
                        </p>
                        <p class="mt-5 text-3xl font-black">
                            {{
                                money(
                                    campaign.quote?.grossAmountMinor ??
                                        campaign.funding?.amountMinor ??
                                        0,
                                    campaign.quote?.currency ?? campaign.funding?.currency,
                                )
                            }}
                        </p>
                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between gap-4">
                                <span class="text-white/38">Part utilisateurs</span>
                                <strong>{{ money(campaign.quote?.userShareMinor ?? 0) }}</strong>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-white/38">Part Wasplex</span>
                                <strong>{{ money(campaign.quote?.platformShareMinor ?? 0) }}</strong>
                            </div>
                            <div class="flex justify-between gap-4">
                                <span class="text-white/38">Impressions estimées</span>
                                <strong>{{ number(campaign.quote?.estimatedImpressions ?? 0) }}</strong>
                            </div>
                            <div class="flex justify-between gap-4 border-t border-white/8 pt-3">
                                <span class="text-white/38">Réservation</span>
                                <strong class="text-cyan-200">
                                    {{ campaign.funding?.reservationStatus || '—' }}
                                </strong>
                            </div>
                        </div>
                        <p class="mt-5 text-xs leading-5 text-white/30">
                            P007 conserve la réservation lors d’une correction ou d’une approbation.
                            Seul un rejet la libère.
                        </p>
                    </article>
                </section>

                <section class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6 sm:p-7">
                    <h2 class="text-xl font-black">Historique de statut</h2>
                    <div class="mt-5 divide-y divide-white/8">
                        <div
                            v-for="event in campaign.statusHistory"
                            :key="event.id"
                            class="grid gap-2 py-4 sm:grid-cols-[10rem_1fr_auto] sm:items-start"
                        >
                            <p class="text-xs font-black text-cyan-200">{{ event.toStatus }}</p>
                            <div>
                                <p class="text-sm text-white/55">
                                    {{ event.reason || 'Transition enregistrée sans commentaire.' }}
                                </p>
                                <p class="mt-1 text-xs text-white/25">
                                    {{ event.actorName || 'Système Wasplex' }}
                                </p>
                            </div>
                            <p class="text-xs text-white/28">{{ date(event.occurredAt) }}</p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-6">
                <section
                    v-if="campaign.status === 'submitted'"
                    class="rounded-[2rem] border border-amber-300/15 bg-amber-300/[0.045] p-5"
                >
                    <p class="text-xs font-black tracking-[0.18em] text-amber-200 uppercase">
                        Décision requise
                    </p>
                    <label class="mt-5 block">
                        <span class="text-xs font-black text-white/55">
                            Motif ou commentaire de décision
                        </span>
                        <textarea
                            v-model="form.reason"
                            rows="6"
                            maxlength="4000"
                            placeholder="Décrivez précisément la correction, le rejet ou la justification."
                            class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm leading-6 outline-none focus:border-cyan-300/50"
                        />
                        <span v-if="form.errors.reason" class="mt-2 block text-xs text-rose-300">
                            {{ form.errors.reason }}
                        </span>
                    </label>
                    <div class="mt-4 grid gap-2">
                        <button
                            class="rounded-2xl bg-emerald-300 px-4 py-3 text-sm font-black text-[#04111e] disabled:opacity-50"
                            :disabled="form.processing"
                            @click="post('approuver')"
                        >
                            Approuver la campagne
                        </button>
                        <button
                            class="rounded-2xl border border-orange-300/25 bg-orange-300/8 px-4 py-3 text-sm font-black text-orange-200 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="post('corrections')"
                        >
                            Demander des corrections
                        </button>
                        <button
                            class="rounded-2xl border border-rose-300/25 bg-rose-300/8 px-4 py-3 text-sm font-black text-rose-200 disabled:opacity-50"
                            :disabled="form.processing"
                            @click="post('rejeter')"
                        >
                            Rejeter et libérer le budget
                        </button>
                    </div>
                </section>

                <section
                    v-else-if="campaign.status === 'approved'"
                    class="rounded-[2rem] border border-violet-300/15 bg-violet-300/[0.045] p-5"
                >
                    <p class="text-xs font-black tracking-[0.18em] text-violet-200 uppercase">
                        Campagne approuvée
                    </p>
                    <p class="mt-3 text-sm leading-6 text-white/42">
                        Elle est éligible au futur Matching, mais aucune diffusion n’existe encore.
                    </p>
                    <textarea
                        v-model="form.reason"
                        rows="5"
                        maxlength="4000"
                        placeholder="Motif obligatoire de suspension"
                        class="mt-5 w-full resize-none rounded-2xl border border-white/10 bg-black/25 px-4 py-3 text-sm leading-6 outline-none"
                    />
                    <span v-if="form.errors.reason" class="mt-2 block text-xs text-rose-300">
                        {{ form.errors.reason }}
                    </span>
                    <button
                        class="mt-3 w-full rounded-2xl border border-violet-300/25 bg-violet-300/8 px-4 py-3 text-sm font-black text-violet-200 disabled:opacity-50"
                        :disabled="form.processing"
                        @click="post('suspendre')"
                    >
                        Suspendre la campagne
                    </button>
                </section>

                <section class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5">
                    <h2 class="font-black">Dossiers de revue</h2>
                    <div class="mt-4 space-y-3">
                        <article
                            v-for="review in campaign.reviews"
                            :key="review.id"
                            class="rounded-2xl border border-white/7 bg-black/15 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-xs font-black text-cyan-200">
                                    Soumission n°{{ review.submissionNumber }}
                                </p>
                                <span class="text-[10px] font-black text-white/30 uppercase">
                                    {{ review.status }}
                                </span>
                            </div>
                            <p v-if="review.reason" class="mt-3 text-sm leading-6 text-white/48">
                                {{ review.reason }}
                            </p>
                            <p class="mt-3 text-xs text-white/25">
                                Ouverte {{ date(review.openedAt) }}
                            </p>
                            <p v-if="review.deciderName" class="mt-1 text-xs text-white/25">
                                Décision : {{ review.deciderName }} · {{ date(review.decidedAt) }}
                            </p>
                        </article>
                    </div>
                </section>
            </aside>
        </div>
    </AdminLayout>
</template>
