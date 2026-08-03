<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import SpaceSwitcher from '@/components/SpaceSwitcher.vue';
import WasplexMark from '@/components/WasplexMark.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    wallet: { balances: { available: number; reserved: number; pending: number } };
}>();

const nav = ['Vue d’ensemble', 'Marques', 'Médias', 'Campagnes', 'Budget', 'Équipe'];
const money = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
const metrics = computed(() => [
    { label: 'Budget disponible', value: `${money(props.wallet.balances.available)} WP` },
    { label: 'Budget réservé', value: `${money(props.wallet.balances.reserved)} WP` },
    { label: 'Campagnes actives', value: '0' },
    { label: 'Membres', value: '1' },
]);
</script>

<template>
    <Head title="Studio Annonceur" />
    <main class="min-h-screen bg-[#06111f] text-white lg:grid lg:grid-cols-[17rem_1fr]">
        <aside
            class="border-b border-white/10 bg-wasplex-night px-5 py-5 lg:min-h-screen lg:border-r lg:border-b-0 lg:px-6 lg:py-7"
        >
            <div class="flex items-center justify-between lg:block">
                <WasplexMark />
                <button
                    class="text-xs font-bold text-white/40 lg:mt-8"
                    @click="router.post('/deconnexion')"
                >
                    Déconnexion
                </button>
            </div>
            <div class="mt-5"><SpaceSwitcher :spaces="spaces" /></div>
            <nav class="mt-8 hidden space-y-2 lg:block">
                <button
                    v-for="(item, index) in nav"
                    :key="item"
                    class="flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-semibold"
                    :class="
                        index === 0
                            ? 'bg-wasplex-blue text-white'
                            : 'text-white/45 hover:bg-white/5 hover:text-white'
                    "
                >
                    <span
                        class="size-2 rounded-full"
                        :class="index === 0 ? 'bg-wasplex-cyan' : 'bg-white/15'"
                    />{{ item }}
                </button>
            </nav>
        </aside>

        <section class="px-5 py-7 sm:px-8 lg:px-12 lg:py-10">
            <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-bold tracking-[0.2em] text-wasplex-orange uppercase">
                        Studio Annonceur
                    </p>
                    <h1 class="mt-3 text-3xl font-black sm:text-4xl">{{ activeSpace.label }}</h1>
                    <p class="mt-2 text-white/40">
                        Une expérience de travail complète, distincte de votre espace personnel.
                    </p>
                </div>
                <button
                    class="rounded-2xl bg-white px-5 py-3 text-sm font-black text-wasplex-night"
                >
                    Créer une campagne
                </button>
            </header>

            <div class="mt-9 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="metric in metrics"
                    :key="metric.label"
                    class="rounded-3xl border border-white/8 bg-white/[0.045] p-5"
                >
                    <p class="text-xs font-semibold text-white/35">{{ metric.label }}</p>
                    <p class="mt-4 text-2xl font-black">{{ metric.value }}</p>
                </article>
            </div>

            <div class="mt-7 grid gap-6 xl:grid-cols-[1.4fr_0.6fr]">
                <article class="rounded-[2rem] border border-white/8 bg-white/[0.045] p-6 sm:p-8">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="font-black">Démarrage du Studio</p>
                            <p class="mt-1 text-sm text-white/35">
                                Votre base annonceur est prête.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-bold text-emerald-300"
                            >ESPACE ACTIF</span
                        >
                    </div>
                    <div class="mt-8 space-y-4">
                        <Link
                            v-for="(step, index) in [
                                'Créer votre première marque',
                                'Importer vos médias',
                                'Financer le Wallet annonceur',
                                'Préparer une campagne',
                            ]"
                            :key="step"
                            class="flex items-center gap-4 rounded-2xl bg-black/15 p-4"
                            :href="index === 2 ? '/studio/wallet' : '#'"
                        >
                            <span
                                class="grid size-9 place-items-center rounded-xl bg-white/5 text-sm font-black text-white/45"
                                >{{ index + 1 }}</span
                            >
                            <p class="font-semibold">{{ step }}</p>
                        </Link>
                    </div>
                </article>

                <article
                    class="rounded-[2rem] bg-gradient-to-br from-wasplex-blue to-[#082e74] p-7"
                >
                    <p class="text-xs font-bold tracking-widest text-wasplex-cyan uppercase">
                        Séparation garantie
                    </p>
                    <h2 class="mt-4 text-2xl font-black">
                        Votre organisation possède son propre contexte.
                    </h2>
                    <p class="mt-4 text-sm leading-6 text-white/55">
                        Équipe, capacités et futures données de campagne restent isolées de Mon
                        Espace.
                    </p>
                </article>
            </div>
        </section>
    </main>
</template>
