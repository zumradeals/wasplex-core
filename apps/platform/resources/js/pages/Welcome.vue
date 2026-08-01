<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

const foundations = [
    { label: 'Backend', value: 'Laravel 13', tone: 'blue' },
    { label: 'Données', value: 'PostgreSQL', tone: 'cyan' },
    { label: 'Temps réel', value: 'Redis · Reverb', tone: 'orange' },
    { label: 'Interface', value: 'Inertia · Vue 3', tone: 'gold' },
] as const;
</script>

<template>
    <Head title="Fondation technique" />

    <main class="min-h-screen overflow-hidden bg-wasplex-night text-white">
        <div class="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true">
            <div
                class="absolute -top-44 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-wasplex-blue/25 blur-3xl"
            />
            <div
                class="absolute top-1/3 right-0 h-72 w-72 rounded-full bg-wasplex-cyan/15 blur-3xl"
            />
        </div>

        <section
            class="relative mx-auto flex min-h-screen max-w-6xl flex-col px-6 py-8 sm:px-10 lg:px-14"
        >
            <header class="flex items-center justify-between border-b border-white/10 pb-6">
                <div class="flex items-center gap-3">
                    <div
                        class="grid size-11 place-items-center rounded-2xl bg-gradient-to-br from-wasplex-cyan to-wasplex-blue font-black text-wasplex-night shadow-glow"
                    >
                        W
                    </div>
                    <div>
                        <p class="text-lg font-bold tracking-tight">Wasplex</p>
                        <p class="text-xs tracking-[0.24em] text-white/45 uppercase">
                            Core platform
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-center gap-2 rounded-full border border-wasplex-cyan/25 bg-wasplex-cyan/10 px-3 py-2 text-xs font-semibold text-wasplex-cyan"
                >
                    <span
                        class="size-2 rounded-full bg-wasplex-cyan shadow-[0_0_12px_rgba(25,199,255,0.9)]"
                    />
                    P000 actif
                </div>
            </header>

            <div class="grid flex-1 items-center gap-14 py-16 lg:grid-cols-[1.15fr_0.85fr]">
                <div>
                    <p
                        class="mb-5 text-sm font-semibold tracking-[0.3em] text-wasplex-orange uppercase"
                    >
                        Fondation technique
                    </p>
                    <h1
                        class="max-w-3xl text-5xl leading-[0.96] font-black tracking-[-0.055em] sm:text-6xl lg:text-7xl"
                    >
                        Le noyau commence par une base
                        <span
                            class="bg-gradient-to-r from-wasplex-cyan via-white to-wasplex-gold bg-clip-text text-transparent"
                            >claire et vérifiable.</span
                        >
                    </h1>
                    <p class="mt-7 max-w-2xl text-base leading-7 text-white/62 sm:text-lg">
                        Monolithe modulaire, données protégées, observabilité et design commun.
                        Aucun module métier n'est simulé dans P000.
                    </p>

                    <div class="mt-9 flex flex-wrap gap-3 text-sm text-white/65">
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2"
                            >PHP 8.4</span
                        >
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2"
                            >TypeScript strict</span
                        >
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2"
                            >Logs JSON</span
                        >
                        <span class="rounded-full border border-white/10 bg-white/5 px-4 py-2"
                            >Trace ID</span
                        >
                    </div>
                </div>

                <aside
                    class="relative rounded-[2rem] border border-white/10 bg-white/[0.055] p-5 shadow-2xl backdrop-blur-xl sm:p-7"
                >
                    <div class="mb-7 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-bold">État du socle</p>
                            <p class="mt-1 text-xs text-white/40">Branche P000 · aucun métier</p>
                        </div>
                        <span
                            class="rounded-lg bg-wasplex-gold/10 px-2.5 py-1 text-xs font-bold text-wasplex-gold"
                            >BASELINE</span
                        >
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                        <article
                            v-for="item in foundations"
                            :key="item.label"
                            class="rounded-2xl border border-white/8 bg-wasplex-night/55 p-4"
                        >
                            <p class="text-xs tracking-[0.18em] text-white/35 uppercase">
                                {{ item.label }}
                            </p>
                            <p class="mt-3 font-bold text-white/90">{{ item.value }}</p>
                        </article>
                    </div>

                    <div
                        class="mt-5 rounded-2xl border border-wasplex-cyan/15 bg-wasplex-cyan/[0.065] p-4"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-9 place-items-center rounded-xl bg-wasplex-cyan/15 text-wasplex-cyan"
                                >✓</span
                            >
                            <div>
                                <p class="text-sm font-semibold">Health endpoint prêt</p>
                                <p class="mt-0.5 text-xs text-white/40">
                                    GET /api/health · X-Trace-Id
                                </p>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <footer class="border-t border-white/10 py-5 text-xs text-white/35">
                Wasplex Core · P000 établit la plateforme, P001 établira les comptes et les espaces.
            </footer>
        </section>
    </main>
</template>
