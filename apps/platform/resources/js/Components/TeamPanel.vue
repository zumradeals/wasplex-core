<script setup lang="ts">
import { ref } from 'vue';
import http from '@/lib/http';

interface Member {
    account_id: string;
    display_name: string | null;
    title: string | null;
    status: string;
    joined_at: string;
}

interface Invitation {
    id: string;
    token: string;
    expires_at: string;
}

const props = defineProps<{ organizationId: string | null }>();

const members = ref<Member[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const identifierValue = ref('');
const inviting = ref(false);
const inviteError = ref<string | null>(null);
const lastInvitation = ref<Invitation | null>(null);
const copied = ref(false);

async function load(): Promise<void> {
    if (!props.organizationId) {
        loading.value = false;
        return;
    }

    loading.value = true;
    loadError.value = null;
    try {
        const { data } = await http.get(`/organizations/${props.organizationId}/members`);
        members.value = data.members;
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? "L'équipe est momentanément indisponible.";
    } finally {
        loading.value = false;
    }
}

function initials(member: Member): string {
    const name = member.display_name?.trim();
    if (name) {
        return name.slice(0, 2).toUpperCase();
    }

    return member.account_id.slice(-2).toUpperCase();
}

async function sendInvitation(): Promise<void> {
    if (!props.organizationId || !identifierValue.value.trim()) {
        return;
    }

    inviteError.value = null;
    copied.value = false;
    inviting.value = true;
    try {
        const identifierType = identifierValue.value.includes('@') ? 'email' : 'phone';
        const { data } = await http.post(`/organizations/${props.organizationId}/invitations`, {
            identifier_type: identifierType,
            identifier_value: identifierValue.value.trim(),
        });
        lastInvitation.value = data;
        identifierValue.value = '';
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        inviteError.value = message ?? "L'invitation a échoué.";
    } finally {
        inviting.value = false;
    }
}

async function copyToken(): Promise<void> {
    if (!lastInvitation.value) {
        return;
    }

    await navigator.clipboard.writeText(lastInvitation.value.token);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 1800);
}

void load();
</script>

<template>
    <div class="flex flex-col gap-5">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <section class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface border-wpx-border border p-5.5">
                <h2 class="text-wpx-text mb-4 text-[15px] font-bold">Inviter un membre</h2>
                <form class="flex gap-2.5" @submit.prevent="sendInvitation">
                    <input
                        v-model="identifierValue"
                        type="text"
                        placeholder="Numéro ou email du membre"
                        class="border-wpx-border bg-wpx-raised text-wpx-text placeholder:text-wpx-text-muted/60 rounded-wpx-md flex-1 border px-3.5 py-2.5 text-sm"
                    />
                    <button
                        type="submit"
                        :disabled="inviting || !identifierValue.trim()"
                        class="bg-wpx-blue-light rounded-wpx-md px-5.5 py-2.5 text-sm font-bold whitespace-nowrap text-white disabled:opacity-50"
                    >
                        Inviter
                    </button>
                </form>
                <p v-if="inviteError" class="text-wpx-danger-light mt-2.5 text-xs">{{ inviteError }}</p>

                <div
                    v-if="lastInvitation"
                    class="bg-wpx-raised border-wpx-border rounded-wpx-md mt-4 flex items-center justify-between gap-3 border p-3.5"
                >
                    <div class="min-w-0">
                        <p class="text-wpx-text-muted text-[10px] font-bold tracking-wide uppercase">
                            Code d'invitation
                        </p>
                        <p class="text-wpx-blue-light mt-0.5 truncate font-mono text-sm font-bold">
                            {{ lastInvitation.token }}
                        </p>
                        <p class="text-wpx-text-muted/70 mt-1 text-xs">
                            Aucun email n'est envoyé automatiquement — transmets ce code toi-même à la personne invitée.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="border-wpx-border rounded-wpx-sm text-wpx-text shrink-0 border px-3.5 py-2 text-xs font-bold"
                        @click="copyToken"
                    >
                        {{ copied ? 'Copié !' : 'Copier' }}
                    </button>
                </div>
            </section>

            <section class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface border-wpx-border overflow-hidden border">
                <h2 class="text-wpx-text border-wpx-border border-b px-5.5 py-4 text-sm font-bold">
                    Membres de l'organisation
                </h2>
                <p v-if="loading" class="text-wpx-text-muted p-5.5 text-sm">Chargement…</p>
                <ul v-else class="divide-wpx-border divide-y">
                    <li
                        v-for="member in members"
                        :key="member.account_id"
                        class="flex items-center gap-3.5 px-5.5 py-4"
                    >
                        <span
                            class="from-wpx-blue to-wpx-cyan flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[13px] font-bold text-white"
                        >
                            {{ initials(member) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-wpx-text truncate text-sm font-bold">
                                {{ member.display_name ?? member.account_id }}
                            </p>
                            <p v-if="member.display_name" class="text-wpx-text-muted/70 truncate font-mono text-[11px]">
                                {{ member.account_id }}
                            </p>
                        </div>
                        <span
                            class="bg-wpx-blue-light/10 text-wpx-blue-light rounded-full px-3 py-1 text-[11px] font-bold whitespace-nowrap capitalize"
                        >
                            {{ member.title ?? 'Membre' }}
                        </span>
                    </li>
                    <li v-if="members.length === 0" class="text-wpx-text-muted p-5.5 text-sm">
                        Aucun membre pour le moment.
                    </li>
                </ul>
            </section>
        </template>
    </div>
</template>
