<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import { ADMIN_ROLES, roleForCapabilities } from '@/lib/adminRoles';
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

interface TeamMember {
    accountId: string;
    grants: Grant[];
    role: ReturnType<typeof roleForCapabilities>;
}

const page = usePage<{ auth: AuthShared }>();

const grants = ref<Grant[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const advancedMode = ref(false);

const inviting = ref(false);
const inviteForm = reactive({ account_id: '', role_code: ADMIN_ROLES[0].code });

const newGrant = ref({ account_id: '', capability_code: '' });

function initials(accountId: string): string {
    return accountId.slice(0, 2).toUpperCase();
}

const team = computed<TeamMember[]>(() => {
    const byAccount = new Map<string, Grant[]>();
    for (const grant of grants.value) {
        const list = byAccount.get(grant.account_id) ?? [];
        list.push(grant);
        byAccount.set(grant.account_id, list);
    }
    return Array.from(byAccount.entries()).map(([accountId, accountGrants]) => ({
        accountId,
        grants: accountGrants,
        role: roleForCapabilities(accountGrants.map((g) => g.capability_code)),
    }));
});

async function loadGrants(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/capabilities');
        grants.value = data.grants;
    } finally {
        loading.value = false;
    }
}

async function submitInvite(): Promise<void> {
    error.value = null;
    const role = ADMIN_ROLES.find((r) => r.code === inviteForm.role_code);
    if (!role || !inviteForm.account_id) {
        return;
    }
    busy.value = 'invite';
    try {
        for (const capability_code of role.capabilities) {
            await http.post('/admin/capabilities', { account_id: inviteForm.account_id, capability_code });
        }
        inviteForm.account_id = '';
        inviting.value = false;
        await loadGrants();
    } catch {
        error.value = "L'attribution du rôle a échoué.";
    } finally {
        busy.value = null;
    }
}

async function removeAccess(member: TeamMember): Promise<void> {
    busy.value = member.accountId;
    try {
        for (const grant of member.grants) {
            await http.delete(`/admin/capabilities/${grant.id}`);
        }
        await loadGrants();
    } finally {
        busy.value = null;
    }
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

onMounted(loadGrants);
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <div class="border-wpx-blue-light/25 rounded-wpx-md flex gap-3 border bg-white p-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="mt-0.5 shrink-0">
                <circle cx="12" cy="12" r="9" stroke="#075CCF" stroke-width="1.6" />
                <path d="M12 8v.5M12 11v5" stroke="#075CCF" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <p class="text-wpx-text text-xs leading-relaxed">
                Qui a accès à quoi dans l'administration. Chaque personne a son propre compte — pas de compte partagé.
                Donne uniquement l'accès dont chacun a besoin pour son rôle.
            </p>
        </div>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-sm p-2 text-xs">{{ error }}</p>

        <div class="flex items-center justify-between">
            <p class="text-wpx-text text-[15px] font-bold">Équipe d'administration</p>
            <button
                type="button"
                class="rounded-wpx-sm bg-wpx-navy-950 px-4.5 py-2.5 text-[13px] font-bold text-white"
                @click="inviting = !inviting"
            >
                + Inviter une personne
            </button>
        </div>

        <form
            v-if="inviting"
            class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface flex flex-col gap-3 border p-4"
            @submit.prevent="submitInvite"
        >
            <label class="text-wpx-text flex flex-col gap-1 text-xs">
                <span class="font-semibold">Identifiant du compte à inviter</span>
                <input
                    v-model="inviteForm.account_id"
                    placeholder="Identifiant du compte (visible dans Utilisateurs)"
                    class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                    required
                />
            </label>
            <label class="text-wpx-text flex flex-col gap-1 text-xs">
                <span class="font-semibold">Rôle</span>
                <select
                    v-model="inviteForm.role_code"
                    class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                >
                    <option v-for="role in ADMIN_ROLES" :key="role.code" :value="role.code">{{ role.name }}</option>
                </select>
            </label>
            <div class="flex gap-2">
                <button
                    type="submit"
                    class="rounded-wpx-sm bg-wpx-blue-light px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
                    :disabled="busy === 'invite'"
                >
                    Donner cet accès
                </button>
                <button type="button" class="text-wpx-text-muted text-xs font-semibold" @click="inviting = false">
                    Annuler
                </button>
            </div>
        </form>

        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
        <div v-else class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface overflow-hidden border">
            <div
                v-for="member in team"
                :key="member.accountId"
                class="border-wpx-border/60 flex items-center gap-3.5 border-b p-4 last:border-0"
            >
                <span
                    class="from-wpx-blue to-wpx-cyan flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[13px] font-extrabold text-white"
                >
                    {{ initials(member.accountId) }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="text-wpx-text block truncate text-[13px] font-bold">
                        {{ member.accountId }}
                        <template v-if="member.accountId === page.props.auth.account.id">(toi)</template>
                    </span>
                    <span class="text-wpx-text-muted block text-[11px]">
                        {{ member.role?.description ?? `Combinaison de ${member.grants.length} accès spécifiques` }}
                    </span>
                </span>
                <span
                    class="rounded-wpx-full px-3 py-1 text-[11px] font-bold whitespace-nowrap"
                    :class="
                        member.role?.code === 'founder'
                            ? 'bg-wpx-blue-light/10 text-wpx-blue-light'
                            : 'bg-wpx-canvas text-wpx-text-muted'
                    "
                >
                    {{ member.role?.name ?? 'Accès personnalisé' }}
                </span>
                <button
                    v-if="member.role?.code !== 'founder'"
                    type="button"
                    class="text-wpx-danger-light text-xs font-bold whitespace-nowrap disabled:opacity-50"
                    :disabled="busy === member.accountId"
                    @click="removeAccess(member)"
                >
                    Retirer l'accès
                </button>
            </div>
            <p v-if="team.length <= 1" class="text-wpx-text-muted p-4 text-sm">Aucune autre personne pour le moment.</p>
        </div>

        <p class="text-wpx-text mt-1 text-[15px] font-bold">Rôles disponibles</p>
        <p class="text-wpx-text-muted -mt-2.5 text-xs">
            Un rôle regroupe un ensemble d'accès. Choisis-en un lorsque tu invites quelqu'un.
        </p>
        <div class="flex flex-col gap-2.5">
            <div
                v-for="role in ADMIN_ROLES"
                :key="role.code"
                class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface flex items-center gap-3.5 border p-4"
                :class="role.code === 'read-only' ? 'opacity-70' : ''"
            >
                <span class="bg-wpx-blue-light/10 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="#075CCF" stroke-width="1.7" />
                    </svg>
                </span>
                <span class="flex-1">
                    <span class="text-wpx-text block text-[13px] font-bold">{{ role.name }}</span>
                    <span class="text-wpx-text-muted block text-xs">{{ role.description }}</span>
                </span>
            </div>
        </div>

        <button
            type="button"
            class="text-wpx-blue-light mt-2 self-start text-xs font-semibold"
            @click="advancedMode = !advancedMode"
        >
            {{ advancedMode ? 'Masquer le mode avancé' : 'Mode avancé — attribuer une capacité précise' }}
        </button>

        <div v-if="advancedMode" class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-4">
            <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="grantCapability">
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Compte (id)</span>
                    <input v-model="newGrant.account_id" class="rounded-wpx-sm border-wpx-border border px-2 py-1" />
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
            </form>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Compte</th>
                        <th class="p-2">Capacité</th>
                        <th class="p-2">Périmètre</th>
                        <th class="p-2">Expiration</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="g in grants" :key="g.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2 font-mono text-xs">{{ g.account_id }}</td>
                        <td class="p-2">{{ g.capability_code }}</td>
                        <td class="text-wpx-text-muted p-2 text-xs">
                            {{ g.scope_type ? `${g.scope_type}:${g.scope_id}` : 'global' }}
                        </td>
                        <td class="text-wpx-text-muted p-2 text-xs">{{ g.expires_at ?? '—' }}</td>
                        <td class="p-2 text-right">
                            <button class="text-wpx-danger-light text-xs hover:underline" @click="revoke(g)">
                                Révoquer
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
