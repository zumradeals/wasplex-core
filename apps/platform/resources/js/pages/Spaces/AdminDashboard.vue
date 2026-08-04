<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import SpaceSwitcher from '@/components/SpaceSwitcher.vue';
import WasplexMark from '@/components/WasplexMark.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    metrics: { accounts: number; spaces: number; campaignReviews: number };
}>();

const navigation = [
    { label: 'Centre de pilotage', href: '/administration', active: true },
    { label: 'Revue des campagnes', href: '/administration/campagnes', active: false },
    { label: 'Comptes & espaces', href: '#', active: false },
    { label: 'Capacités', href: '#', active: false },
    { label: 'Organisations', href: '#', active: false },
    { label: 'Audit d’accès', href: '#', active: false },
    { label: 'Wallet & dépôts', href: '/administration/wallet', active: false },
    {
        label: 'Économie & abonnements',
        href: '/administration/economie',
        active: false,
    },
];
</script>

<template>
    <Head title="Console fondateur" />
    <main class="min-h-screen bg-[#030a12] text-white">
        <div class="hidden min-h-screen md:grid md:grid-cols-[18rem_1fr]">
            <aside class="border-r border-white/8 bg-[#07111d] p-6">
                <WasplexMark />
                <div class="mt-7"><SpaceSwitcher :spaces="spaces" /></div>
                <div
                    class="mt-8 rounded-2xl border border-emerald-400/15 bg-emerald-400/[0.06] p-4"
                >
                    <p class="text-xs font-bold tracking-widest text-emerald-300 uppercase">
                        Session renforcée
                    </p>
                    <p class="mt-2 text-sm text-white/45">MFA vérifiée · accès nominatif</p>
                </div>
                <nav class="mt-8 space-y-1 text-sm font-semibold">
                    <Link
                        v-for="item in navigation"
                        :key="item.label"
                        :href="item.href"
                        class="block rounded-xl px-4 py-3 transition"
                        :class="
                            item.active
                                ? 'bg-white/8 text-white'
                                : 'text-white/40 hover:bg-white/5 hover:text-white'
                        "
                    >
                        {{ item.label }}
                    </Link>
                </nav>
                <button
                    class="mt-10 text-xs font-bold text-white/35"
                    @click="router.post('/deconnexion')"
                >
                    Fermer la session
                </button>
            </aside>

            <section class="p-8 xl:p-12">
                <header class="flex items-end justify-between border-b border-white/8 pb-7">
                    <div>
                        <p
                            class="text-xs font-bold tracking-[0.25em] text-wasplex-orange uppercase"
                        >
                            Administration Wasplex
                        </p>
                        <h1 class="mt-3 text-4xl font-black">Console fondateur</h1>
                        <p class="mt-2 text-white/40">
                            Supervision réelle, capacités explicites, aucune action invisible.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-white/10 px-4 py-3 text-right">
                        <p class="text-xs text-white/35">Connecté en tant que</p>
                        <p class="mt-1 font-bold">{{ account.displayName }}</p>
                    </div>
                </header>

                <div class="mt-8 grid gap-4 lg:grid-cols-4">
                    <article
                        v-for="metric in [
                            { label: 'Comptes', value: metrics.accounts },
                            { label: 'Espaces', value: metrics.spaces },
                            { label: 'Campagnes à traiter', value: metrics.campaignReviews },
                            { label: 'Alertes sécurité', value: 0 },
                        ]"
                        :key="metric.label"
                        class="rounded-2xl border border-white/8 bg-white/[0.035] p-5"
                    >
                        <p class="text-xs font-semibold text-white/35">{{ metric.label }}</p>
                        <p class="mt-4 text-3xl font-black">{{ metric.value }}</p>
                    </article>
                </div>

                <div class="mt-7 grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                    <article class="rounded-3xl border border-white/8 bg-white/[0.035] p-7">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-black">Contrôles du noyau</h2>
                                <p class="mt-1 text-sm text-white/35">Identité, économie et campagne</p>
                            </div>
                            <span
                                class="size-3 rounded-full bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,0.8)]"
                            />
                        </div>
                        <div class="mt-7 divide-y divide-white/8">
                            <div
                                v-for="item in [
                                    'Comptes universels',
                                    'Sessions et appareils',
                                    'Espaces isolés',
                                    'Capacités expirables',
                                    'MFA administration',
                                    'Réservations Wallet',
                                    'Revue administrative',
                                ]"
                                :key="item"
                                class="flex items-center justify-between py-4"
                            >
                                <span class="font-semibold">{{ item }}</span>
                                <span class="text-xs font-bold text-emerald-300">ACTIF</span>
                            </div>
                        </div>
                    </article>

                    <div class="space-y-6">
                        <Link
                            href="/administration/campagnes"
                            class="block rounded-3xl border border-wasplex-orange/20 bg-wasplex-orange/[0.055] p-7 transition hover:border-wasplex-orange/40 hover:bg-wasplex-orange/[0.08]"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-xs font-bold tracking-widest text-wasplex-orange uppercase"
                                    >
                                        P007 · Campagnes
                                    </p>
                                    <h2 class="mt-4 text-2xl font-black">Revue administrative</h2>
                                </div>
                                <span
                                    class="grid size-12 place-items-center rounded-2xl bg-wasplex-orange/10 text-xl font-black text-wasplex-orange"
                                >
                                    {{ metrics.campaignReviews }}
                                </span>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-white/45">
                                Examiner le média, l’audience, le devis et le budget réservé avant
                                toute éligibilité au Matching.
                            </p>
                            <span class="mt-6 inline-flex text-sm font-black text-wasplex-orange">
                                Ouvrir la file →
                            </span>
                        </Link>

                        <Link
                            href="/administration/economie"
                            class="block rounded-3xl border border-wasplex-cyan/20 bg-wasplex-cyan/[0.055] p-7 transition hover:border-wasplex-cyan/40 hover:bg-wasplex-cyan/[0.08]"
                        >
                            <p
                                class="text-xs font-bold tracking-widest text-wasplex-cyan uppercase"
                            >
                                P004 · Noyau économique
                            </p>
                            <h2 class="mt-4 text-2xl font-black">Configurer l’économie Wasplex</h2>
                            <p class="mt-4 text-sm leading-6 text-white/45">
                                Piloter les classes FREE, PREMIUM, GOLD et PLATINUM, leurs quotas,
                                leurs poids et leurs versions publiées.
                            </p>
                        </Link>
                    </div>
                </div>
            </section>
        </div>

        <section class="min-h-screen px-4 py-5 md:hidden">
            <div class="flex items-center justify-between gap-4">
                <WasplexMark />
                <button
                    class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black text-white/55"
                    @click="router.post('/deconnexion')"
                >
                    Sortir
                </button>
            </div>
            <div class="mt-5"><SpaceSwitcher :spaces="spaces" /></div>
            <p class="mt-8 text-xs font-bold tracking-widest text-wasplex-orange uppercase">
                Urgences administratives
            </p>
            <h1 class="mt-4 text-3xl font-black">Console fondateur</h1>
            <Link
                href="/administration/campagnes"
                class="mt-6 block rounded-3xl border border-wasplex-orange/20 bg-wasplex-orange/[0.055] p-6"
            >
                <p class="text-sm font-black text-wasplex-orange">Campagnes à traiter</p>
                <p class="mt-3 text-4xl font-black">{{ metrics.campaignReviews }}</p>
                <p class="mt-3 text-sm leading-6 text-white/42">
                    Ouvrir la file P007 pour examiner ou suspendre une campagne.
                </p>
            </Link>
        </section>
    </main>
</template>
