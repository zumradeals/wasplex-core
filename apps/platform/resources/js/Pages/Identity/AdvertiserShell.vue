<script setup lang="ts">
import { ref } from 'vue';
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
    { key: 'dashboard', label: 'Tableau de bord' },
    { key: 'brands', label: 'Marques' },
    { key: 'campaigns', label: 'Campagnes' },
    { key: 'wallet', label: 'Wallet annonceur' },
    { key: 'team', label: 'Équipe' },
] as const;

const activeSection = ref<(typeof nav)[number]['key']>('dashboard');

const organizationId = page.props.auth.spaces.find((s) => s.space_type === 'advertiser')?.organization_id ?? null;

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
        <aside class="bg-wpx-navy-950 border-wpx-border-dark hidden w-56 flex-col border-r md:flex">
            <div class="flex items-center gap-2.5 px-[18px] pt-5 pb-5.5">
                <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-6.5 w-6.5 object-contain" />
                <span class="text-wpx-white-soft text-sm font-bold">Studio Annonceur</span>
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
                        <svg v-if="item.key === 'dashboard'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <rect
                                x="3"
                                y="3"
                                width="8"
                                height="8"
                                rx="2"
                                :stroke="activeSection === 'dashboard' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                            />
                            <rect
                                x="13"
                                y="3"
                                width="8"
                                height="8"
                                rx="2"
                                :stroke="activeSection === 'dashboard' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                            />
                            <rect
                                x="3"
                                y="13"
                                width="8"
                                height="8"
                                rx="2"
                                :stroke="activeSection === 'dashboard' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                            />
                            <rect
                                x="13"
                                y="13"
                                width="8"
                                height="8"
                                rx="2"
                                :stroke="activeSection === 'dashboard' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                            />
                        </svg>
                        <svg v-else-if="item.key === 'brands'" width="16" height="16" viewBox="0 0 24 24">
                            <path d="M12 2l8 8-9 9-8-8z" :fill="activeSection === 'brands' ? '#2BC4DE' : '#A9B7C8'" />
                            <circle cx="9" cy="8" r="1.6" fill="#07182D" />
                        </svg>
                        <svg
                            v-else-if="item.key === 'campaigns'"
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                        >
                            <path
                                d="M3 10l14-6-4 16-3-6-6-4z"
                                :stroke="activeSection === 'campaigns' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <svg v-else-if="item.key === 'wallet'" width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <rect
                                x="3"
                                y="6"
                                width="18"
                                height="13"
                                rx="3"
                                :stroke="activeSection === 'wallet' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.7"
                            />
                            <rect
                                x="3"
                                y="10"
                                width="18"
                                height="2.4"
                                :fill="activeSection === 'wallet' ? '#2BC4DE' : '#A9B7C8'"
                            />
                        </svg>
                        <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
                            <circle
                                cx="9"
                                cy="8"
                                r="3"
                                :stroke="activeSection === 'team' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <path
                                d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                                :stroke="activeSection === 'team' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <circle
                                cx="18"
                                cy="9"
                                r="2.4"
                                :stroke="activeSection === 'team' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                            <path
                                d="M15.5 20c.3-2.6 2.1-4.5 4.5-4.5"
                                :stroke="activeSection === 'team' ? '#2BC4DE' : '#A9B7C8'"
                                stroke-width="1.6"
                            />
                        </svg>
                    </span>
                    {{ item.label }}
                </button>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="bg-wpx-surface border-wpx-border flex items-center justify-between border-b px-7 py-4">
                <select
                    v-model="activeSection"
                    class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1 text-sm md:hidden"
                >
                    <option v-for="item in nav" :key="item.key" :value="item.key">{{ item.label }}</option>
                </select>
                <span class="text-wpx-text hidden text-[17px] font-bold md:inline">
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

            <main class="flex-1 p-7">
                <AdvertiserDashboardPanel v-if="activeSection === 'dashboard'" />
                <AdvertiserWalletPanel v-else-if="activeSection === 'wallet'" />
                <AdvertiserStudioPanel v-else-if="activeSection === 'brands'" />
                <CampaignsPanel v-else-if="activeSection === 'campaigns'" @navigate-wallet="selectSection('wallet')" />
                <TeamPanel v-else-if="activeSection === 'team'" :organization-id="organizationId" />
            </main>
        </div>
    </div>
</template>
