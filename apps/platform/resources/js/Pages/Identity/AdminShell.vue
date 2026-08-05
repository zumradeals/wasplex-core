<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

interface Grant {
    id: string;
    account_id: string;
    capability_code: string;
    scope_type: string | null;
    scope_id: string | null;
    status: string;
    expires_at: string | null;
}

const page = usePage<{ auth: AuthShared }>();

const nav = [
    { key: 'dashboard', label: 'Tableau de bord', icon: '🧭' },
    { key: 'capabilities', label: 'Capacités', icon: '🔑' },
    { key: 'organizations', label: 'Organisations', icon: '🏢' },
    { key: 'audit', label: 'Audit', icon: '📜' },
] as const;

const activeSection = ref<(typeof nav)[number]['key']>('dashboard');
const grants = ref<Grant[]>([]);
const newGrant = ref({ account_id: '', capability_code: '' });
const error = ref<string | null>(null);

async function loadGrants(): Promise<void> {
    const { data } = await http.get('/admin/capabilities');
    grants.value = data.grants;
}

async function grantCapability(): Promise<void> {
    error.value = null;
    try {
        await http.post('/admin/capabilities', newGrant.value);
        newGrant.value = { account_id: '', capability_code: '' };
        await loadGrants();
    } catch {
        error.value = "L'attribution a échoué.";
    }
}

async function revoke(grant: Grant): Promise<void> {
    await http.delete(`/admin/capabilities/${grant.id}`);
    await loadGrants();
}

function selectSection(key: (typeof nav)[number]['key']): void {
    activeSection.value = key;
    if (key === 'capabilities') {
        void loadGrants();
    }
}

onMounted(() => {
    if (activeSection.value === 'capabilities') {
        void loadGrants();
    }
});

async function logout(): Promise<void> {
    await http.post('/logout');
    router.visit('/login');
}
</script>

<template>
    <div class="bg-wasplex-surface flex min-h-screen">
        <aside class="flex w-60 flex-col border-r border-black/5 bg-white">
            <div class="border-b border-black/5 p-4">
                <span class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-3 py-1 text-sm font-semibold">
                    Administration
                </span>
            </div>
            <nav class="flex flex-1 flex-col gap-1 p-2">
                <button
                    v-for="item in nav"
                    :key="item.key"
                    class="rounded-wasplex-sm px-3 py-2 text-left text-sm"
                    :class="
                        activeSection === item.key
                            ? 'bg-wasplex-gold/10 text-wasplex-black font-semibold'
                            : 'text-wasplex-black/60'
                    "
                    @click="selectSection(item.key)"
                >
                    {{ item.icon }} {{ item.label }}
                </button>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-black/5 bg-white px-6 py-3">
                <span class="text-wasplex-black text-sm font-medium">
                    {{ nav.find((n) => n.key === activeSection)?.label }}
                </span>
                <div class="flex items-center gap-2">
                    <SpaceSwitcher
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button class="text-wasplex-black/60 text-xs hover:underline" @click="logout">Déconnexion</button>
                </div>
            </header>

            <main class="flex-1 p-6">
                <section v-if="activeSection === 'capabilities'" class="flex flex-col gap-4">
                    <form
                        class="rounded-wasplex-lg shadow-wasplex-card flex flex-wrap items-end gap-3 bg-white p-4"
                        @submit.prevent="grantCapability"
                    >
                        <label class="flex flex-col gap-1 text-xs">
                            <span>Compte (id)</span>
                            <input
                                v-model="newGrant.account_id"
                                class="rounded-wasplex-sm border border-black/10 px-2 py-1"
                            />
                        </label>
                        <label class="flex flex-col gap-1 text-xs">
                            <span>Capacité</span>
                            <input
                                v-model="newGrant.capability_code"
                                placeholder="admin.audit.view"
                                class="rounded-wasplex-sm border border-black/10 px-2 py-1"
                            />
                        </label>
                        <button
                            type="submit"
                            class="rounded-wasplex-sm bg-wasplex-black text-wasplex-gold px-3 py-1.5 text-sm font-semibold"
                        >
                            Accorder
                        </button>
                        <p v-if="error" class="text-wasplex-danger text-xs">{{ error }}</p>
                    </form>

                    <table class="rounded-wasplex-lg shadow-wasplex-card w-full bg-white text-sm">
                        <thead>
                            <tr class="text-wasplex-black/50 border-b border-black/5 text-left text-xs">
                                <th class="p-3">Compte</th>
                                <th class="p-3">Capacité</th>
                                <th class="p-3">Périmètre</th>
                                <th class="p-3">Expiration</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in grants" :key="g.id" class="border-b border-black/5">
                                <td class="p-3 font-mono text-xs">{{ g.account_id }}</td>
                                <td class="p-3">{{ g.capability_code }}</td>
                                <td class="text-wasplex-black/50 p-3 text-xs">
                                    {{ g.scope_type ? `${g.scope_type}:${g.scope_id}` : 'global' }}
                                </td>
                                <td class="text-wasplex-black/50 p-3 text-xs">{{ g.expires_at ?? '—' }}</td>
                                <td class="p-3 text-right">
                                    <button class="text-wasplex-danger text-xs hover:underline" @click="revoke(g)">
                                        Révoquer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section
                    v-else
                    class="rounded-wasplex-lg text-wasplex-black/50 shadow-wasplex-card flex h-64 items-center justify-center bg-white text-sm"
                >
                    {{ nav.find((n) => n.key === activeSection)?.label }} — bientôt disponible
                </section>
            </main>
        </div>
    </div>
</template>
