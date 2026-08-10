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
    { key: 'alertes', label: 'Alertes' },
    { key: 'espace', label: 'Mon Espace' },
] as const;

const activeTab = ref<(typeof tabs)[number]['key']>('feed');
const walletPanel = ref<InstanceType<typeof WalletPanel> | null>(null);
const smartProfilePanel = ref<InstanceType<typeof SmartProfilePanel> | null>(null);
const me = ref<MeResponse | null>(null);

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

    if (name) {
        return name.slice(0, 2).toUpperCase();
    }

    return primaryPhone.value ? primaryPhone.value.slice(-2) : 'WP';
});

const smartProfilePercent = computed(() => smartProfilePanel.value?.percent ?? 0);
const smartProfileSuggestions = computed(() => smartProfilePanel.value?.nextSuggestions ?? []);
const profileExpanded = ref(false);

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

function scrollToSubscription(): void {
    document.getElementById('mon-abonnement')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function goToSubscriptionFromWallet(): Promise<void> {
    activeTab.value = 'espace';
    await nextTick();
    scrollToSubscription();
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

            <main class="flex-1" :class="activeTab === 'feed' ? '' : 'px-4 py-6'">
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

                <div v-else-if="activeTab === 'espace'" class="flex flex-col gap-4">
                    <div class="flex items-center justify-between">
                        <h1 class="text-wpx-white-soft text-lg font-bold">Mon Espace</h1>
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

                    <!-- Carte profil -->
                    <section
                        class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 shadow-wpx-card-dark rounded-wpx-xl border-wpx-border-dark border bg-gradient-to-br p-4"
                    >
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
                                    class="text-wpx-blue mt-1 text-xs font-semibold"
                                    @click="announceComingSoon"
                                >
                                    Vérifier mon identité
                                </button>
                            </div>
                        </div>
                        <div class="border-wpx-border-dark mt-4 flex border-t pt-3.5">
                            <div class="flex-1 text-center">
                                <p class="text-wpx-white-soft text-base font-bold">{{ smartProfilePercent }}%</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-[10px] tracking-wide uppercase">Profil</p>
                            </div>
                            <button
                                type="button"
                                class="border-wpx-border-dark flex-1 border-l text-center"
                                @click="announceComingSoon"
                            >
                                <p class="text-wpx-white-soft text-base font-bold">—</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-[10px] tracking-wide uppercase">Parrainage</p>
                            </button>
                        </div>
                    </section>

                    <!-- Actions rapides -->
                    <section class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3.5 text-left"
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
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">
                                Retrait, dépôt, historique
                            </p>
                        </button>
                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3.5 text-left"
                            @click="scrollToSubscription"
                        >
                            <span class="bg-wpx-gold/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="17" height="17" viewBox="0 0 24 24">
                                    <path d="M12 2l6 6-6 14-6-14z" fill="#F2C14E" />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Mon abonnement</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">Gérer mon abonnement</p>
                        </button>
                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3.5 text-left"
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
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">Avantages exclusifs</p>
                        </button>
                        <button
                            type="button"
                            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3.5 text-left"
                            @click="announceComingSoon"
                        >
                            <span class="bg-wpx-cyan/16 rounded-wpx-sm flex h-9 w-9 items-center justify-center">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"
                                        stroke="#2BC4DE"
                                        stroke-width="1.7"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <p class="text-wpx-white-soft mt-2.5 text-sm font-bold">Identité &amp; KYC</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[11px] leading-tight">Sécurise ton compte</p>
                        </button>
                    </section>

                    <p v-if="espaceNotice" class="text-wpx-muted-dark -mt-1 text-center text-xs">{{ espaceNotice }}</p>

                    <BecomeAdvertiserPanel />
                    <EligibleCampaignsPanel />
                    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-4.5">
                        <div class="flex items-baseline justify-between">
                            <p class="text-wpx-white-soft text-sm font-bold">Profil intelligent</p>
                            <p class="text-wpx-gold text-sm font-bold">{{ smartProfilePercent }}%</p>
                        </div>
                        <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                            Complète ton profil pour des publicités mieux ciblées — facultatif, corrigible à tout
                            moment, jamais partagé avec un annonceur.
                        </p>
                        <div class="bg-wpx-navy-750 mt-3 h-1.5 overflow-hidden rounded-full">
                            <div
                                class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                                :style="{ width: `${smartProfilePercent}%` }"
                            />
                        </div>
                        <div v-if="smartProfileSuggestions.length > 0" class="mt-3.5 flex flex-col gap-2">
                            <button
                                v-for="suggestion in smartProfileSuggestions"
                                :key="suggestion"
                                type="button"
                                class="bg-wpx-navy-750 rounded-wpx-md flex items-center justify-between p-2.5 text-left"
                                @click="profileExpanded = true"
                            >
                                <span class="text-wpx-white-soft text-sm font-semibold">{{ suggestion }}</span>
                                <span class="bg-wpx-gold/15 text-wpx-gold rounded-full px-2.5 py-1 text-xs font-bold">
                                    +
                                </span>
                            </button>
                        </div>
                        <button
                            type="button"
                            class="text-wpx-blue mt-3 text-xs font-bold"
                            @click="profileExpanded = !profileExpanded"
                        >
                            {{ profileExpanded ? 'Réduire' : 'Compléter mon profil' }} ›
                        </button>
                        <SmartProfilePanel v-show="profileExpanded" ref="smartProfilePanel" compact class="mt-3" />
                    </section>
                    <ConsentsPanel />

                    <section
                        class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex flex-col gap-3 border p-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <circle cx="6" cy="12" r="2.5" stroke="#4FA3FF" stroke-width="1.7" />
                                <circle cx="18" cy="6" r="2.5" stroke="#4FA3FF" stroke-width="1.7" />
                                <circle cx="18" cy="18" r="2.5" stroke="#4FA3FF" stroke-width="1.7" />
                                <path d="M8.2 10.8l7.6-4.2M8.2 13.2l7.6 4.2" stroke="#4FA3FF" stroke-width="1.6" />
                            </svg>
                            <span class="text-wpx-white-soft text-sm font-bold">Partager &amp; inviter</span>
                        </div>
                        <button
                            type="button"
                            class="bg-wpx-navy-750 rounded-wpx-md flex items-center justify-between p-3.5 text-left"
                            @click="announceComingSoon"
                        >
                            <span class="text-wpx-muted-dark text-xs">Programme de parrainage</span>
                            <span class="text-wpx-blue text-xs font-bold">Bientôt disponible</span>
                        </button>
                    </section>

                    <section
                        class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg divide-wpx-navy-750 divide-y border"
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between p-3.5 text-left"
                            @click="announceComingSoon"
                        >
                            <span>
                                <span class="text-wpx-white-soft block text-sm font-bold">Compte &amp; sécurité</span>
                                <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">
                                    MFA {{ page.props.auth.account.mfa_enabled ? 'activée' : 'non activée' }}
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
                        <button
                            type="button"
                            class="flex w-full items-center justify-between p-3.5 text-left"
                            @click="announceComingSoon"
                        >
                            <span>
                                <span class="text-wpx-white-soft block text-sm font-bold">Services Wasplex</span>
                                <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">Cartes et avantages</span>
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

                    <div id="mon-abonnement">
                        <SubscriptionPanel />
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 text-wpx-muted-dark flex h-64 flex-col items-center justify-center gap-2 text-sm"
                >
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-full text-2xl"
                        :class="activeTab === 'fonds' ? 'bg-wpx-gold/10' : 'bg-wpx-danger/10'"
                    >
                        {{ activeTab === 'fonds' ? '🎯' : '🔔' }}
                    </span>
                    {{ tabs.find((t) => t.key === activeTab)?.label }} — bientôt disponible
                </div>
            </main>

            <nav class="border-wpx-border-dark bg-wpx-navy-850 grid grid-cols-5 items-end border-t px-1 pt-1 pb-2">
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
                        <svg v-else-if="tab.key === 'alertes'" width="22" height="22" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 4a5 5 0 015 5v3l1.5 3h-13L7 12V9a5 5 0 015-5z"
                                :stroke="activeTab === 'alertes' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M10 18a2 2 0 004 0"
                                :stroke="activeTab === 'alertes' ? '#F2C14E' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
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
    </div>
</template>
