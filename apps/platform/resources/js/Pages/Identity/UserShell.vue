<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import SubscriptionPanel from '@/Components/SubscriptionPanel.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const tabs = [
    { key: 'feed', label: 'Feed', icon: '🏠' },
    { key: 'fonds', label: 'Fonds', icon: '🎯' },
    { key: 'wallet', label: 'Wallet', icon: '💳' },
    { key: 'alertes', label: 'Alertes', icon: '🔔' },
    { key: 'espace', label: 'Mon Espace', icon: '👤' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('feed');

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
}
</script>

<template>
    <div class="bg-wpx-navy-950 flex min-h-screen justify-center">
        <div class="bg-wpx-navy-950 flex w-full max-w-md flex-col">
            <header class="border-wpx-border-dark bg-wpx-navy-850 flex items-center justify-between border-b px-4 py-3">
                <div class="flex items-center gap-2">
                    <div class="rounded-wpx-sm bg-white p-1">
                        <img src="/brand/wasplex-logo-full.png" alt="Wasplex" class="h-6 w-6 object-contain" />
                    </div>
                    <span class="text-wpx-white-soft text-sm font-semibold">Wasplex</span>
                </div>
                <div class="flex items-center gap-2">
                    <SpaceSwitcher
                        variant="dark"
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button class="text-wpx-muted-dark text-xs hover:underline" @click="logout">Déconnexion</button>
                </div>
            </header>

            <main class="flex-1 px-4 py-6">
                <div
                    v-if="activeTab === 'feed'"
                    class="rounded-wpx-lg from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 shadow-wpx-card-dark flex h-[28rem] flex-col items-center justify-center gap-4 bg-gradient-to-b p-6 text-center"
                >
                    <div class="rounded-wpx-lg bg-white p-3 shadow">
                        <img src="/brand/wasplex-logo-full.png" alt="Wasplex" class="h-16 w-16 object-contain" />
                    </div>
                    <p class="text-wpx-white-soft text-sm font-semibold">Le Feed arrive bientôt</p>
                    <p class="text-wpx-muted-dark max-w-[16rem] text-xs">
                        Les publicités qualifiées et le crédit automatique de WP seront livrés avec le Super Moteur
                        (P008-P009). Ton compte est prêt à les recevoir dès leur mise en service.
                    </p>
                </div>
                <div v-else-if="activeTab === 'espace'" class="flex flex-col gap-4">
                    <h1 class="text-wpx-white-soft text-lg font-semibold">Mon Espace</h1>
                    <section class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 p-4">
                        <p class="text-wpx-muted-dark text-sm">Compte</p>
                        <p class="text-wpx-white-soft font-mono text-xs">{{ page.props.auth.account.id }}</p>
                        <p class="text-wpx-muted-dark mt-2 text-sm">
                            MFA : {{ page.props.auth.account.mfa_enabled ? 'activée' : 'non activée' }}
                        </p>
                    </section>
                    <SubscriptionPanel />
                    <p class="text-wpx-muted-dark text-xs">
                        Le profil intelligent et les consentements arrivent avec les chantiers suivants.
                    </p>
                </div>
                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 text-wpx-muted-dark flex h-64 items-center justify-center text-sm"
                >
                    {{ tabs.find((t) => t.key === activeTab)?.label }} — bientôt disponible
                </div>
            </main>

            <nav class="border-wpx-border-dark bg-wpx-navy-850 grid grid-cols-5 border-t">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex flex-col items-center gap-1 py-2 text-xs"
                    :class="activeTab === tab.key ? 'text-wpx-gold' : 'text-wpx-muted-dark'"
                    @click="activeTab = tab.key"
                >
                    <span class="text-lg">{{ tab.icon }}</span>
                    {{ tab.label }}
                </button>
            </nav>
        </div>
    </div>
</template>
