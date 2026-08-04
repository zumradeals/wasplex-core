<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    campaign: {
        id: string;
        name: string;
        brand: string;
    };
    explanation: {
        matchId: string;
        campaignId: string;
        decision: 'eligible' | 'ineligible' | 'withheld';
        scoreBand: 'high' | 'medium' | 'low' | null;
        title: string;
        summary: string;
        reasons: string[];
        evaluatedAt: string;
        actions: Array<{ label: string; href: string }>;
        advertiserReceivedIdentity: false;
        financialOperationCreated: false;
    };
}>();

const date = new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
}).format(new Date(props.explanation.evaluatedAt));

const decisionLabel = {
    eligible: 'Compatible',
    ineligible: 'Non retenue',
    withheld: 'Décision protégée',
}[props.explanation.decision];
</script>

<template>
    <Head title="Pourquoi cette publicité ?" />
    <main class="min-h-screen bg-[#03101e] text-white">
        <div
            class="mx-auto min-h-screen max-w-md border-x border-white/5 bg-wasplex-night shadow-2xl"
        >
            <header
                class="sticky top-0 z-20 border-b border-white/10 bg-wasplex-night/90 px-5 py-4 backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <WasplexMark />
                    <Link
                        href="/mon-espace/profil-intelligent"
                        class="text-xs font-black text-wasplex-cyan"
                    >
                        Profil intelligent
                    </Link>
                </div>
                <div class="mt-4"><SpaceSwitcher :spaces="spaces" /></div>
            </header>

            <section class="px-5 py-7">
                <p class="text-xs font-black tracking-[0.2em] text-wasplex-cyan uppercase">
                    Transparence publicitaire
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight">
                    Pourquoi cette publicité ?
                </h1>
                <p class="mt-3 text-sm leading-6 text-white/45">
                    Wasplex explique la décision avec des raisons compréhensibles, sans dévoiler le
                    fonctionnement interne de sécurité ni transmettre votre profil à l’annonceur.
                </p>

                <article
                    class="mt-7 overflow-hidden rounded-[2rem] border border-wasplex-cyan/15 bg-gradient-to-br from-wasplex-blue/45 to-white/[0.035] p-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-black text-wasplex-gold">{{ campaign.brand }}</p>
                            <h2 class="mt-2 text-xl font-black">{{ campaign.name }}</h2>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-3 py-1 text-[0.65rem] font-black uppercase"
                            :class="
                                explanation.decision === 'eligible'
                                    ? 'bg-emerald-300/12 text-emerald-200'
                                    : explanation.decision === 'withheld'
                                      ? 'bg-amber-300/12 text-amber-200'
                                      : 'bg-white/7 text-white/45'
                            "
                        >
                            {{ decisionLabel }}
                        </span>
                    </div>
                    <h3 class="mt-6 text-lg font-black">{{ explanation.title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-white/48">
                        {{ explanation.summary }}
                    </p>
                    <p class="mt-4 text-[0.68rem] text-white/28">Évaluée le {{ date }}</p>
                </article>

                <section class="mt-8">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                        Raisons compréhensibles
                    </p>
                    <div v-if="explanation.reasons.length" class="mt-4 space-y-3">
                        <article
                            v-for="reason in explanation.reasons"
                            :key="reason"
                            class="flex gap-3 rounded-2xl border border-white/8 bg-white/[0.035] p-4"
                        >
                            <span
                                class="grid size-7 shrink-0 place-items-center rounded-full bg-wasplex-cyan/10 text-xs font-black text-wasplex-cyan"
                            >
                                ✓
                            </span>
                            <p class="text-sm leading-6 text-white/52">{{ reason }}</p>
                        </article>
                    </div>
                    <p
                        v-else
                        class="mt-4 rounded-2xl border border-white/8 bg-white/[0.03] p-4 text-sm leading-6 text-white/42"
                    >
                        Aucun détail supplémentaire n’est affiché pour cette décision protégée.
                    </p>
                </section>

                <section class="mt-8 grid gap-3">
                    <Link
                        v-for="action in explanation.actions"
                        :key="action.href"
                        :href="action.href"
                        class="rounded-2xl border border-white/10 px-4 py-3 text-center text-sm font-black text-white/60"
                    >
                        {{ action.label }}
                    </Link>
                </section>

                <aside
                    class="mt-8 rounded-[1.75rem] border border-emerald-300/15 bg-emerald-300/[0.045] p-5"
                >
                    <p class="font-black text-emerald-200">Votre identité reste protégée</p>
                    <p class="mt-2 text-xs leading-5 text-white/40">
                        L’annonceur n’a reçu ni votre nom, ni votre compte, ni vos réponses
                        individuelles. Cette explication n’a déclenché aucun paiement, crédit ou
                        mouvement Wallet.
                    </p>
                </aside>
            </section>

            <nav
                class="sticky bottom-0 mt-6 grid grid-cols-4 border-t border-white/10 bg-wasplex-night/95 px-3 py-3 text-center text-[0.68rem] font-bold text-white/40 backdrop-blur-xl"
            >
                <Link href="/mon-espace">Espace</Link><span>Feed</span
                ><Link href="/wallet">Wallet</Link
                ><Link href="/mon-espace/profil-intelligent" class="text-wasplex-cyan"
                    >Profil</Link
                >
            </nav>
        </div>
    </main>
</template>
