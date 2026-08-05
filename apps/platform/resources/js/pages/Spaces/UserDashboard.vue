<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import UserLayout from '@/layouts/UserLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Wallet = {
    unit: string;
    balances: {
        available: number;
    };
};

const props = defineProps<{
    account: { id: string; displayName: string | null };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    wallet: Wallet;
}>();

const advertiser = useForm({ organization_name: '' });
const services = [
    {
        label: 'Feed',
        href: '/mon-espace/pour-toi',
        detail: 'Publicités compatibles et transparentes',
    },
    {
        label: 'Wallet',
        href: '/wallet',
        detail: `${new Intl.NumberFormat('fr-FR').format(props.wallet.balances.available)} ${props.wallet.unit} disponible`,
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
    <UserLayout :account="account" :spaces="spaces" :wallet="wallet" active="space">
        <section class="mx-auto max-w-4xl px-5 py-7 sm:px-7 sm:py-10">
            <p class="text-sm text-white/45">Bonjour {{ account.displayName }}</p>
            <h1 class="mt-2 text-3xl font-black sm:text-4xl">Mon Espace</h1>
            <p class="mt-3 max-w-xl leading-6 text-white/45">
                Votre identité, vos choix et tous vos services au même endroit.
            </p>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <article
                    class="col-span-2 rounded-3xl bg-gradient-to-br from-wasplex-blue to-[#0e3c98] p-6 shadow-xl sm:col-span-4"
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
                    class="rounded-3xl border border-white/8 bg-white/5 p-5 transition hover:-translate-y-0.5 hover:border-wasplex-cyan/20 hover:bg-white/[0.07] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-wasplex-cyan"
                >
                    <div class="size-9 rounded-2xl bg-wasplex-cyan/10" />
                    <p class="mt-6 font-bold">{{ item.label }}</p>
                    <p class="mt-1 text-xs leading-5 text-white/35">{{ item.detail }}</p>
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
                    >
                        {{ advertiser.errors.organization_name }}
                    </span>
                    <button
                        class="mt-3 w-full rounded-2xl bg-wasplex-gold px-4 py-3 text-sm font-black text-wasplex-night"
                        type="submit"
                    >
                        Activer mon Studio
                    </button>
                </form>
            </section>
        </section>
    </UserLayout>
</template>
