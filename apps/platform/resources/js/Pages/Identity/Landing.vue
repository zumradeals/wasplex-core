<script setup lang="ts">
import PhoneQuickConnect from '@/Components/PhoneQuickConnect.vue';

const features = [
    { icon: '📺', label: 'Regarde des publicités qui rapportent des WP' },
    { icon: '💸', label: 'Retire tes WP via Mobile Money' },
    { icon: '🔥', label: 'Bonus de régularité au fil de tes visites' },
    { icon: '🤝', label: 'Parrainage et communauté Wasplex' },
] as const;

function scrollToConnect(): void {
    document.getElementById('connexion-rapide')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>

<template>
    <main class="bg-wpx-navy-950 flex min-h-screen justify-center">
        <div class="flex w-full max-w-md flex-col gap-8 px-5 py-10">
            <!-- Hero -->
            <section class="flex flex-col items-center gap-4 text-center">
                <div class="rounded-wpx-xl bg-white p-3 shadow-lg">
                    <img src="/brand/wasplex-logo-full.png" alt="Wasplex" class="h-28 w-28 object-contain" />
                </div>
                <h1 class="text-wpx-white-soft text-2xl leading-tight font-bold">
                    Ton attention a de la valeur.<br />Wasplex te la rend.
                </h1>
                <p class="text-wpx-muted-dark text-sm">
                    Un compte unique, gratuit et rapide à créer, pour transformer ton attention en WP retirables.
                </p>
                <button
                    type="button"
                    class="rounded-wpx-full from-wpx-blue to-wpx-cyan text-wpx-navy-950 ease-wpx-standard w-full bg-gradient-to-br px-6 py-3 text-sm font-semibold shadow transition duration-200"
                    @click="scrollToConnect"
                >
                    Commencer maintenant
                </button>
            </section>

            <!-- Cartes de valeur -->
            <section class="grid grid-cols-3 gap-2">
                <div
                    class="rounded-wpx-md shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col items-center gap-1 p-3 text-center"
                >
                    <span class="text-lg">🆓</span>
                    <span class="text-wpx-white-soft text-xs font-semibold">Gratuit</span>
                    <span class="text-wpx-muted-dark text-[11px]">Inscription simple</span>
                </div>
                <div
                    class="rounded-wpx-md shadow-wpx-card-dark from-wpx-orange to-wpx-gold flex flex-col items-center gap-1 bg-gradient-to-br p-3 text-center"
                >
                    <span class="text-lg">💰</span>
                    <span class="text-wpx-navy-950 text-xs font-semibold">1 WP = 1 FCFA</span>
                    <span class="text-wpx-navy-950/70 text-[11px]">Valeur garantie</span>
                </div>
                <div
                    class="rounded-wpx-md shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col items-center gap-1 p-3 text-center"
                >
                    <span class="text-lg">📱</span>
                    <span class="text-wpx-white-soft text-xs font-semibold">Mobile Money</span>
                    <span class="text-wpx-muted-dark text-[11px]">Retrait simple</span>
                </div>
            </section>

            <!-- Fonctionnalités -->
            <section class="flex flex-col gap-2">
                <div
                    v-for="feature in features"
                    :key="feature.label"
                    class="rounded-wpx-md bg-wpx-navy-850 border-wpx-border-dark flex items-center gap-3 border p-3"
                >
                    <span class="text-xl">{{ feature.icon }}</span>
                    <span class="text-wpx-white-soft text-sm">{{ feature.label }}</span>
                </div>
            </section>

            <!-- Connexion rapide -->
            <section class="flex flex-col gap-3">
                <h2 class="text-wpx-white-soft text-center text-sm font-semibold tracking-wide uppercase">
                    Connexion rapide
                </h2>
                <PhoneQuickConnect />
            </section>

            <p class="text-wpx-muted-dark text-center text-[11px]">
                En continuant, tu acceptes les conditions d'utilisation de Wasplex.
            </p>
        </div>
    </main>
</template>
