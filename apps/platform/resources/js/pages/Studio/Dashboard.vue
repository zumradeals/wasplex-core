<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Brand = {
    id: string;
    name: string;
    status: string;
    versionCount: number;
    activeVersion: {
        publicName: string;
        slogan: string | null;
        primaryColor: string;
        secondaryColor: string;
        logoUrl: string | null;
    } | null;
};

type Asset = {
    id: string;
    kind: 'image' | 'video';
    originalName: string;
    url: string | null;
    label: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    profile: { displayName: string; completion: number; industry: string | null };
    wallet: { balances: { available: number; reserved: number; pending: number } };
    metrics: { brands: number; media: number; members: number; profileCompletion: number };
    recentBrands: Brand[];
    recentAssets: Asset[];
    flash: { success: string | null };
}>();

const money = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
const readiness = computed(() => {
    let score = 0;
    if (props.metrics.profileCompletion >= 70) score += 25;
    if (props.metrics.brands > 0) score += 25;
    if (props.metrics.media > 0) score += 25;
    if (props.wallet.balances.available > 0) score += 25;

    return score;
});

const steps = computed(() => [
    {
        label: 'Compléter le profil annonceur',
        detail: `${props.metrics.profileCompletion} % complété`,
        done: props.metrics.profileCompletion >= 70,
        href: '/studio/profil',
    },
    {
        label: 'Créer une première marque',
        detail: props.metrics.brands > 0 ? `${props.metrics.brands} marque(s)` : 'Aucune marque',
        done: props.metrics.brands > 0,
        href: '/studio/marques',
    },
    {
        label: 'Ajouter des images ou vidéos',
        detail: props.metrics.media > 0 ? `${props.metrics.media} média(s)` : 'Bibliothèque vide',
        done: props.metrics.media > 0,
        href: '/studio/medias',
    },
    {
        label: 'Financer le Wallet annonceur',
        detail:
            props.wallet.balances.available > 0
                ? `${money(props.wallet.balances.available)} WP disponibles`
                : 'Budget à alimenter',
        done: props.wallet.balances.available > 0,
        href: '/studio/wallet',
    },
]);
</script>

<template>
    <Head title="Studio Annonceur" />
    <StudioLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="dashboard"
    >
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.24em] text-orange-400 uppercase">
                    Studio Annonceur
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Bonjour, {{ profile.displayName }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                    Construisez votre identité, organisez vos médias et préparez votre budget avant
                    la première campagne.
                </p>
            </div>
            <Link
                href="/studio/marques"
                class="rounded-2xl bg-white px-5 py-3 text-center text-sm font-black text-[#06111f] transition hover:-translate-y-0.5"
            >
                Créer une marque
            </Link>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <section class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-white/8 bg-white/[0.045] p-5">
                <p class="text-xs font-semibold text-white/35">Budget disponible</p>
                <p class="mt-4 text-2xl font-black">
                    {{ money(wallet.balances.available) }}
                    <span class="text-sm text-cyan-300">WP</span>
                </p>
                <Link
                    href="/studio/wallet"
                    class="mt-4 inline-block text-xs font-black text-cyan-300"
                >
                    Gérer le budget →
                </Link>
            </article>
            <article class="rounded-3xl border border-white/8 bg-white/[0.045] p-5">
                <p class="text-xs font-semibold text-white/35">Marques actives</p>
                <p class="mt-4 text-2xl font-black">{{ metrics.brands }}</p>
                <p class="mt-4 text-xs text-white/30">Identités versionnées</p>
            </article>
            <article class="rounded-3xl border border-white/8 bg-white/[0.045] p-5">
                <p class="text-xs font-semibold text-white/35">Médias prêts</p>
                <p class="mt-4 text-2xl font-black">{{ metrics.media }}</p>
                <p class="mt-4 text-xs text-white/30">Images et vidéos isolées</p>
            </article>
            <article class="rounded-3xl border border-cyan-400/15 bg-cyan-400/[0.055] p-5">
                <p class="text-xs font-semibold text-cyan-200/60">Prêt pour P006</p>
                <p class="mt-4 text-2xl font-black text-cyan-200">{{ readiness }} %</p>
                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-black/25">
                    <div
                        class="h-full rounded-full bg-cyan-300"
                        :style="{ width: `${readiness}%` }"
                    />
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <article class="rounded-[2rem] border border-white/8 bg-white/[0.04] p-6 sm:p-8">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-lg font-black">Préparation du Studio</p>
                        <p class="mt-1 text-sm text-white/35">Quatre actions avant la campagne.</p>
                    </div>
                    <span
                        class="rounded-full bg-white/5 px-3 py-1 text-xs font-black text-white/45"
                    >
                        {{ steps.filter((step) => step.done).length }}/4
                    </span>
                </div>

                <div class="mt-6 space-y-3">
                    <Link
                        v-for="(step, index) in steps"
                        :key="step.label"
                        :href="step.href"
                        class="group flex items-center gap-4 rounded-2xl border border-white/6 bg-black/15 p-4 transition hover:border-cyan-300/20 hover:bg-cyan-300/[0.035]"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl text-sm font-black"
                            :class="
                                step.done
                                    ? 'bg-emerald-400/12 text-emerald-300'
                                    : 'bg-white/5 text-white/35'
                            "
                        >
                            {{ step.done ? '✓' : index + 1 }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-black">{{ step.label }}</span>
                            <span class="mt-1 block truncate text-xs text-white/30">{{
                                step.detail
                            }}</span>
                        </span>
                        <span
                            class="text-white/20 transition group-hover:translate-x-1 group-hover:text-cyan-300"
                            >→</span
                        >
                    </Link>
                </div>
            </article>

            <article
                class="overflow-hidden rounded-[2rem] border border-orange-400/15 bg-[#130f0c] p-6 sm:p-8"
            >
                <p class="text-[10px] font-black tracking-[0.2em] text-orange-400 uppercase">
                    Prochaine étape
                </p>
                <h2 class="mt-4 text-2xl font-black">La campagne arrive dans P006.</h2>
                <p class="mt-4 text-sm leading-6 text-white/40">
                    Objectif, audience, territoire, devis et réservation du budget seront construits
                    après que votre marque et vos médias seront prêts.
                </p>
                <div class="mt-7 rounded-2xl border border-white/7 bg-white/[0.035] p-4">
                    <p class="text-xs font-black text-white/55">Protection de périmètre</p>
                    <p class="mt-2 text-xs leading-5 text-white/30">
                        Aucune campagne factice n’est créée dans P005. Le Studio prépare des données
                        solides pour le prochain chantier.
                    </p>
                </div>
            </article>
        </section>

        <section
            v-if="recentBrands.length"
            class="mt-6 rounded-[2rem] border border-white/8 bg-white/[0.035] p-6 sm:p-8"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-black">Marques récentes</h2>
                    <p class="mt-1 text-sm text-white/30">Les identités actuellement publiées.</p>
                </div>
                <Link href="/studio/marques" class="text-xs font-black text-cyan-300"
                    >Tout voir →</Link
                >
            </div>
            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="brand in recentBrands"
                    :key="brand.id"
                    class="rounded-2xl border border-white/7 bg-black/15 p-4"
                >
                    <div
                        class="grid size-12 place-items-center overflow-hidden rounded-2xl text-lg font-black"
                        :style="{
                            background: `linear-gradient(135deg, ${brand.activeVersion?.primaryColor ?? '#0EA5E9'}, ${brand.activeVersion?.secondaryColor ?? '#F97316'})`,
                        }"
                    >
                        <img
                            v-if="brand.activeVersion?.logoUrl"
                            :src="brand.activeVersion.logoUrl"
                            :alt="brand.name"
                            class="size-full object-cover"
                        />
                        <span v-else>{{ brand.name.slice(0, 1).toUpperCase() }}</span>
                    </div>
                    <p class="mt-4 truncate font-black">{{ brand.name }}</p>
                    <p class="mt-1 truncate text-xs text-white/30">
                        {{ brand.activeVersion?.slogan || `Version ${brand.versionCount}` }}
                    </p>
                </article>
            </div>
        </section>

        <section
            v-if="recentAssets.length"
            class="mt-6 rounded-[2rem] border border-white/8 bg-white/[0.035] p-6 sm:p-8"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-black">Derniers médias</h2>
                <Link href="/studio/medias" class="text-xs font-black text-cyan-300"
                    >Médiathèque →</Link
                >
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
                <article
                    v-for="asset in recentAssets"
                    :key="asset.id"
                    class="aspect-square overflow-hidden rounded-2xl border border-white/7 bg-black/25"
                >
                    <img
                        v-if="asset.kind === 'image' && asset.url"
                        :src="asset.url"
                        :alt="asset.label || asset.originalName"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="grid size-full place-items-center text-center text-xs font-black text-white/30"
                    >
                        {{ asset.kind === 'video' ? 'VIDÉO' : 'MÉDIA' }}
                    </div>
                </article>
            </div>
        </section>
    </StudioLayout>
</template>
