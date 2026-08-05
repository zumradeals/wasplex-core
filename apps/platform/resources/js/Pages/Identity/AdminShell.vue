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
    <div class="bg-wpx-canvas flex min-h-screen">
        <aside class="bg-wpx-navy-950 border-wpx-border-dark flex w-60 flex-col border-r">
            <div class="border-wpx-border-dark border-b p-4">
                <span class="rounded-wpx-md bg-wpx-navy-750 text-wpx-gold px-3 py-1 text-sm font-semibold">
                    Administration
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
            <header class="bg-wpx-surface border-wpx-border flex items-center justify-between border-b px-6 py-3">
                <span class="text-wpx-text text-sm font-medium">
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
                <section v-if="activeSection === 'capabilities'" class="flex flex-col gap-4">
                    <form
                        class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex flex-wrap items-end gap-3 p-4"
                        @submit.prevent="grantCapability"
                    >
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Compte (id)</span>
                            <input
                                v-model="newGrant.account_id"
                                class="rounded-wpx-sm border-wpx-border border px-2 py-1"
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Capacité</span>
                            <input
                                v-model="newGrant.capability_code"
                                placeholder="admin.audit.view"
                                class="rounded-wpx-sm border-wpx-border border px-2 py-1"
                            />
                        </label>
                        <button
                            type="submit"
                            class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold"
                        >
                            Accorder
                        </button>
                        <p v-if="error" class="text-wpx-danger-light text-xs">{{ error }}</p>
                    </form>

                    <table class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface w-full text-sm">
                        <thead>
                            <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                                <th class="p-3">Compte</th>
                                <th class="p-3">Capacité</th>
                                <th class="p-3">Périmètre</th>
                                <th class="p-3">Expiration</th>
                                <th class="p-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in grants" :key="g.id" class="border-wpx-border text-wpx-text border-b">
                                <td class="p-3 font-mono text-xs">{{ g.account_id }}</td>
                                <td class="p-3">{{ g.capability_code }}</td>
                                <td class="text-wpx-text-muted p-3 text-xs">
                                    {{ g.scope_type ? `${g.scope_type}:${g.scope_id}` : 'global' }}
                                </td>
                                <td class="text-wpx-text-muted p-3 text-xs">{{ g.expires_at ?? '—' }}</td>
                                <td class="p-3 text-right">
                                    <button class="text-wpx-danger-light text-xs hover:underline" @click="revoke(g)">
                                        Révoquer
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
