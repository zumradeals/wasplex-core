<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
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
    active: 'dashboard' | 'campaigns' | 'wallet' | 'payments' | 'economy' | 'advertising';
}>();

const navigation = [
    { key: 'dashboard', label: 'Centre de pilotage', href: '/administration' },
    { key: 'campaigns', label: 'Revue des campagnes', href: '/administration/campagnes' },
    { key: 'advertising', label: 'Matching & confidentialité', href: '/administration/publicite' },
    { key: 'wallet', label: 'Wallet & dépôts', href: '/administration/wallet' },
    {
        key: 'payments',
        label: 'Paiements & GeniusPay',
        href: '/administration/paiements/geniuspay',
    },
    { key: 'economy', label: 'Économie', href: '/administration/economie' },
] as const;
</script>

<template>
    <main class="min-h-screen bg-[#030a12] text-white">
        <div class="min-h-screen md:grid md:grid-cols-[18rem_1fr]">
            <aside class="hidden border-r border-white/8 bg-[#07111d] p-6 md:block">
                <WasplexMark />
                <div class="mt-7"><SpaceSwitcher :spaces="props.spaces" /></div>
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
                        :key="item.key"
                        :href="item.href"
                        class="block rounded-xl px-4 py-3 transition"
                        :class="
                            props.active === item.key
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

            <section class="min-w-0">
                <header class="border-b border-white/8 bg-[#07111d]/95 px-4 py-4 md:hidden">
                    <div class="flex items-center justify-between gap-4">
                        <WasplexMark />
                        <button
                            class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black text-white/55"
                            @click="router.post('/deconnexion')"
                        >
                            Sortir
                        </button>
                    </div>
                    <div class="mt-4"><SpaceSwitcher :spaces="props.spaces" /></div>
                    <nav class="mt-4 flex gap-2 overflow-x-auto pb-1">
                        <Link
                            v-for="item in navigation"
                            :key="item.key"
                            :href="item.href"
                            class="shrink-0 rounded-xl border px-3 py-2 text-xs font-black"
                            :class="
                                props.active === item.key
                                    ? 'border-cyan-300 bg-cyan-300 text-[#04111e]'
                                    : 'border-white/8 bg-white/[0.035] text-white/45'
                            "
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </header>

                <div class="px-4 py-6 sm:px-6 md:p-8 xl:p-12">
                    <slot />
                </div>
            </section>
        </div>
    </main>
</template>
