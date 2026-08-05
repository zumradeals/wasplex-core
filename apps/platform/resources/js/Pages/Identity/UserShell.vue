<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const tabs = [
    { key: 'feed', label: 'Feed', icon: '🏠' },
    { key: 'fonds', label: 'Fonds', icon: '🎯' },
    { key: 'wallet', label: 'Wallet', icon: '💳' },
    { key: 'alertes', label: 'Alertes', icon: '🔔' },
    { key: 'espace', label: 'Mon Espace', icon: '👤' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('espace');

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
}
</script>

<template>
    <div class="bg-wasplex-surface flex min-h-screen justify-center">
        <div class="bg-wasplex-surface flex w-full max-w-md flex-col">
            <header class="flex items-center justify-between border-b border-black/5 bg-white px-4 py-3">
                <span class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-3 py-1 text-sm font-semibold">
                    Wasplex
                </span>
                <div class="flex items-center gap-2">
                    <SpaceSwitcher
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button class="text-wasplex-black/60 text-xs hover:underline" @click="logout">Déconnexion</button>
                </div>
            </header>

            <main class="flex-1 px-4 py-6">
                <div v-if="activeTab === 'espace'" class="flex flex-col gap-4">
                    <h1 class="text-wasplex-black text-lg font-semibold">Mon Espace</h1>
                    <section class="rounded-wasplex-lg shadow-wasplex-card bg-white p-4">
                        <p class="text-wasplex-black/60 text-sm">Compte</p>
                        <p class="text-wasplex-black font-mono text-xs">{{ page.props.auth.account.id }}</p>
                        <p class="text-wasplex-black/60 mt-2 text-sm">
                            MFA : {{ page.props.auth.account.mfa_enabled ? 'activée' : 'non activée' }}
                        </p>
                    </section>
                    <p class="text-wasplex-black/50 text-xs">
                        Le profil intelligent, les consentements et le score de complétude arrivent avec les chantiers
                        suivants (P004+).
                    </p>
                </div>
                <div
                    v-else
                    class="rounded-wasplex-lg text-wasplex-black/50 shadow-wasplex-card flex h-64 items-center justify-center bg-white text-sm"
                >
                    {{ tabs.find((t) => t.key === activeTab)?.label }} — bientôt disponible
                </div>
            </main>

            <nav class="grid grid-cols-5 border-t border-black/5 bg-white">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex flex-col items-center gap-1 py-2 text-xs"
                    :class="activeTab === tab.key ? 'text-wasplex-gold' : 'text-wasplex-black/50'"
                    @click="activeTab = tab.key"
                >
                    <span class="text-lg">{{ tab.icon }}</span>
                    {{ tab.label }}
                </button>
            </nav>
        </div>
    </div>
</template>
