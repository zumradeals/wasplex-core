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

type Wallet = {
    unit: string;
    balances: {
        available: number;
    };
};

const props = defineProps<{
    account: { id: string; displayName: string | null };
    spaces: Space[];
    wallet: Wallet;
    active: 'space' | 'feed' | 'wallet' | 'profile';
    immersive?: boolean;
}>();

const navigation = [
    { key: 'space', label: 'Espace', href: '/mon-espace', glyph: '⌂' },
    { key: 'feed', label: 'Feed', href: '/mon-espace/pour-toi', glyph: '▶' },
    { key: 'wallet', label: 'Wallet', href: '/wallet', glyph: 'W' },
    { key: 'profile', label: 'Profil', href: '/mon-espace/profil-intelligent', glyph: '◎' },
] as const;

const formattedBalance = new Intl.NumberFormat('fr-FR').format(props.wallet.balances.available);
</script>

<template>
    <main class="min-h-screen bg-[#020b14] text-white">
        <div
            class="mx-auto flex min-h-screen max-w-6xl flex-col border-x border-white/5 bg-wasplex-night shadow-2xl"
        >
            <header
                class="sticky top-0 z-40 border-b border-white/10 bg-wasplex-night/92 px-4 py-3 backdrop-blur-xl sm:px-6"
            >
                <div class="flex items-center justify-between gap-4">
                    <WasplexMark />
                    <div class="flex items-center gap-3">
                        <Link
                            href="/wallet"
                            class="rounded-full border border-wasplex-cyan/15 bg-wasplex-cyan/[0.06] px-3 py-1.5 text-xs font-black text-wasplex-cyan"
                            aria-label="Ouvrir le Wallet"
                        >
                            {{ formattedBalance }} {{ wallet.unit }}
                        </Link>
                        <button
                            class="text-xs font-bold text-white/45 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-wasplex-cyan"
                            type="button"
                            @click="router.post('/deconnexion')"
                        >
                            Quitter
                        </button>
                    </div>
                </div>
                <div v-if="!immersive" class="mt-3 max-w-md">
                    <SpaceSwitcher :spaces="spaces" />
                </div>
            </header>

            <div class="min-h-0 flex-1">
                <slot />
            </div>

            <nav
                class="sticky bottom-0 z-40 grid grid-cols-4 border-t border-white/10 bg-wasplex-night/95 px-2 py-2 backdrop-blur-xl sm:px-4"
                aria-label="Navigation de l’espace utilisateur"
            >
                <Link
                    v-for="item in navigation"
                    :key="item.key"
                    :href="item.href"
                    class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-2xl text-[0.68rem] font-black transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-wasplex-cyan"
                    :class="
                        active === item.key
                            ? 'bg-wasplex-cyan/10 text-wasplex-cyan'
                            : 'text-white/38 hover:bg-white/5 hover:text-white/70'
                    "
                    :aria-current="active === item.key ? 'page' : undefined"
                >
                    <span class="text-sm" aria-hidden="true">{{ item.glyph }}</span>
                    <span>{{ item.label }}</span>
                </Link>
            </nav>
        </div>
    </main>
</template>
