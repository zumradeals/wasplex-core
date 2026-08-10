<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import AdminCampaignsPanel from '@/Components/AdminCampaignsPanel.vue';
import AdminDashboardPanel from '@/Components/AdminDashboardPanel.vue';
import AdminFeedPanel from '@/Components/AdminFeedPanel.vue';
import AdminFeedRiskPanel from '@/Components/AdminFeedRiskPanel.vue';
import AdminMatchingPanel from '@/Components/AdminMatchingPanel.vue';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';
import AdminPermissionsPanel from '@/Components/AdminPermissionsPanel.vue';
import AdminSmartProfilePanel from '@/Components/AdminSmartProfilePanel.vue';
import AdminSubscriptionsPanel from '@/Components/AdminSubscriptionsPanel.vue';
import AdminUsersPanel from '@/Components/AdminUsersPanel.vue';
import AdminWalletLedgerPanel from '@/Components/AdminWalletLedgerPanel.vue';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const nav = [
    { key: 'dashboard', label: "Vue d'ensemble" },
    { key: 'users', label: 'Utilisateurs' },
    { key: 'capabilities', label: 'Permissions' },
    { key: 'ledger', label: 'Wallet & Grand livre' },
    { key: 'subscriptions', label: 'Abonnements' },
    { key: 'advertisers', label: 'Annonceurs & campagnes' },
    { key: 'smartprofile', label: 'Informations de profil' },
    { key: 'matching', label: 'Ciblage publicitaire' },
    { key: 'feed', label: 'Feed' },
    { key: 'organizations', label: 'Organisations' },
    { key: 'audit', label: 'Audit' },
] as const;

const activeSection = ref<(typeof nav)[number]['key']>('dashboard');
const feedPanel = ref<InstanceType<typeof AdminFeedPanel> | null>(null);

function onFeedHoldResolved(): void {
    void feedPanel.value?.load();
}

function selectSection(key: (typeof nav)[number]['key']): void {
    activeSection.value = key;
}

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/');
}
</script>

<template>
    <div class="bg-wpx-canvas flex min-h-screen">
        <aside class="bg-wpx-navy-950 flex w-56 flex-col py-5">
            <div class="flex items-center gap-2.5 px-[18px] pt-0 pb-5.5">
                <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-6.5 w-6.5 object-contain" />
                <span class="text-wpx-white-soft text-sm font-bold">Administration</span>
            </div>
            <nav class="flex flex-1 flex-col gap-px">
                <button
                    v-for="item in nav"
                    :key="item.key"
                    class="flex items-center gap-2.5 px-[18px] py-2.5 text-left text-[13px] font-semibold"
                    :class="
                        activeSection === item.key
                            ? 'bg-wpx-navy-850 border-wpx-cyan text-wpx-white-soft border-l-[3px]'
                            : 'text-wpx-muted-dark border-l-[3px] border-transparent'
                    "
                    @click="selectSection(item.key)"
                >
                    <span class="flex h-4 w-4 shrink-0 items-center justify-center">
                        <AdminNavIcon :section="item.key" :active="activeSection === item.key" />
                    </span>
                    {{ item.label }}
                </button>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="bg-wpx-surface border-wpx-border flex items-center justify-between border-b px-7 py-4">
                <span class="text-wpx-text text-[17px] font-bold">
                    {{ nav.find((n) => n.key === activeSection)?.label }}
                </span>
                <div class="flex items-center gap-4">
                    <SpaceSwitcher
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button class="text-wpx-danger-light text-xs font-semibold" @click="logout">Déconnexion</button>
                </div>
            </header>

            <main class="flex-1 p-6">
                <section v-if="activeSection === 'capabilities'">
                    <AdminPermissionsPanel />
                </section>

                <section v-else-if="activeSection === 'ledger'">
                    <AdminWalletLedgerPanel />
                </section>

                <section v-else-if="activeSection === 'subscriptions'">
                    <AdminSubscriptionsPanel />
                </section>

                <section v-else-if="activeSection === 'advertisers'">
                    <AdminCampaignsPanel />
                </section>

                <section v-else-if="activeSection === 'smartprofile'">
                    <AdminSmartProfilePanel />
                </section>

                <section v-else-if="activeSection === 'matching'">
                    <AdminMatchingPanel />
                </section>

                <section v-else-if="activeSection === 'feed'" class="flex flex-col gap-4">
                    <AdminFeedPanel ref="feedPanel" />
                    <AdminFeedRiskPanel @resolved="onFeedHoldResolved" />
                </section>

                <section v-else-if="activeSection === 'dashboard'">
                    <AdminDashboardPanel @navigate="selectSection" />
                </section>

                <section v-else-if="activeSection === 'users'">
                    <AdminUsersPanel />
                </section>

                <section
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface text-wpx-text-muted flex h-64 items-center justify-center text-sm"
                >
                    {{ nav.find((n) => n.key === activeSection)?.label }} — bientôt disponible
                </section>
            </main>
        </div>
    </div>
</template>
