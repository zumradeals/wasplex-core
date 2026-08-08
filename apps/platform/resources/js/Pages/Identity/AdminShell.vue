<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import AdminAdvertisersPanel from '@/Components/AdminAdvertisersPanel.vue';
import AdminAdvertisingPricingPanel from '@/Components/AdminAdvertisingPricingPanel.vue';
import AdminCampaignReviewsPanel from '@/Components/AdminCampaignReviewsPanel.vue';
import AdminDashboardPanel from '@/Components/AdminDashboardPanel.vue';
import AdminFeedPanel from '@/Components/AdminFeedPanel.vue';
import AdminFeedRiskPanel from '@/Components/AdminFeedRiskPanel.vue';
import AdminMatchingPanel from '@/Components/AdminMatchingPanel.vue';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';
import AdminPermissionsPanel from '@/Components/AdminPermissionsPanel.vue';
import AdminReconciliationPanel from '@/Components/AdminReconciliationPanel.vue';
import AdminSmartProfilePanel from '@/Components/AdminSmartProfilePanel.vue';
import AdminSubscriptionsPanel from '@/Components/AdminSubscriptionsPanel.vue';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

interface LedgerEntryRow {
    id: string;
    direction: string;
    amount_minor: number;
    currency: string;
    description: string | null;
    account: { code: string; owner_type: string; owner_id: string } | null;
}

interface LedgerTransactionRow {
    id: string;
    type: string;
    status: string;
    currency: string;
    source_module: string;
    business_reference: string | null;
    created_by: string | null;
    approved_by: string | null;
    posted_at: string | null;
    created_at: string;
    entries?: LedgerEntryRow[];
}

const page = usePage<{ auth: AuthShared }>();

const nav = [
    { key: 'dashboard', label: "Vue d'ensemble" },
    { key: 'capabilities', label: 'Permissions' },
    { key: 'ledger', label: 'Grand Livre' },
    { key: 'subscriptions', label: 'Abonnements' },
    { key: 'advertisers', label: 'Annonceurs' },
    { key: 'campaign-reviews', label: 'Revue de campagnes' },
    { key: 'smartprofile', label: 'Informations de profil' },
    { key: 'matching', label: 'Ciblage publicitaire' },
    { key: 'feed', label: 'Feed' },
    { key: 'reconciliation', label: 'Rapprochement' },
    { key: 'organizations', label: 'Organisations' },
    { key: 'audit', label: 'Audit' },
] as const;

const activeSection = ref<(typeof nav)[number]['key']>('dashboard');
const feedPanel = ref<InstanceType<typeof AdminFeedPanel> | null>(null);

function onFeedHoldResolved(): void {
    void feedPanel.value?.load();
}

const ledgerTransactions = ref<LedgerTransactionRow[]>([]);
const selectedTransaction = ref<LedgerTransactionRow | null>(null);
const loadingLedger = ref(false);

async function loadLedgerTransactions(): Promise<void> {
    loadingLedger.value = true;
    try {
        const { data } = await http.get('/admin/ledger/transactions');
        ledgerTransactions.value = data.transactions;
    } finally {
        loadingLedger.value = false;
    }
}

async function viewTransaction(id: string): Promise<void> {
    const { data } = await http.get(`/admin/ledger/transactions/${id}`);
    selectedTransaction.value = data.transaction;
}

function selectSection(key: (typeof nav)[number]['key']): void {
    activeSection.value = key;
    if (key === 'ledger') {
        selectedTransaction.value = null;
        void loadLedgerTransactions();
    }
}

onMounted(() => {
    if (activeSection.value === 'ledger') {
        void loadLedgerTransactions();
    }
});

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
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

                <section v-else-if="activeSection === 'ledger'" class="flex flex-col gap-4 lg:flex-row">
                    <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex-1 overflow-x-auto p-4">
                        <h2 class="text-wpx-text mb-3 text-sm font-semibold">Transactions</h2>
                        <p v-if="loadingLedger" class="text-wpx-text-muted text-sm">Chargement…</p>
                        <table v-else class="w-full text-sm">
                            <thead>
                                <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                                    <th class="p-2">Type</th>
                                    <th class="p-2">Statut</th>
                                    <th class="p-2">Montant</th>
                                    <th class="p-2">Posted</th>
                                    <th class="p-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="t in ledgerTransactions"
                                    :key="t.id"
                                    class="border-wpx-border text-wpx-text border-b"
                                >
                                    <td class="p-2">{{ t.type }}</td>
                                    <td class="p-2">
                                        <span
                                            class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                            :class="
                                                t.status === 'posted'
                                                    ? 'bg-wpx-success/10 text-wpx-success-light'
                                                    : t.status === 'rejected'
                                                      ? 'bg-wpx-danger/10 text-wpx-danger-light'
                                                      : 'bg-wpx-pending/10 text-wpx-warning-light'
                                            "
                                        >
                                            {{ t.status }}
                                        </span>
                                    </td>
                                    <td class="p-2 font-mono text-xs">{{ t.currency }}</td>
                                    <td class="text-wpx-text-muted p-2 text-xs">{{ t.posted_at ?? '—' }}</td>
                                    <td class="p-2 text-right">
                                        <button class="text-xs hover:underline" @click="viewTransaction(t.id)">
                                            Détail
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="selectedTransaction"
                        class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface w-full p-4 lg:w-96"
                    >
                        <h2 class="text-wpx-text mb-1 text-sm font-semibold">Écritures</h2>
                        <p class="text-wpx-text-muted mb-3 font-mono text-xs break-all">{{ selectedTransaction.id }}</p>
                        <ul class="flex flex-col gap-2 text-sm">
                            <li
                                v-for="entry in selectedTransaction.entries"
                                :key="entry.id"
                                class="border-wpx-border flex justify-between border-b pb-1"
                            >
                                <span class="text-wpx-text-muted text-xs">{{ entry.account?.code }}</span>
                                <span
                                    :class="
                                        entry.direction === 'debit' ? 'text-wpx-info-light' : 'text-wpx-success-light'
                                    "
                                >
                                    {{ entry.direction === 'debit' ? '−' : '+' }}{{ entry.amount_minor }}
                                    {{ entry.currency }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <section v-else-if="activeSection === 'subscriptions'">
                    <AdminSubscriptionsPanel />
                </section>

                <section v-else-if="activeSection === 'advertisers'" class="flex flex-col gap-4">
                    <AdminAdvertisersPanel />
                    <AdminAdvertisingPricingPanel />
                </section>

                <section v-else-if="activeSection === 'campaign-reviews'">
                    <AdminCampaignReviewsPanel />
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

                <section v-else-if="activeSection === 'reconciliation'">
                    <AdminReconciliationPanel />
                </section>

                <section v-else-if="activeSection === 'dashboard'">
                    <AdminDashboardPanel @navigate="selectSection" />
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
