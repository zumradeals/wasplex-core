<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
    metrics: { accounts: number; spaces: number };
}>();
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
                    <a class="block rounded-xl bg-white/8 px-4 py-3 text-white"
                        >Centre de pilotage</a
                    >
                    <a
                        v-for="item in [
                            'Comptes & espaces',
                            'Capacités',
                            'Organisations',
                            'Audit d’accès',
                            'Incidents',
                        ]"
                        :key="item"
                        class="block rounded-xl px-4 py-3 text-white/40 hover:bg-white/5 hover:text-white"
                        >{{ item }}</a
                    >
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
                            { label: 'Capacités critiques', value: 4 },
                            { label: 'Alertes sécurité', value: 0 },
                        ]"
                        :key="metric.label"
                        class="rounded-2xl border border-white/8 bg-white/[0.035] p-5"
                    >
                        <p class="text-xs font-semibold text-white/35">{{ metric.label }}</p>
                        <p class="mt-4 text-3xl font-black">{{ metric.value }}</p>
                    </article>
                </div>

                <div class="mt-7 grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                    <article class="rounded-3xl border border-white/8 bg-white/[0.035] p-7">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-black">Contrôles du noyau Identity</h2>
                                <p class="mt-1 text-sm text-white/35">État opérationnel P001</p>
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
                                    'Audit append-only',
                                ]"
                                :key="item"
                                class="flex items-center justify-between py-4"
                            >
                                <span class="font-semibold">{{ item }}</span
                                ><span class="text-xs font-bold text-emerald-300">ACTIF</span>
                            </div>
                        </div>
                    </article>
                    <article
                        class="rounded-3xl border border-wasplex-orange/15 bg-wasplex-orange/[0.055] p-7"
                    >
                        <p class="text-xs font-bold tracking-widest text-wasplex-orange uppercase">
                            Autorité contrôlée
                        </p>
                        <h2 class="mt-4 text-2xl font-black">
                            Chaque intervention laisse une preuve.
                        </h2>
                        <p class="mt-4 text-sm leading-6 text-white/45">
                            Les futures actions critiques exigeront capacité, contexte,
                            justification, MFA et événement d’audit.
                        </p>
                    </article>
                </div>
            </section>
        </div>

        <section class="grid min-h-screen place-items-center px-6 text-center md:hidden">
            <div>
                <WasplexMark />
                <p class="mt-10 text-xs font-bold tracking-widest text-wasplex-orange uppercase">
                    Accès mobile limité
                </p>
                <h1 class="mt-4 text-3xl font-black">
                    La console complète nécessite un écran sécurisé.
                </h1>
                <p class="mt-4 leading-7 text-white/45">
                    Sur mobile, seules les urgences administratives seront accessibles dans un
                    chantier dédié.
                </p>
                <button
                    class="mt-7 rounded-2xl border border-white/15 px-5 py-3 text-sm font-bold"
                    @click="router.post('/deconnexion')"
                >
                    Fermer la session
                </button>
            </div>
        </section>
    </main>
</template>
