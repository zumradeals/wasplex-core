<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import { useComingSoon } from '@/lib/comingSoon';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import SubscriptionPanel from '@/Components/SubscriptionPanel.vue';
import SmartProfilePanel from '@/Components/SmartProfilePanel.vue';
import ConsentsPanel from '@/Components/ConsentsPanel.vue';
import EligibleCampaignsPanel from '@/Components/EligibleCampaignsPanel.vue';
import BecomeAdvertiserPanel from '@/Components/BecomeAdvertiserPanel.vue';
import FeedPanel from '@/Components/FeedPanel.vue';
import WalletPanel from '@/Components/WalletPanel.vue';
import IdentityVerificationPanel from '@/Components/IdentityVerificationPanel.vue';
import AccountSecurityPanel from '@/Components/AccountSecurityPanel.vue';
import type { AuthShared } from '@/types/identity';

interface MeResponse {
    profile: { first_name: string | null; last_name: string | null; display_name: string | null };
    identifiers: Array<{ type: string; value: string; is_primary: boolean }>;
}

const page = usePage<{ auth: AuthShared }>();

const tabs = [
    { key: 'feed', label: 'Feed' },
    { key: 'fonds', label: 'Fonds' },
    { key: 'wallet', label: 'Wallet' },
    { key: 'espace', label: 'Mon Espace' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('feed');
const walletPanel = ref<InstanceType<typeof WalletPanel> | null>(null);
const smartProfilePanel = ref<InstanceType<typeof SmartProfilePanel> | null>(null);
const me = ref<MeResponse | null>(null);
const showSmartProfile = ref(false);
const showIdentityVerification = ref(false);
const showAccountSecurity = ref(false);

const { notice: espaceNotice, announce: announceComingSoon } = useComingSoon();

const primaryPhone = computed(
    () =>
        me.value?.identifiers.find((i) => i.type === 'phone' && i.is_primary)?.value ??
        me.value?.identifiers.find((i) => i.type === 'phone')?.value ??
        null,
);

const displayName = computed(() => me.value?.profile.display_name?.trim() || primaryPhone.value || 'Compte Wasplex');

const initials = computed(() => {
    const name = me.value?.profile.display_name?.trim();

    if (name) return name.slice(0, 2).toUpperCase();

    return primaryPhone.value ? primaryPhone.value.slice(-2) : 'WP';
});

const smartProfilePercent = computed(() => smartProfilePanel.value?.percent ?? 0);

async function loadMe(): Promise<void> {
    const { data } = await http.get('/me');
    me.value = data;
}

function onBalanceChanged(): void {
    void walletPanel.value?.load();
}

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/');
}

function goToWallet(): void {
    activeTab.value = 'wallet';
}

function goToFeed(): void {
    activeTab.value = 'feed';
}

function scrollToSubscription(): void {
    document.getElementById('mon-abonnement')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

async function goToSubscriptionFromWallet(): Promise<void> {
    activeTab.value = 'espace';
    await nextTick();
    scrollToSubscription();
}

function onMfaEnabled(): void {
    router.reload({ only: ['auth'] });
}

onMounted(loadMe);
</script>

<template>
    <div class="bg-wpx-navy-950 flex min-h-screen justify-center">
        <div class="bg-wpx-navy-950 flex w-full max-w-md flex-col">
            <header
                v-if="activeTab !== 'feed'"
                class="border-wpx-border-dark bg-wpx-navy-850 flex items-center justify-between border-b px-4 py-2.5"
            >
                <div class="flex items-center gap-2">
                    <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-6 w-6 object-contain" />
                    <span class="text-wpx-white-soft text-sm font-semibold">Wasplex</span>
                </div>
                <SpaceSwitcher
                    variant="dark"
                    :spaces="page.props.auth.spaces"
                    :active-space-id="page.props.auth.active_space_id"
                />
            </header>

            <main class="flex-1" :class="activeTab === 'feed' ? '' : 'px-4 py-5'">
                <FeedPanel v-if="activeTab === 'feed'" @balance-changed="onBalanceChanged" />

                <div v-else-if="activeTab === 'wallet'" class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h1 class="text-wpx-white-soft text-lg font-bold">Mon Portefeuille</h1>
                        <button
                            type="button"
                            aria-label="Rafraîchir"
                            class="text-wpx-muted-dark"
                            @click="walletPanel?.load()"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M3 12a9 9 0 0115-6.7M21 12a9 9 0 01-15 6.7"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                                <path
                                    d="M18 3v4h-4M6 21v-4h4"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>
                    </div>
                    <WalletPanel
                        ref="walletPanel"
                        :account-label="me?.profile.display_name ?? undefined"
                        :phone-number="primaryPhone"
                        @go-to-subscription="goToSubscriptionFromWallet"
                    />
                </div>

                <div v-else-if="activeTab === 'espace'" class="flex flex-col gap-3.5">
                    <div class="flex items-center justify-between px-0.5">
                        <div>
                            <h1 class="text-wpx-white-soft text-xl font-bold">Mon Espace</h1>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px]">
                                Votre compte, vos choix, vos avantages.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-wpx-danger flex items-center gap-1 text-xs font-bold"
                            @click="logout"
                        >
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M9 4H6a2 2 0 00-2 2v12a2 2 0 002 2h3"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                                <path
                                    d="M13 8l4 4-4 4M17 12H9"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            Sortir
                        </button>
                    </div>

                    <section
                        class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 shadow-wpx-card-dark rounded-wpx-xl border-wpx-border-dark overflow-hidden border bg-gradient-to-br"
                    >
                        <div class="p-4">
                            <div class="flex items-start gap-3">
                                <span
                                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-wpx-md flex h-14 w-14 shrink-0 items-center justify-center bg-gradient-to-br text-lg font-extrabold"
                                >
                                    {{ initials }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-wpx-white-soft truncate text-base font-bold">{{ displayName }}</p>
                                    <p
                                        v-if="primaryPhone && me?.profile.display_name"
                                        class="text-wpx-muted-dark mt-0.5 font-mono text-xs"
                                    >
                                        {{ primaryPhone }}
                                    </p>
                                    <button
                                        type="button"
                                        class="text-wpx-blue mt-1.5 text-xs font-semibold"
                                        @click="showIdentityVerification = true"
                                    >
                                        Vérifier mon identité ›
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="border-wpx-border-dark grid grid-cols-2 border-t">
                            <button type="button" class="px-3 py-3 text-center" @click="showSmartProfile = true">
                                <p class="text-wpx-white-soft text-base font-bold">{{ smartProfilePercent }}%</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-[10px] tracking-wide uppercase">
                                    Profil intelligent
                                </p>
                            </button>
                            <button
                                type="button"
                                class="border-wpx-border-dark border-l px-3 py-3 text-center"
                                @click="showAccountSecurity = true"
                            >
                                <p
                                    class="text-sm font-bold"
                                    :class="
                                        page.props.auth.account.mfa_enabled ? 'text-wpx-success-light' : 'text-wpx-gold'
                                    "
                                >
                                    {{ page.props.auth.account.mfa_enabled ? 'Protégé' : 'À renforcer' }}
                                </p>
                                <p class="text-wpx-muted-dark mt-0.5 text-[10px] tracking-wide uppercase">Sécurité</p>
                            </button>
                        </div>
                    </section>

                    <section class="grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5 text-left"
                            @click="goToWallet"
                        >
                            <span class="bg-wpx-blue/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <rect
                                        x="3"
                                        y="6"
                                        width="18"
                                        height="13"
                                        rx="3"
                                        stroke="#4FA3FF"
                                        stroke-width="1.7"
                                    />
                                    <rect x="3" y="10" width="18" height="2.4" fill="#4FA3FF" />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Mon wallet</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">Solde, dépôt, retrait</p>
                        </button>

                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5 text-left"
                            @click="showSmartProfile = true"
                        >
                            <span class="bg-wpx-cyan/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="8" r="3" stroke="#2BC4DE" stroke-width="1.7" />
                                    <path
                                        d="M6 20c.8-4 3.2-6 6-6s5.2 2 6 6"
                                        stroke="#2BC4DE"
                                        stroke-width="1.7"
                                        stroke-linecap="round"
                                    />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Profil intelligent</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">
                                {{ smartProfilePercent }}% complété
                            </p>
                        </button>

                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5 text-left"
                            @click="showIdentityVerification = true"
                        >
                            <span class="bg-wpx-cyan/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"
                                        stroke="#2BC4DE"
                                        stroke-width="1.7"
                                    />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Identité &amp; KYC</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">
                                Vérification confidentielle
                            </p>
                        </button>

                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5 text-left"
                            @click="announceComingSoon"
                        >
                            <span class="bg-wpx-orange/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <rect
                                        x="3"
                                        y="5"
                                        width="18"
                                        height="12"
                                        rx="2.5"
                                        stroke="#FF9A3D"
                                        stroke-width="1.7"
                                    />
                                    <circle cx="12" cy="11" r="2.4" stroke="#FF9A3D" stroke-width="1.7" />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Carte Wasplex</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">Avantages et services</p>
                        </button>
                    </section>

                    <p v-if="espaceNotice" class="text-wpx-muted-dark -mt-1 text-center text-xs">{{ espaceNotice }}</p>

                    <div id="mon-abonnement">
                        <SubscriptionPanel />
                    </div>

                    <EligibleCampaignsPanel @open-feed="goToFeed" />
                    <BecomeAdvertiserPanel />
                    <ConsentsPanel />

                    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg border">
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 p-3.5 text-left"
                            @click="showAccountSecurity = true"
                        >
                            <span class="flex items-center gap-3">
                                <span class="bg-wpx-blue/12 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none">
                                        <path
                                            d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"
                                            stroke="#4FA3FF"
                                            stroke-width="1.7"
                                        />
                                        <path
                                            d="M9 12l2 2 4-4"
                                            stroke="#4FA3FF"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <span>
                                    <span class="text-wpx-white-soft block text-sm font-bold"
                                        >Compte &amp; sécurité</span
                                    >
                                    <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">
                                        MFA {{ page.props.auth.account.mfa_enabled ? 'activée' : 'non activée' }} ·
                                        sessions et appareils
                                    </span>
                                </span>
                            </span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M9 6l6 6-6 6"
                                    stroke="#A9B7C8"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                            </svg>
                        </button>
                    </section>
                </div>

                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 text-wpx-muted-dark flex h-64 flex-col items-center justify-center gap-2 text-sm"
                >
                    <span class="bg-wpx-gold/10 flex h-12 w-12 items-center justify-center rounded-full text-2xl">
                        🎯
                    </span>
                    {{ tabs.find((t) => t.key === activeTab)?.label }} — bientôt disponible
                </div>
            </main>

            <nav class="border-wpx-border-dark bg-wpx-navy-850 grid grid-cols-4 items-end border-t px-1 pt-1 pb-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    class="flex flex-col items-center gap-1 py-1 text-[11px] font-semibold"
                    :class="activeTab === tab.key && tab.key !== 'wallet' ? 'text-wpx-gold' : 'text-wpx-muted-dark'"
                    @click="activeTab = tab.key"
                >
                    <span
                        v-if="tab.key === 'wallet'"
                        class="from-wpx-orange to-wpx-gold shadow-wpx-card-dark -mt-5 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br"
                        :class="activeTab === 'wallet' ? 'ring-wpx-gold ring-2' : ''"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="6" width="18" height="13" rx="3" stroke="#07182D" stroke-width="1.8" />
                            <rect x="3" y="10" width="18" height="2.4" fill="#07182D" />
                        </svg>
                    </span>
                    <span v-else class="flex h-9 w-9 items-center justify-center">
                        <svg v-if="tab.key === 'feed'" width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <rect
                                x="3"
                                y="4"
                                width="18"
                                height="16"
                                rx="4"
                                :stroke="activeTab === 'feed' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <path d="M10 9l6 3-6 3V9z" :fill="activeTab === 'feed' ? '#F2C14E' : '#A9B7C8'" />
                        </svg>
                        <svg v-else-if="tab.key === 'fonds'" width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <circle
                                cx="12"
                                cy="12"
                                r="8"
                                :stroke="activeTab === 'fonds' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <circle
                                cx="12"
                                cy="12"
                                r="4.2"
                                :stroke="activeTab === 'fonds' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <circle cx="12" cy="12" r="1" :fill="activeTab === 'fonds' ? '#F2C14E' : '#A9B7C8'" />
                        </svg>
                        <svg v-else width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <circle
                                cx="12"
                                cy="8.5"
                                r="3.4"
                                :stroke="activeTab === 'espace' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <path
                                d="M5 20c1.2-4 4-6 7-6s5.8 2 7 6"
                                :stroke="activeTab === 'espace' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>
                    <span>{{ tab.label }}</span>
                </button>
            </nav>
        </div>

        <Teleport to="body">
            <div
                v-show="showSmartProfile"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 sm:items-center"
                @click.self="showSmartProfile = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-4 shadow-2xl sm:rounded-3xl"
                >
                    <div class="bg-wpx-navy-950 sticky top-0 z-20 flex items-start justify-between gap-3 pb-3">
                        <div>
                            <p class="text-wpx-white-soft text-lg font-bold">Mon profil intelligent</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Mettez à jour uniquement ce que vous souhaitez partager.
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Fermer"
                            class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                            @click="showSmartProfile = false"
                        >
                            ×
                        </button>
                    </div>
                    <SmartProfilePanel ref="smartProfilePanel" />
                </div>
            </div>
        </Teleport>

        <IdentityVerificationPanel
            :open="showIdentityVerification"
            @close="showIdentityVerification = false"
            @submitted="loadMe"
        />
        <AccountSecurityPanel
            :open="showAccountSecurity"
            :mfa-enabled="page.props.auth.account.mfa_enabled"
            @close="showAccountSecurity = false"
            @mfa-enabled="onMfaEnabled"
        />
    </div>
</template>
