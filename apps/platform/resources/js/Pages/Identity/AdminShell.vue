<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import AdminAdvertisingOverviewPanel from '@/Components/AdminAdvertisingOverviewPanel.vue';
import AdminAlertsPanel from '@/Components/AdminAlertsPanel.vue';
import AdminAuditSecurityPanel from '@/Components/AdminAuditSecurityPanel.vue';
import AdminCampaignsPanel from '@/Components/AdminCampaignsPanel.vue';
import AdminDashboardPanel from '@/Components/AdminDashboardPanel.vue';
import AdminFeedPanel from '@/Components/AdminFeedPanel.vue';
import AdminFeedRiskPanel from '@/Components/AdminFeedRiskPanel.vue';
import AdminFinanceOverviewPanel from '@/Components/AdminFinanceOverviewPanel.vue';
import AdminFundsPanel from '@/Components/AdminFundsPanel.vue';
import AdminIdentityVerificationsPanel from '@/Components/AdminIdentityVerificationsPanel.vue';
import AdminLiveRewardsPanel from '@/Components/AdminLiveRewardsPanel.vue';
import AdminProfessionalSpacesPanel from '@/Components/AdminProfessionalSpacesPanel.vue';
import AdminMatchingPanel from '@/Components/AdminMatchingPanel.vue';
import AdminNavIcon from '@/Components/AdminNavIcon.vue';
import AdminPaymentsSettingsPanel from '@/Components/AdminPaymentsSettingsPanel.vue';
import AdminPermissionsPanel from '@/Components/AdminPermissionsPanel.vue';
import AdminSmartProfilePanel from '@/Components/AdminSmartProfilePanel.vue';
import AdminSubscriptionsPanel from '@/Components/AdminSubscriptionsPanel.vue';
import AdminUsersPanel from '@/Components/AdminUsersPanel.vue';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();
const nav = [
    { key: 'dashboard', label: 'Accueil', helper: 'Priorités et santé' },
    { key: 'users', label: 'Utilisateurs', helper: 'Comptes et identités' },
    { key: 'alerts', label: 'Alertes', helper: 'Protection et diffusion utile' },
    { key: 'funds', label: 'Fonds', helper: 'Programmes solidaires et vœux' },
    { key: 'advertising', label: 'Publicité', helper: 'Campagnes et diffusion' },
    { key: 'finance', label: 'Finance', helper: 'Situation et Grand Livre' },
    { key: 'offers', label: 'Offres', helper: 'Abonnements et gains' },
    { key: 'settings', label: 'Réglages', helper: 'Équipe et intégrations' },
] as const;

type SectionKey = (typeof nav)[number]['key'];
type UsersTab = 'accounts' | 'kyc' | 'professionals';
type AdvertisingTab = 'overview' | 'advertisers' | 'feed' | 'matching' | 'smartprofile';
type SettingsTab = 'capabilities' | 'payments' | 'audit';

const usersTabs: Array<{ key: UsersTab; label: string }> = [
    { key: 'accounts', label: 'Comptes' },
    { key: 'kyc', label: 'Identité & KYC' },
    { key: 'professionals', label: 'Professionnels & institutions' },
];
const advertisingTabs: Array<{ key: AdvertisingTab; label: string }> = [
    { key: 'overview', label: 'Vue d’ensemble' },
    { key: 'advertisers', label: 'Campagnes & annonceurs' },
    { key: 'feed', label: 'Antifraude' },
    { key: 'matching', label: 'Diffusion & ciblage' },
    { key: 'smartprofile', label: 'Profil intelligent' },
];
const settingsTabs: Array<{ key: SettingsTab; label: string }> = [
    { key: 'capabilities', label: 'Équipe & permissions' },
    { key: 'payments', label: 'Paiements & intégrations' },
    { key: 'audit', label: 'Audit & sécurité' },
];

const activeSection = ref<SectionKey>('dashboard');
const activeUsersTab = ref<UsersTab>('accounts');
const activeAdvertisingTab = ref<AdvertisingTab>('overview');
const activeSettingsTab = ref<SettingsTab>('capabilities');
const feedPanel = ref<InstanceType<typeof AdminFeedPanel> | null>(null);
const activeNavItem = computed(() => nav.find((item) => item.key === activeSection.value) ?? nav[0]);

function onFeedHoldResolved(): void { void feedPanel.value?.load(); }
function selectSection(key: SectionKey): void { activeSection.value = key; }
function navigateFromDashboard(section: 'users' | 'advertising' | 'finance' | 'offers'): void { activeSection.value = section; }
async function logout(): Promise<void> { await http.post('/logout'); router.visit('/'); }
</script>

<template>
    <div class="bg-wpx-navy-950 text-wpx-white-soft min-h-screen md:flex">
        <aside class="border-wpx-border-dark bg-wpx-navy-950 hidden w-64 shrink-0 flex-col border-r md:flex">
            <div class="border-wpx-border-dark flex items-center gap-3 border-b px-5 py-5">
                <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-8 w-8 object-contain" />
                <div><p class="text-sm font-extrabold">Administration</p><p class="text-wpx-muted-dark text-[11px]">Console fondateur</p></div>
            </div>
            <nav class="flex flex-1 flex-col gap-1.5 p-3">
                <button v-for="item in nav" :key="item.key" type="button" class="rounded-wpx-md flex items-center gap-3 px-3.5 py-3 text-left transition" :class="activeSection === item.key ? 'border-wpx-border-dark bg-wpx-navy-750 shadow-wpx-card-dark border' : 'text-wpx-muted-dark hover:bg-wpx-navy-850 hover:text-wpx-white-soft'" @click="selectSection(item.key)">
                    <span class="rounded-wpx-sm flex h-9 w-9 shrink-0 items-center justify-center" :class="activeSection === item.key ? 'bg-wpx-cyan/12' : 'bg-wpx-navy-850'"><AdminNavIcon :section="item.key" :active="activeSection === item.key" /></span>
                    <span class="min-w-0"><span class="block text-[13px] font-bold">{{ item.label }}</span><span class="text-wpx-muted-dark block truncate text-[10px]">{{ item.helper }}</span></span>
                </button>
            </nav>
            <div class="border-wpx-border-dark border-t p-4"><p class="text-wpx-muted-dark text-[10px] tracking-[0.16em] uppercase">Wasplex Core</p><p class="mt-1 text-xs font-semibold">Piloter sans jargon technique</p></div>
        </aside>

        <div class="bg-wpx-navy-950 min-w-0 flex-1">
            <header class="border-wpx-border-dark bg-wpx-navy-950/95 sticky top-0 z-30 border-b px-4 py-3 backdrop-blur md:px-7">
                <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0"><p class="truncate text-lg font-extrabold">{{ activeNavItem.label }}</p><p class="text-wpx-muted-dark hidden text-xs sm:block">{{ activeNavItem.helper }}</p></div>
                    <div class="flex items-center gap-3"><div class="hidden sm:block"><SpaceSwitcher :spaces="page.props.auth.spaces" :active-space-id="page.props.auth.active_space_id" /></div><button class="text-wpx-danger shrink-0 text-xs font-bold" @click="logout">Sortir</button></div>
                </div>
                <nav class="mt-3 flex gap-2 overflow-x-auto pb-1 md:hidden">
                    <button v-for="item in nav" :key="item.key" type="button" class="rounded-wpx-full shrink-0 border px-3 py-2 text-xs font-bold" :class="activeSection === item.key ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan' : 'border-wpx-border-dark text-wpx-muted-dark'" @click="selectSection(item.key)">{{ item.label }}</button>
                </nav>
            </header>

            <main class="mx-auto w-full max-w-[1500px] p-4 pb-10 md:p-6 lg:p-8">
                <section v-if="activeSection === 'dashboard'"><AdminDashboardPanel @navigate="navigateFromDashboard" /></section>
                <section v-else-if="activeSection === 'users'" class="flex flex-col gap-5">
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 flex gap-2 overflow-x-auto border p-2">
                        <button v-for="tab in usersTabs" :key="tab.key" type="button" class="rounded-wpx-md shrink-0 px-4 py-2.5 text-xs font-bold" :class="activeUsersTab === tab.key ? 'bg-wpx-white-soft text-wpx-navy-950' : 'text-wpx-muted-dark hover:text-wpx-white-soft'" @click="activeUsersTab = tab.key">{{ tab.label }}</button>
                    </div>
                    <AdminUsersPanel v-if="activeUsersTab === 'accounts'" /><AdminIdentityVerificationsPanel v-else-if="activeUsersTab === 'kyc'" /><AdminProfessionalSpacesPanel v-else />
                </section>
                <section v-else-if="activeSection === 'alerts'"><AdminAlertsPanel /></section>
                <section v-else-if="activeSection === 'funds'"><AdminFundsPanel /></section>
                <section v-else-if="activeSection === 'advertising'" class="flex flex-col gap-5">
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 flex gap-2 overflow-x-auto border p-2">
                        <button v-for="tab in advertisingTabs" :key="tab.key" type="button" class="rounded-wpx-md shrink-0 px-4 py-2.5 text-xs font-bold" :class="activeAdvertisingTab === tab.key ? 'bg-wpx-white-soft text-wpx-navy-950' : 'text-wpx-muted-dark hover:text-wpx-white-soft'" @click="activeAdvertisingTab = tab.key">{{ tab.label }}</button>
                    </div>
                    <AdminAdvertisingOverviewPanel v-if="activeAdvertisingTab === 'overview'" @open-tab="activeAdvertisingTab = $event" />
                    <div v-else-if="activeAdvertisingTab === 'advertisers'" class="[&_.bg-white]:bg-wpx-navy-850 [&_.bg-wpx-surface]:bg-wpx-navy-850 [&_.bg-wpx-canvas]:bg-wpx-navy-950 [&_.text-wpx-text]:text-wpx-white-soft [&_.text-wpx-text-muted]:text-wpx-muted-dark [&_.border-wpx-border]:border-wpx-border-dark [&_.text-wpx-blue-light]:text-wpx-cyan [&_.text-wpx-success-light]:text-wpx-success [&_.text-wpx-warning-light]:text-wpx-gold [&_.text-wpx-danger-light]:text-wpx-danger [&_input]:bg-wpx-navy-950 [&_input]:text-wpx-white-soft [&_select]:bg-wpx-navy-950 [&_select]:text-wpx-white-soft"><AdminCampaignsPanel /></div>
                    <div v-else-if="activeAdvertisingTab === 'feed'" class="flex flex-col gap-4"><AdminFeedPanel ref="feedPanel" /><AdminFeedRiskPanel @resolved="onFeedHoldResolved" /><AdminLiveRewardsPanel /></div>
                    <AdminMatchingPanel v-else-if="activeAdvertisingTab === 'matching'" /><AdminSmartProfilePanel v-else />
                </section>
                <section v-else-if="activeSection === 'finance'"><AdminFinanceOverviewPanel /></section>
                <section v-else-if="activeSection === 'offers'"><AdminSubscriptionsPanel /></section>
                <section v-else class="flex flex-col gap-5">
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 flex gap-2 overflow-x-auto border p-2">
                        <button v-for="tab in settingsTabs" :key="tab.key" type="button" class="rounded-wpx-md shrink-0 px-4 py-2.5 text-xs font-bold" :class="activeSettingsTab === tab.key ? 'bg-wpx-white-soft text-wpx-navy-950' : 'text-wpx-muted-dark hover:text-wpx-white-soft'" @click="activeSettingsTab = tab.key">{{ tab.label }}</button>
                    </div>
                    <AdminPermissionsPanel v-if="activeSettingsTab === 'capabilities'" /><AdminPaymentsSettingsPanel v-else-if="activeSettingsTab === 'payments'" /><AdminAuditSecurityPanel v-else />
                </section>
            </main>
        </div>
    </div>
</template>
