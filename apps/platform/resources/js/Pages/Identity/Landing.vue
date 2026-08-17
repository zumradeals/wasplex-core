<script setup lang="ts">
import PhoneQuickConnect from '@/Components/PhoneQuickConnect.vue';

withDefaults(
    defineProps<{
        initialMode?: 'login' | 'register';
    }>(),
    {
        initialMode: 'login',
    },
);
</script>

<template>
    <main class="bg-wpx-navy-950 flex min-h-screen justify-center">
        <div class="flex w-full max-w-md flex-col gap-6 px-6 py-12">
            <section class="flex flex-col items-center gap-3 text-center">
                <a href="/" aria-label="Retour au Feed public">
                    <img
                        src="/brand/wasplex-logo-transparent.png"
                        alt="Wasplex"
                        class="h-24 w-24 object-contain"
                        style="filter: drop-shadow(0 10px 24px rgba(79, 163, 255, 0.25))"
                    />
                </a>
                <h1 class="text-wpx-white-soft text-xl leading-snug font-bold">
                    Ton attention a de la valeur.<br />Wasplex te la rend.
                </h1>
                <p class="text-wpx-muted-dark max-w-[290px] text-sm leading-relaxed">
                    Regarde des publicités, gagne des WP, retire-les sur ton Mobile Money.
                </p>
            </section>

            <section class="grid grid-cols-3 gap-2">
                <div
                    class="rounded-wpx-md bg-wpx-navy-850 border-wpx-border-dark flex flex-col items-center gap-0.5 border p-3 text-center"
                >
                    <span class="text-wpx-white-soft text-sm font-bold">Gratuit</span>
                    <span class="text-wpx-muted-dark text-[11px]">Inscription simple</span>
                </div>
                <div
                    class="rounded-wpx-md bg-wpx-navy-850 border-wpx-border-dark flex flex-col items-center gap-0.5 border p-3 text-center"
                >
                    <span class="text-wpx-gold text-sm font-bold">+30 WP</span>
                    <span class="text-wpx-muted-dark text-[11px]">Par pub vue</span>
                </div>
                <div
                    class="rounded-wpx-md bg-wpx-navy-850 border-wpx-border-dark flex flex-col items-center gap-0.5 border p-3 text-center"
                >
                    <span class="text-wpx-orange text-sm font-bold">Cash</span>
                    <span class="text-wpx-muted-dark text-[11px]">Mobile Money</span>
                </div>
            </section>

            <PhoneQuickConnect :initial-mode="initialMode" />

            <p class="text-wpx-muted-dark text-center text-[11px] leading-relaxed">
                Un seul compte Wasplex donne accès à ton espace, à ton wallet et, si besoin, à ton Studio Annonceur.
                <br /><br />
                En continuant, tu acceptes les conditions d'utilisation de Wasplex.
            </p>
        </div>
    </main>
</template>
