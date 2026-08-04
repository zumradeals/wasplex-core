<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
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
    wallet: { balances: { available: number } };
}>();

const advertiser = useForm({ organization_name: '' });
const services = [
    { label: 'Feed', href: '#', detail: 'Bientôt disponible' },
    {
        label: 'Wallet',
        href: '/wallet',
        detail: `${new Intl.NumberFormat('fr-FR').format(props.wallet.balances.available)} WP disponible`,
    },
    {
        label: 'Profil intelligent',
        href: '/mon-espace/profil-intelligent',
        detail: 'Vos réponses et consentements',
    },
    { label: 'Carte', href: '#', detail: 'Bientôt disponible' },
];
</script>

<template>
    <Head title="Mon Espace" />
    <main class="min-h-screen bg-[#03101e] text-white">
        <div
            class="mx-auto min-h-screen max-w-md border-x border-white/5 bg-wasplex-night shadow-2xl"
        >
            <header
                class="sticky top-0 z-10 border-b border-white/10 bg-wasplex-night/90 px-5 py-4 backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <WasplexMark />
                    <button
                        class="text-xs font-bold text-white/45 hover:text-white"
                        @click="router.post('/deconnexion')"
                    >
                        Quitter
                    </button>
                </div>
                <div class="mt-4"><SpaceSwitcher :spaces="spaces" /></div>
            </header>

            <section class="px-5 py-7">
                <p class="text-sm text-white/45">Bonjour {{ account.displayName }}</p>
                <h1 class="mt-2 text-3xl font-black">Mon Espace</h1>
                <p class="mt-3 leading-6 text-white/45">
                    Votre identité, vos choix et tous vos services au même endroit.
                </p>

                <div class="mt-8 grid grid-cols-2 gap-3">
                    <article
                        class="col-span-2 rounded-3xl bg-gradient-to-br from-wasplex-blue to-[#0e3c98] p-6 shadow-xl"
                    >
                        <p class="text-xs font-bold tracking-widest text-white/60 uppercase">
                            Identité Wasplex
                        </p>
                        <p class="mt-4 text-xl font-black">Compte universel actif</p>
                        <p class="mt-2 text-sm text-white/60">
                            Un compte, plusieurs espaces strictement séparés.
                        </p>
                    </article>
                    <Link
                        v-for="item in services"
                        :key="item.label"
                        :href="item.href"
                        class="rounded-3xl border border-white/8 bg-white/5 p-5"
                    >
                        <div class="size-9 rounded-2xl bg-wasplex-cyan/10" />
                        <p class="mt-6 font-bold">{{ item.label }}</p>
                        <p class="mt-1 text-xs text-white/35">{{ item.detail }}</p>
                    </Link>
                </div>

                <section
                    v-if="!spaces.some((space) => space.kind === 'advertiser')"
                    class="mt-7 rounded-3xl border border-wasplex-gold/20 bg-wasplex-gold/[0.06] p-5"
                >
                    <p class="font-black text-wasplex-gold">Vous représentez une activité ?</p>
                    <p class="mt-2 text-sm leading-6 text-white/45">
                        Activez un Studio Annonceur séparé sans créer un autre compte.
                    </p>
                    <form class="mt-4" @submit.prevent="advertiser.post('/espaces/annonceur')">
                        <input
                            v-model="advertiser.organization_name"
                            required
                            class="wasplex-input"
                            placeholder="Nom de l’activité ou de l’entreprise"
                        />
                        <span
                            v-if="advertiser.errors.organization_name"
                            class="mt-2 block text-sm text-red-300"
                            >{{ advertiser.errors.organization_name }}</span
                        >
                        <button
                            class="mt-3 w-full rounded-2xl bg-wasplex-gold px-4 py-3 text-sm font-black text-wasplex-night"
                            type="submit"
                        >
                            Activer mon Studio
                        </button>
                    </form>
                </section>
            </section>

            <nav
                class="sticky bottom-0 mt-6 grid grid-cols-4 border-t border-white/10 bg-wasplex-night/95 px-3 py-3 text-center text-[0.68rem] font-bold text-white/40 backdrop-blur-xl"
            >
                <span class="text-wasplex-cyan">Espace</span><span>Feed</span
                ><Link href="/wallet">Wallet</Link
                ><Link href="/mon-espace/profil-intelligent">Profil</Link>
            </nav>
        </div>
    </main>
</template>
