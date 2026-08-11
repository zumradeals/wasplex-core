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

interface AccountRow {
    id: string;
    primary_identifier: string;
}

interface TeamMember {
    accountId: string;
    grants: Grant[];
    role: ReturnType<typeof roleForCapabilities>;
}

const page = usePage<{ auth: AuthShared }>();

const grants = ref<Grant[]>([]);
const accountLabels = ref<Record<string, string>>({});
const loading = ref(true);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const advancedMode = ref(false);
const inviting = ref(false);
const inviteForm = reactive({ account_identifier: '', role_code: ADMIN_ROLES[0].code });
const newGrant = ref({ account_id: '', capability_code: '' });

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
        role: roleForCapabilities(accountGrants.map((grant) => grant.capability_code)),
    }));
});

function initials(label: string): string {
    const cleaned = label.replace(/[^a-zA-Z0-9]/g, '');
    return (cleaned.slice(0, 2) || 'AD').toUpperCase();
}

function memberLabel(member: TeamMember): string {
    return accountLabels.value[member.accountId] ?? `Compte ${member.accountId.slice(0, 6)}…`;
}

async function enrichAccountLabels(): Promise<void> {
    const ids = [...new Set(grants.value.map((grant) => grant.account_id))];
    const results = await Promise.allSettled(ids.map((id) => http.get(`/admin/accounts/${id}`)));
    const labels: Record<string, string> = {};

    results.forEach((result, index) => {
        if (result.status === 'fulfilled') {
            const detail = result.value.data;
            labels[ids[index]] = detail.account.identifiers?.[0] ?? ids[index];
        }
    });

    accountLabels.value = labels;
}

async function loadGrants(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/capabilities');
        grants.value = data.grants;
        await enrichAccountLabels();
    } finally {
        loading.value = false;
    }
}

async function resolveAccountId(identifier: string): Promise<string | null> {
    const { data } = await http.get('/admin/accounts', { params: { q: identifier } });
    const accounts: AccountRow[] = data.accounts;
    const exact = accounts.find(
        (account) => account.id === identifier || account.primary_identifier.toLowerCase() === identifier.toLowerCase(),
    );

    if (exact) return exact.id;
    return accounts.length === 1 ? accounts[0].id : null;
}

async function submitInvite(): Promise<void> {
    error.value = null;
    const role = ADMIN_ROLES.find((candidate) => candidate.code === inviteForm.role_code);
    const identifier = inviteForm.account_identifier.trim();
    if (!role || !identifier) return;

    busy.value = 'invite';
    try {
        const accountId = await resolveAccountId(identifier);
        if (!accountId) {
            error.value = 'Compte introuvable. Utilise le téléphone ou l’email exact de la personne.';
            return;
        }

        for (const capabilityCode of role.capabilities) {
            await http.post('/admin/capabilities', { account_id: accountId, capability_code: capabilityCode });
        }

        inviteForm.account_identifier = '';
        inviting.value = false;
        await loadGrants();
    } catch {
        error.value = 'L’attribution du rôle a échoué.';
    } finally {
        busy.value = null;
    }
}

async function removeAccess(member: TeamMember): Promise<void> {
    const confirmed = window.confirm(`Retirer l’accès administration à ${memberLabel(member)} ?`);
    if (!confirmed) return;

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
        error.value = 'L’attribution a échoué.';
    }
}

async function revoke(grant: Grant): Promise<void> {
    await http.delete(`/admin/capabilities/${grant.id}`);
    await loadGrants();
}

onMounted(loadGrants);
</script>

<template>
    <div class="text-wpx-white-soft mx-auto flex max-w-5xl flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">
                        Équipe d’administration
                    </p>
                    <h2 class="mt-1 text-xl font-extrabold">Donner le bon accès à la bonne personne.</h2>
                    <p class="text-wpx-muted-dark mt-1 max-w-2xl text-sm">
                        Chacun utilise son propre compte Wasplex. Tu choisis simplement son rôle ; les permissions
                        techniques restent cachées sauf en mode avancé.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan rounded-wpx-md text-wpx-navy-950 bg-gradient-to-br px-4 py-2.5 text-xs font-extrabold"
                    @click="inviting = !inviting"
                >
                    {{ inviting ? 'Fermer' : '+ Ajouter une personne' }}
                </button>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/15 text-wpx-danger rounded-wpx-md p-3 text-xs">{{ error }}</p>

        <form
            v-if="inviting"
            class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5"
            @submit.prevent="submitInvite"
        >
            <h3 class="text-base font-extrabold">Ajouter un accès</h3>
            <p class="text-wpx-muted-dark mt-1 text-xs">
                Entre le téléphone ou l’email de la personne, puis choisis son rôle.
            </p>
            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_280px_auto] md:items-end">
                <label class="flex flex-col gap-1.5 text-xs">
                    <span class="text-wpx-muted-dark font-bold">Téléphone ou email</span>
                    <input
                        v-model="inviteForm.account_identifier"
                        placeholder="Ex. +225… ou nom@exemple.com"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2.5 text-sm outline-none"
                        required
                    />
                </label>
                <label class="flex flex-col gap-1.5 text-xs">
                    <span class="text-wpx-muted-dark font-bold">Rôle</span>
                    <select
                        v-model="inviteForm.role_code"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2.5 text-sm"
                    >
                        <option v-for="role in ADMIN_ROLES" :key="role.code" :value="role.code">{{ role.name }}</option>
                    </select>
                </label>
                <button
                    type="submit"
                    class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                    :disabled="busy === 'invite'"
                >
                    Donner l’accès
                </button>
            </div>
        </form>

        <section
            class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
        >
            <div class="border-wpx-border-dark flex items-center justify-between border-b px-5 py-4">
                <div>
                    <h3 class="text-base font-extrabold">Qui peut administrer Wasplex ?</h3>
                    <p class="text-wpx-muted-dark mt-0.5 text-xs">
                        {{ team.length }} compte{{ team.length > 1 ? 's' : '' }} avec un accès.
                    </p>
                </div>
            </div>
            <p v-if="loading" class="text-wpx-muted-dark p-5 text-sm">Chargement…</p>
            <div v-else class="divide-wpx-border-dark divide-y">
                <div
                    v-for="member in team"
                    :key="member.accountId"
                    class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center"
                >
                    <span
                        class="from-wpx-blue to-wpx-cyan flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-extrabold text-white"
                    >
                        {{ initials(memberLabel(member)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="truncate text-sm font-extrabold">{{ memberLabel(member) }}</p>
                            <span
                                v-if="member.accountId === page.props.auth.account.id"
                                class="text-wpx-cyan text-[10px] font-extrabold"
                                >TOI</span
                            >
                        </div>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            {{ member.role?.description ?? `${member.grants.length} permissions personnalisées` }}
                        </p>
                    </div>
                    <span
                        class="rounded-wpx-full px-3 py-1 text-[10px] font-extrabold"
                        :class="
                            member.role?.code === 'founder'
                                ? 'bg-wpx-gold/15 text-wpx-gold'
                                : 'bg-wpx-blue/15 text-wpx-blue'
                        "
                    >
                        {{ member.role?.name ?? 'Accès personnalisé' }}
                    </span>
                    <button
                        v-if="member.role?.code !== 'founder'"
                        type="button"
                        class="text-wpx-danger text-xs font-extrabold disabled:opacity-50"
                        :disabled="busy === member.accountId"
                        @click="removeAccess(member)"
                    >
                        Retirer l’accès
                    </button>
                </div>
                <p v-if="team.length === 0" class="text-wpx-muted-dark p-5 text-sm">
                    Aucun accès administration trouvé.
                </p>
            </div>
        </section>

        <section>
            <h3 class="text-base font-extrabold">Rôles simples</h3>
            <p class="text-wpx-muted-dark mt-1 text-xs">Tu n’as pas besoin de connaître les permissions une par une.</p>
            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                <div
                    v-for="role in ADMIN_ROLES"
                    :key="role.code"
                    class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4"
                >
                    <div class="flex items-start gap-3">
                        <span
                            class="bg-wpx-blue/12 text-wpx-blue rounded-wpx-md flex h-9 w-9 shrink-0 items-center justify-center"
                            >◎</span
                        >
                        <div>
                            <p class="text-sm font-extrabold">{{ role.name }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">{{ role.description }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <button
            type="button"
            class="text-wpx-cyan self-start text-xs font-extrabold"
            @click="advancedMode = !advancedMode"
        >
            {{ advancedMode ? 'Masquer les permissions techniques' : 'Mode avancé — permissions techniques' }}
        </button>

        <section
            v-if="advancedMode"
            class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5"
        >
            <div class="rounded-wpx-md bg-wpx-warning/10 text-wpx-gold p-3 text-xs">
                Réservé aux cas particuliers. Pour la plupart des personnes, utilise simplement un rôle ci-dessus.
            </div>
            <form
                class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end"
                @submit.prevent="grantCapability"
            >
                <label class="flex flex-col gap-1 text-xs">
                    <span class="text-wpx-muted-dark">ID du compte</span>
                    <input
                        v-model="newGrant.account_id"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2 text-sm"
                    />
                </label>
                <label class="flex flex-col gap-1 text-xs">
                    <span class="text-wpx-muted-dark">Permission</span>
                    <input
                        v-model="newGrant.capability_code"
                        placeholder="admin.audit.view"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2 text-sm"
                    />
                </label>
                <button
                    type="submit"
                    class="bg-wpx-cyan rounded-wpx-md text-wpx-navy-950 px-4 py-2 text-xs font-extrabold"
                >
                    Accorder
                </button>
            </form>

            <div class="mt-5 overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr
                            class="border-wpx-border-dark text-wpx-muted-dark border-b text-left text-[10px] tracking-wide uppercase"
                        >
                            <th class="p-2">Compte</th>
                            <th class="p-2">Permission</th>
                            <th class="p-2">Périmètre</th>
                            <th class="p-2">Expiration</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="grant in grants" :key="grant.id" class="border-wpx-border-dark border-b">
                            <td class="p-2 text-xs">{{ accountLabels[grant.account_id] ?? grant.account_id }}</td>
                            <td class="p-2 font-mono text-xs">{{ grant.capability_code }}</td>
                            <td class="text-wpx-muted-dark p-2 text-xs">
                                {{ grant.scope_type ? `${grant.scope_type}:${grant.scope_id}` : 'global' }}
                            </td>
                            <td class="text-wpx-muted-dark p-2 text-xs">{{ grant.expires_at ?? '—' }}</td>
                            <td class="p-2 text-right">
                                <button class="text-wpx-danger text-xs font-bold" @click="revoke(grant)">
                                    Révoquer
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
