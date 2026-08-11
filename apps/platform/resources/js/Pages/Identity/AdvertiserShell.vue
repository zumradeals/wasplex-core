<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import AdvertiserDashboardPanel from '@/Components/AdvertiserDashboardPanel.vue';
import AdvertiserStudioPanel from '@/Components/AdvertiserStudioPanel.vue';
import AdvertiserWalletPanel from '@/Components/AdvertiserWalletPanel.vue';
import CampaignsPanel from '@/Components/CampaignsPanel.vue';
import TeamPanel from '@/Components/TeamPanel.vue';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const nav = [
    { key: 'dashboard', label: 'Accueil', shortLabel: 'Accueil' },
    { key: 'campaigns', label: 'Mes publicités', shortLabel: 'Publicités' },
    { key: 'wallet', label: 'Mon solde', shortLabel: 'Solde' },
    { key: 'brands', label: 'Mon activité', shortLabel: 'Activité' },
    { key: 'team', label: 'Équipe & accès', shortLabel: 'Équipe' },
] as const;

type SectionKey = (typeof nav)[number]['key'];

const activeSection = ref<SectionKey>('dashboard');

const advertiserSpace = computed(() => page.props.auth.spaces.find((s) => s.space_type === 'advertiser') ?? null);
const organizationId = computed(() => advertiserSpace.value?.organization_id ?? null);
const organizationName = computed(() => advertiserSpace.value?.organization_name?.trim() || 'Mon activité');

function selectSection(key: SectionKey): void {
    activeSection.value = key;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/');
}
</script>

<template>
    <div class="bg-wpx-navy-950 min-h-screen">
        <div class="mx-auto flex min-h-screen w-full max-w-[1500px]">
            <aside class="border-wpx-border-dark bg-wpx-navy-950 hidden w-64 shrink-0 flex-col border-r lg:flex">
                <div class="border-wpx-border-dark border-b px-5 py-5">
                    <div class="flex items-center gap-3">
                        <span
                            class="from-wpx-orange to-wpx-gold flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br"
                        >
                            <img
                                src="/brand/wasplex-logo-transparent.png"
                                alt="Wasplex"
                                class="h-7 w-7 object-contain"
                            />
                        </span>
                        <div class="min-w-0">
                            <p class="text-wpx-white-soft text-sm font-bold">Studio Wasplex</p>
                            <p class="text-wpx-muted-dark mt-0.5 truncate text-[11px]">{{ organizationName }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-4 pt-5 pb-3">
                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 shadow-wpx-card-dark flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold"
                        @click="selectSection('campaigns')"
                    >
                        <span class="text-lg leading-none">+</span>
                        Créer une publicité
                    </button>
                </div>

                <nav class="flex flex-1 flex-col gap-1 px-3 py-2">
                    <button
                        v-for="item in nav"
                        :key="item.key"
                        type="button"
                        class="flex items-center gap-3 rounded-xl px-3 py-3 text-left text-sm font-semibold transition-colors"
                        :class="
                            activeSection === item.key
                                ? 'bg-wpx-navy-750 text-wpx-white-soft'
                                : 'text-wpx-muted-dark hover:bg-wpx-navy-850 hover:text-wpx-white-soft'
                        "
                        @click="selectSection(item.key)"
                    >
                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                            :class="activeSection === item.key ? 'bg-wpx-cyan/14' : 'bg-wpx-navy-850'"
                        >
                            <svg v-if="item.key === 'dashboard'" width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <rect
                                    x="3"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                                <rect
                                    x="14"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                            </svg>
                            <svg
                                v-else-if="item.key === 'campaigns'"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M3 10l14-6-4 16-3-6-6-4z"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linejoin="round"
                                />
                            </svg>
                            <svg
                                v-else-if="item.key === 'wallet'"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <rect
                                    x="3"
                                    y="6"
                                    width="18"
                                    height="13"
                                    rx="3"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                                <path d="M3 10h18" stroke="currentColor" stroke-width="1.7" />
                            </svg>
                            <svg
                                v-else-if="item.key === 'brands'"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M4 7.5A2.5 2.5 0 016.5 5h11A2.5 2.5 0 0120 7.5V19H4V7.5z"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                />
                                <path
                                    d="M9 5V3.5h6V5M3 10h18"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                            </svg>
                            <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none">
                                <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.7" />
                                <path
                                    d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                                <circle cx="18" cy="9" r="2.3" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                        </span>
                        {{ item.label }}
                    </button>
                </nav>

                <div class="border-wpx-border-dark border-t p-4">
                    <SpaceSwitcher
                        variant="dark"
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button
                        type="button"
                        class="text-wpx-danger mt-3 w-full text-left text-xs font-bold"
                        @click="logout"
                    >
                        Déconnexion
                    </button>
                </div>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="border-wpx-border-dark bg-wpx-navy-850 sticky top-0 z-30 border-b px-4 py-3 lg:px-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <img
                                src="/brand/wasplex-logo-transparent.png"
                                alt="Wasplex"
                                class="h-7 w-7 object-contain lg:hidden"
                            />
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-sm font-bold lg:text-base">
                                    {{
                                        activeSection === 'dashboard'
                                            ? 'Studio annonceur'
                                            : nav.find((n) => n.key === activeSection)?.label
                                    }}
                                </p>
                                <p class="text-wpx-muted-dark hidden truncate text-[11px] sm:block">
                                    {{ organizationName }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <SpaceSwitcher
                                variant="dark"
                                :spaces="page.props.auth.spaces"
                                :active-space-id="page.props.auth.active_space_id"
                            />
                            <button
                                type="button"
                                class="text-wpx-danger hidden text-xs font-bold sm:inline"
                                @click="logout"
                            >
                                Sortir
                            </button>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-3 pt-4 pb-24 sm:px-5 lg:px-6 lg:pt-6 lg:pb-8">
                    <AdvertiserDashboardPanel
                        v-if="activeSection === 'dashboard'"
                        :organization-name="organizationName"
                        @navigate="selectSection"
                    />
                    <CampaignsPanel
                        v-else-if="activeSection === 'campaigns'"
                        @navigate-wallet="selectSection('wallet')"
                    />
                    <AdvertiserWalletPanel v-else-if="activeSection === 'wallet'" />
                    <AdvertiserStudioPanel v-else-if="activeSection === 'brands'" />
                    <TeamPanel v-else-if="activeSection === 'team'" :organization-id="organizationId" />
                </main>

                <nav
                    class="border-wpx-border-dark bg-wpx-navy-850 fixed inset-x-0 bottom-0 z-40 mx-auto grid w-full grid-cols-5 border-t px-1 pt-1 pb-2 lg:hidden"
                >
                    <button
                        v-for="item in nav"
                        :key="item.key"
                        type="button"
                        class="flex min-w-0 flex-col items-center gap-1 py-1 text-[10px] font-semibold"
                        :class="activeSection === item.key ? 'text-wpx-gold' : 'text-wpx-muted-dark'"
                        @click="selectSection(item.key)"
                    >
                        <span
                            v-if="item.key === 'campaigns'"
                            class="from-wpx-orange to-wpx-gold shadow-wpx-card-dark -mt-5 flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br"
                            :class="activeSection === 'campaigns' ? 'ring-wpx-gold ring-2' : ''"
                        >
                            <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
                                <path d="M12 5v14M5 12h14" stroke="#07182D" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </span>
                        <span v-else class="flex h-8 w-8 items-center justify-center">
                            <svg v-if="item.key === 'dashboard'" width="21" height="21" viewBox="0 0 24 24" fill="none">
                                <rect
                                    x="3"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                                <rect
                                    x="14"
                                    y="3"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                                <rect
                                    x="3"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                                <rect
                                    x="14"
                                    y="14"
                                    width="7"
                                    height="7"
                                    rx="2"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                            </svg>
                            <svg
                                v-else-if="item.key === 'wallet'"
                                width="21"
                                height="21"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <rect
                                    x="3"
                                    y="6"
                                    width="18"
                                    height="13"
                                    rx="3"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                                <path d="M3 10h18" stroke="currentColor" stroke-width="1.6" />
                            </svg>
                            <svg
                                v-else-if="item.key === 'brands'"
                                width="21"
                                height="21"
                                viewBox="0 0 24 24"
                                fill="none"
                            >
                                <path
                                    d="M4 7.5A2.5 2.5 0 016.5 5h11A2.5 2.5 0 0120 7.5V19H4V7.5z"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                />
                                <path
                                    d="M9 5V3.5h6V5M3 10h18"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                />
                            </svg>
                            <svg v-else width="21" height="21" viewBox="0 0 24 24" fill="none">
                                <circle cx="9" cy="8" r="3" stroke="currentColor" stroke-width="1.6" />
                                <path
                                    d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                                    stroke="currentColor"
                                    stroke-width="1.6"
                                    stroke-linecap="round"
                                />
                                <circle cx="18" cy="9" r="2.3" stroke="currentColor" stroke-width="1.5" />
                            </svg>
                        </span>
                        <span class="max-w-full truncate">{{ item.shortLabel }}</span>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</template>
