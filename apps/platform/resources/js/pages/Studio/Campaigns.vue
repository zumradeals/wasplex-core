<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Campaign = {
    id: string;
    name: string;
    status: 'draft' | 'quoted' | 'funded' | 'submitted' | 'cancelled';
    currentStep: number;
    brand: { id: string; name: string; primaryColor: string | null; secondaryColor: string | null };
    version: {
        objective: string;
        territoryName: string | null;
        radiusKm: number | null;
        budgetMinor: number;
        creatives: Array<{ id: string; name: string; kind: 'image' | 'video'; url: string | null }>;
    } | null;
    audience: { estimatedMin: number; estimatedMax: number } | null;
    quote: { grossAmountMinor: number; currency: string; expiresAt: string } | null;
    submittedAt: string | null;
    updatedAt: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaigns: Campaign[];
    flash: { success: string | null };
}>();

const statusLabel: Record<Campaign['status'], string> = {
    draft: 'Brouillon',
    quoted: 'Devis prêt',
    funded: 'Budget réservé',
    submitted: 'Soumise',
    cancelled: 'Annulée',
};

const statusClass: Record<Campaign['status'], string> = {
    draft: 'bg-white/6 text-white/45',
    quoted: 'bg-amber-300/10 text-amber-200',
    funded: 'bg-cyan-300/10 text-cyan-200',
    submitted: 'bg-emerald-300/10 text-emerald-200',
    cancelled: 'bg-rose-300/10 text-rose-200',
};

const money = (value: number, currency = 'XOF') =>
    new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);

const number = (value: number) => new Intl.NumberFormat('fr-FR').format(value);

const cancel = (campaign: Campaign) => {
    if (!window.confirm(`Annuler la campagne « ${campaign.name} » ?`)) return;
    router.delete(`/studio/campagnes/${campaign.id}`);
};
</script>

<template>
    <Head title="Campagnes" />
    <StudioLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.24em] text-cyan-300 uppercase">
                    Campagnes publicitaires
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Créer et financer</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                    Préparez votre contenu, choisissez votre audience, obtenez un devis et réservez le
                    budget avant la revue administrative.
                </p>
            </div>
            <Link
                href="/studio/campagnes/nouvelle"
                class="rounded-2xl bg-cyan-300 px-5 py-3 text-center text-sm font-black text-[#04111e]"
            >
                Nouvelle campagne
            </Link>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <section v-if="campaigns.length" class="mt-8 grid gap-5 xl:grid-cols-2">
            <article
                v-for="campaign in campaigns"
                :key="campaign.id"
                class="overflow-hidden rounded-[2rem] border border-white/8 bg-white/[0.035]"
            >
                <div
                    class="h-2"
                    :style="{
                        background: `linear-gradient(90deg, ${campaign.brand.primaryColor ?? '#22D3EE'}, ${campaign.brand.secondaryColor ?? '#38BDF8'})`,
                    }"
                />
                <div class="p-6 sm:p-7">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs font-black text-cyan-300/65">{{ campaign.brand.name }}</p>
                            <h2 class="mt-2 truncate text-xl font-black">{{ campaign.name }}</h2>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-[10px] font-black uppercase"
                            :class="statusClass[campaign.status]"
                        >
                            {{ statusLabel[campaign.status] }}
                        </span>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl bg-black/15 p-3">
                            <p class="text-[10px] font-bold text-white/28 uppercase">Étape</p>
                            <p class="mt-1 font-black">{{ campaign.currentStep }}/7</p>
                        </div>
                        <div class="rounded-2xl bg-black/15 p-3">
                            <p class="text-[10px] font-bold text-white/28 uppercase">Budget</p>
                            <p class="mt-1 truncate font-black">
                                {{ money(campaign.quote?.grossAmountMinor ?? campaign.version?.budgetMinor ?? 0) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-black/15 p-3">
                            <p class="text-[10px] font-bold text-white/28 uppercase">Territoire</p>
                            <p class="mt-1 truncate font-black">
                                {{ campaign.version?.territoryName || 'À définir' }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-black/15 p-3">
                            <p class="text-[10px] font-bold text-white/28 uppercase">Audience</p>
                            <p class="mt-1 truncate font-black">
                                {{ campaign.audience ? number(campaign.audience.estimatedMax) : '—' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-white/7 pt-5">
                        <p class="text-xs text-white/28">
                            {{ campaign.version?.creatives.length ?? 0 }} contenu(s) sélectionné(s)
                        </p>
                        <div class="flex gap-2">
                            <button
                                v-if="!['submitted', 'cancelled'].includes(campaign.status)"
                                class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black text-white/45 hover:text-white"
                                @click="cancel(campaign)"
                            >
                                Annuler
                            </button>
                            <Link
                                :href="`/studio/campagnes/${campaign.id}/modifier`"
                                class="rounded-xl bg-white px-4 py-2 text-xs font-black text-[#04111e]"
                            >
                                {{ campaign.status === 'submitted' ? 'Consulter' : 'Continuer' }}
                            </Link>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-else
            class="mt-8 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.025] px-6 py-16 text-center"
        >
            <div class="mx-auto grid size-16 place-items-center rounded-3xl bg-cyan-300/10 text-2xl">◎</div>
            <h2 class="mt-5 text-2xl font-black">Votre première campagne</h2>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-white/38">
                L’assistant vous guide en sept étapes simples, sans exposer les calculs techniques du
                Wallet ou de la répartition économique.
            </p>
            <Link
                href="/studio/campagnes/nouvelle"
                class="mt-7 inline-flex rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e]"
            >
                Commencer
            </Link>
        </section>
    </StudioLayout>
</template>
