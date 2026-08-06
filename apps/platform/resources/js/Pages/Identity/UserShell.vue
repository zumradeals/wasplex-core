<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import SubscriptionPanel from '@/Components/SubscriptionPanel.vue';
import SmartProfilePanel from '@/Components/SmartProfilePanel.vue';
import ConsentsPanel from '@/Components/ConsentsPanel.vue';
import EligibleCampaignsPanel from '@/Components/EligibleCampaignsPanel.vue';
import FeedPanel from '@/Components/FeedPanel.vue';
import WalletPanel from '@/Components/WalletPanel.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const tabs = [
    { key: 'feed', label: 'Feed', icon: '🎬' },
    { key: 'fonds', label: 'Fonds', icon: '🎯' },
    { key: 'wallet', label: 'Wallet', icon: '💳' },
    { key: 'alertes', label: 'Alertes', icon: '🔔' },
    { key: 'espace', label: 'Mon Espace', icon: '👤' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('feed');
const walletPanel = ref<InstanceType<typeof WalletPanel> | null>(null);

function onBalanceChanged(): void {
    void walletPanel.value?.load();
}

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
}
</script>

<template>
    <div class="bg-wpx-navy-950 flex min-h-screen justify-center">
        <div class="bg-wpx-navy-950 flex w-full max-w-md flex-col">
            <header
                v-if="activeTab !== 'feed'"
                class="border-wpx-border-dark bg-wpx-navy-850 flex items-center justify-between border-b px-4 py-3"
            >
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

            <main class="flex-1" :class="activeTab === 'feed' ? '' : 'px-4 py-6'">
                <FeedPanel v-if="activeTab === 'feed'" @balance-changed="onBalanceChanged" />
                <div v-else-if="activeTab === 'wallet'" class="flex flex-col gap-4">
                    <h1 class="text-wpx-white-soft text-lg font-semibold">Wallet</h1>
                    <WalletPanel ref="walletPanel" />
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
                    <EligibleCampaignsPanel />
                    <SmartProfilePanel />
                    <ConsentsPanel />
                    <SubscriptionPanel />
                </div>
                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 text-wpx-muted-dark flex h-64 flex-col items-center justify-center gap-2 text-sm"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-full text-2xl"
                        :class="activeTab === 'fonds' ? 'bg-wpx-gold/10' : 'bg-wpx-danger/10'"
                    >
                        {{ tabs.find((t) => t.key === activeTab)?.icon }}
                    </span>
                    {{ tabs.find((t) => t.key === activeTab)?.label }} — bientôt disponible
                </div>
            </main>

            <nav class="border-wpx-border-dark bg-wpx-navy-850 grid grid-cols-5 items-end border-t px-1 pt-1 pb-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex flex-col items-center gap-1 py-1 text-[11px]"
                    :class="activeTab === tab.key && tab.key !== 'wallet' ? 'text-wpx-gold' : 'text-wpx-muted-dark'"
                    @click="activeTab = tab.key"
                >
                    <span
                        v-if="tab.key === 'wallet'"
                        class="from-wpx-orange to-wpx-gold shadow-wpx-card-dark -mt-5 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br text-xl"
                        :class="activeTab === 'wallet' ? 'ring-wpx-gold ring-2' : ''"
                    >
                        {{ tab.icon }}
                    </span>
                    <span
                        v-else
                        class="flex h-9 w-9 items-center justify-center rounded-full text-lg"
                        :class="activeTab === tab.key ? 'bg-wpx-navy-750' : ''"
                    >
                        {{ tab.icon }}
                    </span>
                    <span :class="tab.key === 'wallet' ? 'text-wpx-gold font-semibold' : ''">{{ tab.label }}</span>
                </button>
            </nav>
        </div>
    </div>
</template>
