<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import AdvertiserWalletPanel from '@/Components/AdvertiserWalletPanel.vue';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

interface Member {
    account_id: string;
    display_name: string | null;
    title: string | null;
    status: string;
    joined_at: string;
}

const page = usePage<{ auth: AuthShared }>();

const nav = [
    { key: 'dashboard', label: 'Tableau de bord', icon: '📊' },
    { key: 'brands', label: 'Marques', icon: '🏷️' },
    { key: 'campaigns', label: 'Campagnes', icon: '📣' },
    { key: 'wallet', label: 'Wallet annonceur', icon: '💳' },
    { key: 'team', label: 'Équipe', icon: '👥' },
] as const;

const activeSection = ref<(typeof nav)[number]['key']>('dashboard');
const members = ref<Member[]>([]);
const loadingMembers = ref(false);

const organizationId = page.props.auth.spaces.find((s) => s.space_type === 'advertiser')?.organization_id ?? null;

async function loadMembers(): Promise<void> {
    if (!organizationId || members.value.length > 0) {
        return;
    }

    loadingMembers.value = true;
    try {
        const { data } = await http.get(`/organizations/${organizationId}/members`);
        members.value = data.members;
    } finally {
        loadingMembers.value = false;
    }
}

onMounted(() => {
    if (activeSection.value === 'team') {
        void loadMembers();
    }
});

function selectSection(key: (typeof nav)[number]['key']): void {
    activeSection.value = key;
    if (key === 'team') {
        void loadMembers();
    }
}

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
}
</script>

<template>
    <div class="bg-wpx-canvas flex min-h-screen">
        <aside class="bg-wpx-navy-950 border-wpx-border-dark hidden w-56 flex-col border-r md:flex">
            <div class="border-wpx-border-dark border-b p-4">
                <span class="rounded-wpx-md bg-wpx-navy-750 text-wpx-gold px-3 py-1 text-sm font-semibold">
                    Studio Annonceur
                </span>
            </div>
            <nav class="flex flex-1 flex-col gap-1 p-2">
                <button
                    v-for="item in nav"
                    :key="item.key"
                    class="rounded-wpx-sm px-3 py-2 text-left text-sm"
                    :class="
                        activeSection === item.key
                            ? 'bg-wpx-navy-850 text-wpx-gold font-semibold'
                            : 'text-wpx-muted-dark'
                    "
                    @click="selectSection(item.key)"
                >
                    {{ item.icon }} {{ item.label }}
                </button>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="bg-wpx-surface border-wpx-border flex items-center justify-between border-b px-4 py-3">
                <select
                    v-model="activeSection"
                    class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1 text-sm md:hidden"
                >
                    <option v-for="item in nav" :key="item.key" :value="item.key">{{ item.label }}</option>
                </select>
                <span class="text-wpx-text hidden text-sm font-medium md:inline">
                    {{ nav.find((n) => n.key === activeSection)?.label }}
                </span>
                <div class="flex items-center gap-2">
                    <SpaceSwitcher
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button class="text-wpx-text-muted text-xs hover:underline" @click="logout">Déconnexion</button>
                </div>
            </header>

            <main class="flex-1 p-6">
                <section v-if="activeSection === 'wallet'">
                    <AdvertiserWalletPanel />
                </section>
                <section v-else-if="activeSection === 'team'" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
                    <h2 class="text-wpx-text mb-3 text-sm font-semibold">Membres de l'organisation</h2>
                    <p v-if="loadingMembers" class="text-wpx-text-muted text-sm">Chargement…</p>
                    <ul v-else class="flex flex-col gap-2 text-sm">
                        <li
                            v-for="m in members"
                            :key="m.account_id"
                            class="border-wpx-border flex justify-between border-b py-1"
                        >
                            <span>{{ m.display_name ?? m.account_id }}</span>
                            <span class="text-wpx-text-muted">{{ m.title ?? 'membre' }}</span>
                        </li>
                    </ul>
                </section>
                <section
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface text-wpx-text-muted flex h-64 items-center justify-center text-sm"
                >
                    {{ nav.find((n) => n.key === activeSection)?.label }} — bientôt disponible (P005+)
                </section>
            </main>
        </div>
    </div>
</template>
