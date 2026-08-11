<script setup lang="ts">
import { computed, ref } from 'vue';
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
const showInvite = ref(false);

const memberCountLabel = computed(() => `${members.value.length} membre${members.value.length > 1 ? 's' : ''}`);

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
        return name
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.slice(0, 1))
            .join('')
            .toUpperCase();
    }

    return member.account_id.slice(-2).toUpperCase();
}

function titleLabel(title: string | null): string {
    const normalized = title?.toLowerCase();
    return (
        {
            owner: 'Propriétaire',
            admin: 'Administrateur',
            manager: 'Responsable',
            member: 'Membre',
        }[normalized ?? ''] ??
        title ??
        'Membre'
    );
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
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 lg:gap-5">
        <div v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-2xl p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-5 sm:p-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="max-w-xl">
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">Équipe & accès</p>
                        <h1 class="text-wpx-white-soft mt-1 text-xl font-extrabold sm:text-2xl">
                            Travaillez seul ou à plusieurs.
                        </h1>
                        <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                            Si vous gérez votre activité seul, vous n’avez rien à configurer ici. Invitez quelqu’un
                            seulement lorsque vous souhaitez partager l’accès au Studio.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-wpx-navy-750 text-wpx-muted-dark rounded-full px-3 py-1.5 text-xs font-bold">
                            {{ loading ? '…' : memberCountLabel }}
                        </span>
                        <button
                            v-if="props.organizationId"
                            type="button"
                            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-4 py-2.5 text-xs font-extrabold"
                            @click="showInvite = !showInvite"
                        >
                            {{ showInvite ? 'Fermer' : '+ Inviter' }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="!props.organizationId"
                    class="bg-wpx-gold/8 border-wpx-gold/20 text-wpx-muted-dark mt-5 rounded-2xl border p-4 text-xs leading-relaxed"
                >
                    Aucun espace d’organisation n’est encore associé à ce Studio.
                </div>

                <form
                    v-if="showInvite && props.organizationId"
                    class="border-wpx-border-dark bg-wpx-navy-750 mt-5 rounded-2xl border p-4"
                    @submit.prevent="sendInvitation"
                >
                    <label class="block">
                        <span class="text-wpx-white-soft text-sm font-bold">Qui voulez-vous inviter ?</span>
                        <span class="text-wpx-muted-dark mt-1 block text-xs"
                            >Saisissez son numéro de téléphone ou son adresse email.</span
                        >
                        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                            <input
                                v-model="identifierValue"
                                type="text"
                                placeholder="Ex. +225… ou nom@email.com"
                                class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft placeholder:text-wpx-muted-dark min-w-0 flex-1 rounded-xl border px-4 py-3 text-sm outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="inviting || !identifierValue.trim()"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-xl bg-gradient-to-r px-5 py-3 text-sm font-extrabold disabled:opacity-50"
                            >
                                {{ inviting ? 'Invitation…' : 'Créer l’invitation' }}
                            </button>
                        </div>
                    </label>
                    <p v-if="inviteError" class="text-wpx-danger-light mt-2.5 text-xs">{{ inviteError }}</p>

                    <div v-if="lastInvitation" class="border-wpx-cyan/25 bg-wpx-cyan/8 mt-4 rounded-2xl border p-4">
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">Invitation prête</p>
                        <p class="text-wpx-white-soft mt-1 text-sm font-bold">Envoyez ce code à la personne invitée.</p>
                        <div
                            class="border-wpx-border-dark bg-wpx-navy-950 mt-3 flex items-center gap-3 rounded-xl border p-3"
                        >
                            <code class="text-wpx-gold min-w-0 flex-1 truncate text-sm font-bold">{{
                                lastInvitation.token
                            }}</code>
                            <button type="button" class="text-wpx-cyan shrink-0 text-xs font-bold" @click="copyToken">
                                {{ copied ? 'Copié ✓' : 'Copier' }}
                            </button>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 text-[11px] leading-relaxed">
                            Wasplex ne l’envoie pas automatiquement pour le moment : transmettez-le par WhatsApp, SMS ou
                            email.
                        </p>
                    </div>
                </form>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-2xl border">
                <div class="border-wpx-border-dark flex items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                    <div>
                        <h2 class="text-wpx-white-soft text-sm font-bold">Personnes ayant accès</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">Les membres de votre espace annonceur.</p>
                    </div>
                    <span class="text-wpx-muted-dark text-xs">{{ loading ? '…' : memberCountLabel }}</span>
                </div>

                <div v-if="loading" class="text-wpx-muted-dark p-5 text-sm">Chargement…</div>
                <div v-else-if="members.length === 0" class="px-5 py-9 text-center">
                    <span class="bg-wpx-blue/12 mx-auto flex h-12 w-12 items-center justify-center rounded-2xl">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <circle cx="9" cy="8" r="3" stroke="#4FA3FF" stroke-width="1.7" />
                            <path
                                d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                                stroke="#4FA3FF"
                                stroke-width="1.7"
                                stroke-linecap="round"
                            />
                            <path d="M18 8v6M15 11h6" stroke="#2BC4DE" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                    </span>
                    <p class="text-wpx-white-soft mt-3 text-sm font-bold">Vous gérez cet espace seul</p>
                    <p class="text-wpx-muted-dark mx-auto mt-1 max-w-sm text-xs leading-relaxed">
                        C’est parfaitement adapté à un commerçant, indépendant ou solo-entrepreneur. Vous pourrez
                        inviter quelqu’un plus tard.
                    </p>
                </div>

                <div v-else class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-3">
                    <article
                        v-for="member in members"
                        :key="member.account_id"
                        class="border-wpx-border-dark bg-wpx-navy-750 rounded-2xl border p-4"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="from-wpx-blue to-wpx-cyan flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br text-sm font-black text-white"
                            >
                                {{ initials(member) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-wpx-white-soft truncate text-sm font-bold">
                                    {{ member.display_name ?? 'Membre Wasplex' }}
                                </p>
                                <p class="text-wpx-cyan mt-0.5 text-[11px] font-bold">{{ titleLabel(member.title) }}</p>
                            </div>
                        </div>
                        <p v-if="!member.display_name" class="text-wpx-muted-dark mt-3 truncate font-mono text-[10px]">
                            {{ member.account_id }}
                        </p>
                    </article>
                </div>
            </section>
        </template>
    </div>
</template>
