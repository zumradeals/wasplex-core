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

defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    active: 'dashboard' | 'profile' | 'brands' | 'media' | 'wallet';
}>();

const navigation = [
    { key: 'dashboard', label: 'Vue d’ensemble', href: '/studio', caption: 'Pilotage' },
    { key: 'profile', label: 'Profil annonceur', href: '/studio/profil', caption: 'Organisation' },
    { key: 'brands', label: 'Marques', href: '/studio/marques', caption: 'Identités' },
    { key: 'media', label: 'Médiathèque', href: '/studio/medias', caption: 'Images & vidéos' },
    { key: 'wallet', label: 'Budget', href: '/studio/wallet', caption: 'Wallet annonceur' },
] as const;
</script>

<template>
    <main class="min-h-screen bg-[#050d17] text-white lg:grid lg:grid-cols-[18rem_1fr]">
        <aside
            class="border-b border-white/8 bg-[#071423] px-5 py-5 lg:sticky lg:top-0 lg:min-h-screen lg:border-r lg:border-b-0 lg:px-6 lg:py-7"
        >
            <div class="flex items-center justify-between lg:block">
                <WasplexMark />
                <button
                    class="rounded-xl border border-white/10 px-3 py-2 text-xs font-bold text-white/45 transition hover:border-white/20 hover:text-white lg:mt-7"
                    @click="router.post('/deconnexion')"
                >
                    Déconnexion
                </button>
            </div>

            <div class="mt-5">
                <SpaceSwitcher :spaces="spaces" />
            </div>

            <div class="mt-6 rounded-2xl border border-cyan-400/15 bg-cyan-400/[0.055] p-4">
                <p class="text-[10px] font-black tracking-[0.18em] text-cyan-300 uppercase">
                    Studio actif
                </p>
                <p class="mt-2 truncate text-sm font-black">{{ activeSpace.label }}</p>
                <p class="mt-1 text-xs text-white/35">Contexte annonceur isolé</p>
            </div>

            <nav class="mt-7 hidden space-y-2 lg:block">
                <Link
                    v-for="item in navigation"
                    :key="item.key"
                    :href="item.href"
                    class="group flex items-center gap-3 rounded-2xl px-4 py-3 transition"
                    :class="
                        active === item.key
                            ? 'bg-gradient-to-r from-sky-500 to-cyan-400 text-[#04111e] shadow-lg shadow-sky-500/10'
                            : 'text-white/45 hover:bg-white/[0.045] hover:text-white'
                    "
                >
                    <span
                        class="size-2.5 rounded-full border"
                        :class="
                            active === item.key
                                ? 'border-[#04111e]/40 bg-[#04111e]'
                                : 'border-white/20 bg-white/5 group-hover:bg-white/30'
                        "
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-black">{{ item.label }}</span>
                        <span
                            class="mt-0.5 block text-[10px] font-semibold"
                            :class="active === item.key ? 'text-[#04111e]/60' : 'text-white/25'"
                            >{{ item.caption }}</span
                        >
                    </span>
                </Link>
            </nav>

            <div class="mt-auto hidden pt-10 lg:block">
                <p class="text-xs text-white/25">Connecté en tant que</p>
                <p class="mt-1 truncate text-sm font-black">{{ account.displayName }}</p>
            </div>
        </aside>

        <section class="min-w-0 px-4 py-6 sm:px-8 lg:px-12 lg:py-10 xl:px-16">
            <nav class="mb-6 flex gap-2 overflow-x-auto pb-2 lg:hidden">
                <Link
                    v-for="item in navigation"
                    :key="item.key"
                    :href="item.href"
                    class="shrink-0 rounded-full border px-4 py-2 text-xs font-black"
                    :class="
                        active === item.key
                            ? 'border-cyan-300 bg-cyan-300 text-[#04111e]'
                            : 'border-white/10 bg-white/[0.035] text-white/45'
                    "
                >
                    {{ item.label }}
                </Link>
            </nav>

            <slot />
        </section>
    </main>
</template>
