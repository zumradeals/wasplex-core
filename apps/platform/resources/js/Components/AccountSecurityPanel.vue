<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import http from '@/lib/http';

interface SessionItem {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_active_at: string;
    is_current: boolean;
}

const props = defineProps<{ open: boolean; mfaEnabled: boolean }>();
const emit = defineEmits<{ close: []; 'mfa-enabled': [] }>();

const sessions = ref<SessionItem[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);
const busySession = ref<string | null>(null);
const mfaEnabledLocal = ref(props.mfaEnabled);
const enrollment = ref<{ secret: string; otpauth_url: string } | null>(null);
const code = ref('');
const mfaBusy = ref(false);
const mfaMessage = ref<string | null>(null);
const currentPassword = ref('');
const newPassword = ref('');
const newPasswordConfirmation = ref('');
const passwordBusy = ref(false);
const passwordMessage = ref<string | null>(null);
const showPasswordForm = ref(false);

const otherSessions = computed(() => sessions.value.filter((session) => !session.is_current));
const currentSession = computed(() => sessions.value.find((session) => session.is_current) ?? null);
const passwordReady = computed(
    () =>
        currentPassword.value.length > 0 &&
        newPassword.value.length >= 10 &&
        newPassword.value === newPasswordConfirmation.value,
);

function deviceLabel(userAgent: string | null): string {
    if (!userAgent) return 'Appareil inconnu';
    const ua = userAgent.toLowerCase();
    if (ua.includes('android')) return 'Téléphone Android';
    if (ua.includes('iphone') || ua.includes('ipad')) return 'Appareil Apple';
    if (ua.includes('windows')) return 'Ordinateur Windows';
    if (ua.includes('macintosh') || ua.includes('mac os')) return 'Mac';
    if (ua.includes('linux')) return 'Ordinateur Linux';
    return 'Navigateur web';
}

function browserLabel(userAgent: string | null): string {
    if (!userAgent) return '';
    if (userAgent.includes('Edg/')) return 'Edge';
    if (userAgent.includes('Chrome/')) return 'Chrome';
    if (userAgent.includes('Firefox/')) return 'Firefox';
    if (userAgent.includes('Safari/') && !userAgent.includes('Chrome/')) return 'Safari';
    return '';
}

function sessionWhen(value: string): string {
    return new Date(value).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
}

async function loadSessions(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get<{ sessions: SessionItem[] }>('/me/sessions');
        sessions.value = data.sessions;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger vos sessions.';
    } finally {
        loading.value = false;
    }
}

async function revokeSession(session: SessionItem): Promise<void> {
    if (session.is_current) return;
    busySession.value = session.id;
    error.value = null;
    try {
        await http.delete(`/me/sessions/${session.id}`);
        sessions.value = sessions.value.filter((item) => item.id !== session.id);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de fermer cette session.';
    } finally {
        busySession.value = null;
    }
}

async function revokeAllOtherSessions(): Promise<void> {
    error.value = null;
    for (const session of [...otherSessions.value]) {
        busySession.value = session.id;
        try {
            await http.delete(`/me/sessions/${session.id}`);
            sessions.value = sessions.value.filter((item) => item.id !== session.id);
        } catch (e) {
            error.value =
                (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
                'Certaines sessions n’ont pas pu être fermées.';
            break;
        }
    }
    busySession.value = null;
}

async function beginMfaEnrollment(): Promise<void> {
    mfaBusy.value = true;
    mfaMessage.value = null;
    error.value = null;
    try {
        const { data } = await http.post<{ secret: string; otpauth_url: string }>('/me/mfa');
        enrollment.value = data;
        code.value = '';
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de démarrer la double authentification.';
    } finally {
        mfaBusy.value = false;
    }
}

async function confirmMfa(): Promise<void> {
    if (code.value.trim().length < 6) return;
    mfaBusy.value = true;
    mfaMessage.value = null;
    error.value = null;
    try {
        await http.put('/me/mfa', { code: code.value.trim() });
        mfaEnabledLocal.value = true;
        enrollment.value = null;
        code.value = '';
        mfaMessage.value = 'Double authentification activée avec succès.';
        emit('mfa-enabled');
    } catch (e) {
        error.value = (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Code invalide.';
    } finally {
        mfaBusy.value = false;
    }
}

async function changePassword(): Promise<void> {
    if (!passwordReady.value) return;
    passwordBusy.value = true;
    passwordMessage.value = null;
    error.value = null;
    try {
        const { data } = await http.put<{ message: string; revoked_sessions: number }>('/me/password', {
            current_password: currentPassword.value,
            password: newPassword.value,
            password_confirmation: newPasswordConfirmation.value,
        });
        currentPassword.value = '';
        newPassword.value = '';
        newPasswordConfirmation.value = '';
        showPasswordForm.value = false;
        passwordMessage.value =
            data.revoked_sessions > 0
                ? `${data.message} ${data.revoked_sessions} autre${data.revoked_sessions > 1 ? 's' : ''} session${data.revoked_sessions > 1 ? 's' : ''} déconnectée${data.revoked_sessions > 1 ? 's' : ''}.`
                : data.message;
        await loadSessions();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de modifier le mot de passe.';
    } finally {
        passwordBusy.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            mfaEnabledLocal.value = props.mfaEnabled;
            enrollment.value = null;
            code.value = '';
            mfaMessage.value = null;
            passwordMessage.value = null;
            currentPassword.value = '';
            newPassword.value = '';
            newPasswordConfirmation.value = '';
            showPasswordForm.value = false;
            void loadSessions();
        }
    },
);

watch(
    () => props.mfaEnabled,
    (enabled) => {
        mfaEnabledLocal.value = enabled;
    },
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
            @click.self="emit('close')"
        >
            <section
                class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-3xl border shadow-2xl sm:rounded-3xl"
            >
                <header class="bg-wpx-navy-950 sticky top-0 z-10 flex items-start justify-between gap-3 px-4 pt-4 pb-3">
                    <div>
                        <p class="text-wpx-white-soft text-lg font-bold">Compte &amp; sécurité</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Protégez votre accès et vos appareils.</p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </header>

                <div class="flex flex-col gap-3.5 px-4 pb-5">
                    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl overflow-hidden border">
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <span
                                        :class="[
                                            'rounded-wpx-md flex h-10 w-10 shrink-0 items-center justify-center',
                                            mfaEnabledLocal
                                                ? 'bg-wpx-success/12 text-wpx-success-light'
                                                : 'bg-wpx-gold/12 text-wpx-gold',
                                        ]"
                                    >
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path
                                                d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                            />
                                            <path
                                                d="M9 12l2 2 4-4"
                                                stroke="currentColor"
                                                stroke-width="1.7"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            />
                                        </svg>
                                    </span>
                                    <div>
                                        <p class="text-wpx-white-soft text-sm font-bold">Double authentification</p>
                                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                                            {{
                                                mfaEnabledLocal
                                                    ? 'Votre compte est protégé par un code temporaire.'
                                                    : 'Ajoutez une deuxième preuve après votre mot de passe.'
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <span
                                    :class="[
                                        'rounded-full px-2.5 py-1 text-[10px] font-bold',
                                        mfaEnabledLocal
                                            ? 'bg-wpx-success/12 text-wpx-success-light'
                                            : 'bg-wpx-gold/12 text-wpx-gold',
                                    ]"
                                >
                                    {{ mfaEnabledLocal ? 'Activée' : 'À activer' }}
                                </span>
                            </div>

                            <button
                                v-if="!mfaEnabledLocal && !enrollment"
                                type="button"
                                :disabled="mfaBusy"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md mt-4 w-full bg-gradient-to-br px-4 py-2.5 text-sm font-bold disabled:opacity-50"
                                @click="beginMfaEnrollment"
                            >
                                {{ mfaBusy ? 'Préparation…' : 'Activer maintenant' }}
                            </button>

                            <div v-if="enrollment" class="mt-4 flex flex-col gap-3">
                                <div class="bg-wpx-blue/8 border-wpx-blue/20 rounded-wpx-lg border p-3">
                                    <p class="text-wpx-white-soft text-xs font-bold">
                                        1. Ajoutez Wasplex dans votre application
                                    </p>
                                    <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                                        Google Authenticator, Microsoft Authenticator, 1Password ou toute application
                                        TOTP compatible.
                                    </p>
                                </div>
                                <div>
                                    <p class="text-wpx-muted-dark mb-1.5 text-[10px] font-bold uppercase">
                                        Clé secrète
                                    </p>
                                    <code
                                        class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-cyan rounded-wpx-md block border p-3 text-center text-xs font-bold tracking-wider break-all"
                                    >
                                        {{ enrollment.secret }}
                                    </code>
                                    <p class="text-wpx-muted-dark mt-1.5 text-[10px]">Ne partagez jamais cette clé.</p>
                                </div>
                                <label class="flex flex-col gap-1.5">
                                    <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">2. Code à 6 chiffres</span>
                                    <input
                                        v-model="code"
                                        type="text"
                                        inputmode="numeric"
                                        autocomplete="one-time-code"
                                        maxlength="8"
                                        placeholder="000000"
                                        class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-3 text-center text-lg tracking-[0.3em] outline-none"
                                    />
                                </label>
                                <button
                                    type="button"
                                    :disabled="mfaBusy || code.trim().length < 6"
                                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md bg-gradient-to-br px-4 py-2.5 text-sm font-bold disabled:opacity-50"
                                    @click="confirmMfa"
                                >
                                    {{ mfaBusy ? 'Vérification…' : 'Confirmer et activer' }}
                                </button>
                            </div>

                            <p
                                v-if="mfaMessage"
                                class="bg-wpx-success/10 text-wpx-success-light rounded-wpx-md mt-3 p-3 text-xs"
                            >
                                {{ mfaMessage }}
                            </p>
                        </div>
                    </section>

                    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <span class="bg-wpx-blue/12 text-wpx-blue rounded-wpx-md flex h-10 w-10 shrink-0 items-center justify-center">•••</span>
                                <div>
                                    <p class="text-wpx-white-soft text-sm font-bold">Mot de passe</p>
                                    <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                                        Modifiez votre secret d’accès. Par sécurité, les autres appareils seront déconnectés.
                                    </p>
                                </div>
                            </div>
                            <button
                                v-if="!showPasswordForm"
                                type="button"
                                class="text-wpx-cyan shrink-0 text-[10px] font-bold"
                                @click="showPasswordForm = true"
                            >
                                Modifier
                            </button>
                        </div>

                        <div v-if="showPasswordForm" class="mt-4 flex flex-col gap-2.5">
                            <label class="flex flex-col gap-1.5">
                                <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">Mot de passe actuel</span>
                                <input
                                    v-model="currentPassword"
                                    type="password"
                                    autocomplete="current-password"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                />
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">Nouveau mot de passe</span>
                                <input
                                    v-model="newPassword"
                                    type="password"
                                    autocomplete="new-password"
                                    placeholder="10 caractères minimum"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                />
                            </label>
                            <label class="flex flex-col gap-1.5">
                                <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">Confirmer le nouveau</span>
                                <input
                                    v-model="newPasswordConfirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                />
                            </label>
                            <p class="text-wpx-muted-dark text-[10px] leading-relaxed">
                                Utilisez au moins 10 caractères, avec lettres majuscules/minuscules et chiffres.
                            </p>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="border-wpx-border-dark text-wpx-muted-dark rounded-wpx-md border px-4 py-2.5 text-xs font-bold"
                                    @click="showPasswordForm = false"
                                >
                                    Annuler
                                </button>
                                <button
                                    type="button"
                                    :disabled="passwordBusy || !passwordReady"
                                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md flex-1 bg-gradient-to-br px-4 py-2.5 text-xs font-bold disabled:opacity-50"
                                    @click="changePassword"
                                >
                                    {{ passwordBusy ? 'Modification…' : 'Modifier et sécuriser' }}
                                </button>
                            </div>
                        </div>

                        <p
                            v-if="passwordMessage"
                            class="bg-wpx-success/10 text-wpx-success-light rounded-wpx-md mt-3 p-3 text-xs"
                        >
                            {{ passwordMessage }}
                        </p>
                    </section>

                    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-wpx-white-soft text-sm font-bold">Appareils connectés</p>
                                <p class="text-wpx-muted-dark mt-1 text-[11px]">
                                    Fermez une session que vous ne reconnaissez pas.
                                </p>
                            </div>
                            <span class="bg-wpx-blue/12 text-wpx-blue rounded-full px-2.5 py-1 text-[10px] font-bold">
                                {{ sessions.length }} session{{ sessions.length > 1 ? 's' : '' }}
                            </span>
                        </div>

                        <div v-if="loading" class="text-wpx-muted-dark py-6 text-center text-xs">Chargement…</div>

                        <div v-else class="mt-3 flex flex-col gap-2">
                            <div
                                v-if="currentSession"
                                class="border-wpx-success/20 bg-wpx-success/6 rounded-wpx-lg border p-3"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-wpx-white-soft text-xs font-bold">
                                            {{ deviceLabel(currentSession.user_agent) }}
                                            <span
                                                v-if="browserLabel(currentSession.user_agent)"
                                                class="text-wpx-muted-dark font-normal"
                                                >· {{ browserLabel(currentSession.user_agent) }}</span
                                            >
                                        </p>
                                        <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                            {{ currentSession.ip_address ?? 'IP inconnue' }} · actif
                                            {{ sessionWhen(currentSession.last_active_at) }}
                                        </p>
                                    </div>
                                    <span
                                        class="bg-wpx-success/12 text-wpx-success-light rounded-full px-2 py-1 text-[9px] font-bold"
                                        >Cet appareil</span
                                    >
                                </div>
                            </div>

                            <div
                                v-for="session in otherSessions"
                                :key="session.id"
                                class="border-wpx-border-dark bg-wpx-navy-750 rounded-wpx-lg border p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-wpx-white-soft text-xs font-bold">
                                            {{ deviceLabel(session.user_agent) }}
                                            <span
                                                v-if="browserLabel(session.user_agent)"
                                                class="text-wpx-muted-dark font-normal"
                                                >· {{ browserLabel(session.user_agent) }}</span
                                            >
                                        </p>
                                        <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                            {{ session.ip_address ?? 'IP inconnue' }} ·
                                            {{ sessionWhen(session.last_active_at) }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="busySession !== null"
                                        class="text-wpx-danger-light shrink-0 text-[10px] font-bold disabled:opacity-50"
                                        @click="revokeSession(session)"
                                    >
                                        {{ busySession === session.id ? 'Fermeture…' : 'Déconnecter' }}
                                    </button>
                                </div>
                            </div>

                            <p
                                v-if="otherSessions.length === 0"
                                class="text-wpx-muted-dark py-2 text-center text-[10px]"
                            >
                                Aucun autre appareil connecté.
                            </p>
                        </div>

                        <button
                            v-if="otherSessions.length > 1"
                            type="button"
                            :disabled="busySession !== null"
                            class="border-wpx-danger/25 text-wpx-danger-light rounded-wpx-md mt-3 w-full border px-3 py-2.5 text-xs font-bold disabled:opacity-50"
                            @click="revokeAllOtherSessions"
                        >
                            Déconnecter tous les autres appareils
                        </button>
                    </section>

                    <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5">
                        <div class="flex items-start gap-3">
                            <span
                                class="bg-wpx-orange/12 text-wpx-orange flex h-9 w-9 shrink-0 items-center justify-center rounded-full"
                                >!</span
                            >
                            <div>
                                <p class="text-wpx-white-soft text-xs font-bold">Un accès vous semble suspect ?</p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                                    Déconnectez l’appareil concerné, changez votre mot de passe puis activez la double authentification.
                                </p>
                            </div>
                        </div>
                    </div>

                    <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs">
                        {{ error }}
                    </p>
                </div>
            </section>
        </div>
    </Teleport>
</template>
