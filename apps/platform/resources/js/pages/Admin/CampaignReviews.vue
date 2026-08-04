<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type CampaignSummary = {
    id: string;
    name: string;
    status: 'submitted' | 'changes_requested' | 'approved' | 'rejected' | 'suspended';
    advertiser: { organizationId: string; name: string; spaceLabel: string };
    brand: {
        id: string;
        name: string;
        primaryColor: string | null;
        secondaryColor: string | null;
    };
    review: {
        id: string;
        submissionNumber: number;
        status: string;
        reason: string | null;
        openedAt: string;
        decidedAt: string | null;
        task: { status: string; priority: string; dueAt: string | null } | null;
    } | null;
    budget: { amountMinor: number; currency: string; reservationStatus: string | null };
    submittedAt: string | null;
    updatedAt: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaigns: CampaignSummary[];
    filter: 'pending' | 'approved' | 'rejected' | 'suspended' | 'all';
    counts: { pending: number; approved: number; rejected: number; suspended: number };
    flash: { success: string | null };
}>();

const tabs = [
    { key: 'pending', label: 'À traiter', count: props.counts.pending },
    { key: 'approved', label: 'Approuvées', count: props.counts.approved },
    { key: 'rejected', label: 'Rejetées', count: props.counts.rejected },
    { key: 'suspended', label: 'Suspendues', count: props.counts.suspended },
    { key: 'all', label: 'Historique', count: null },
] as const;

const statusLabel: Record<CampaignSummary['status'], string> = {
    submitted: 'En attente',
    changes_requested: 'Corrections demandées',
    approved: 'Approuvée',
    rejected: 'Rejetée',
    suspended: 'Suspendue',
};

const statusClass: Record<CampaignSummary['status'], string> = {
    submitted: 'bg-amber-300/10 text-amber-200',
    changes_requested: 'bg-orange-300/10 text-orange-200',
    approved: 'bg-emerald-300/10 text-emerald-200',
    rejected: 'bg-rose-300/10 text-rose-200',
    suspended: 'bg-violet-300/10 text-violet-200',
};

const money = (value: number, currency = 'XOF') =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);

const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : '—';
</script>

<template>
    <Head title="Revue des campagnes" />
    <AdminLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.24em] text-wasplex-orange uppercase">
                    P007 · Contrôle administratif
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Revue des campagnes
                </h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-white/40">
                    Examiner le contenu, l’audience et le budget réservé avant toute éligibilité au
                    Matching. Aucune décision sur cet écran ne capture le budget.
                </p>
            </div>
            <div class="rounded-2xl border border-amber-300/15 bg-amber-300/[0.055] px-5 py-4">
                <p class="text-xs font-black text-amber-200">File active</p>
                <p class="mt-1 text-3xl font-black">{{ counts.pending }}</p>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <nav class="mt-7 flex gap-2 overflow-x-auto pb-2">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="`/administration/campagnes?status=${tab.key}`"
                class="shrink-0 rounded-2xl border px-4 py-3 text-xs font-black transition"
                :class="
                    filter === tab.key
                        ? 'border-cyan-300 bg-cyan-300 text-[#04111e]'
                        : 'border-white/8 bg-white/[0.035] text-white/45 hover:text-white'
                "
            >
                {{ tab.label }}
                <span v-if="tab.count !== null" class="ml-2 opacity-70">{{ tab.count }}</span>
            </Link>
        </nav>

        <section v-if="campaigns.length" class="mt-5 space-y-4">
            <article
                v-for="campaign in campaigns"
                :key="campaign.id"
                class="overflow-hidden rounded-[1.75rem] border border-white/8 bg-white/[0.035]"
            >
                <div
                    class="h-1.5"
                    :style="{
                        background: `linear-gradient(90deg, ${campaign.brand.primaryColor ?? '#22D3EE'}, ${campaign.brand.secondaryColor ?? '#38BDF8'})`,
                    }"
                />
                <div class="grid gap-5 p-5 sm:p-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                :class="statusClass[campaign.status]"
                            >
                                {{ statusLabel[campaign.status] }}
                            </span>
                            <span class="text-xs font-bold text-white/28">
                                Soumission n°{{ campaign.review?.submissionNumber ?? 1 }}
                            </span>
                        </div>
                        <h2 class="mt-3 truncate text-xl font-black">{{ campaign.name }}</h2>
                        <p class="mt-1 text-sm text-white/42">
                            {{ campaign.advertiser.name }} · {{ campaign.brand.name }}
                        </p>

                        <div class="mt-5 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-black/15 p-3">
                                <p class="text-[10px] font-bold text-white/28 uppercase">Budget</p>
                                <p class="mt-1 font-black">
                                    {{ money(campaign.budget.amountMinor, campaign.budget.currency) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-black/15 p-3">
                                <p class="text-[10px] font-bold text-white/28 uppercase">
                                    Réservation
                                </p>
                                <p class="mt-1 font-black">
                                    {{ campaign.budget.reservationStatus || '—' }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-black/15 p-3">
                                <p class="text-[10px] font-bold text-white/28 uppercase">Ouverte</p>
                                <p class="mt-1 font-black">
                                    {{ date(campaign.review?.openedAt ?? campaign.submittedAt) }}
                                </p>
                            </div>
                        </div>

                        <p
                            v-if="campaign.review?.reason"
                            class="mt-4 rounded-2xl border border-white/7 bg-black/10 px-4 py-3 text-sm leading-6 text-white/48"
                        >
                            {{ campaign.review.reason }}
                        </p>
                    </div>

                    <Link
                        :href="`/administration/campagnes/${campaign.id}`"
                        class="rounded-2xl bg-white px-5 py-3 text-center text-sm font-black text-[#04111e]"
                    >
                        {{ campaign.status === 'submitted' ? 'Examiner' : 'Consulter' }}
                    </Link>
                </div>
            </article>
        </section>

        <section
            v-else
            class="mt-6 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.025] px-6 py-16 text-center"
        >
            <div class="mx-auto grid size-16 place-items-center rounded-3xl bg-emerald-300/10 text-2xl">
                ✓
            </div>
            <h2 class="mt-5 text-2xl font-black">Aucune campagne dans cette vue</h2>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-white/38">
                La file est à jour. Les nouvelles campagnes financées apparaîtront après leur
                soumission par l’annonceur.
            </p>
        </section>
    </AdminLayout>
</template>
