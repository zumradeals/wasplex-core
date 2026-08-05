<script setup lang="ts">
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const enrollment = ref<{ secret: string; otpauth_url: string } | null>(null);
const code = ref('');
const error = ref<string | null>(null);
const busy = ref(false);

async function beginEnrollment(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/me/mfa');
        enrollment.value = data;
    } finally {
        busy.value = false;
    }
}

async function confirmEnrollment(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        await http.put('/me/mfa', { code: code.value });
        await verify();
    } catch {
        error.value = 'Code invalide.';
    } finally {
        busy.value = false;
    }
}

async function verify(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        await http.post('/me/mfa/verify', { code: code.value });
        router.visit('/admin');
    } catch {
        error.value = 'Code invalide.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <main class="bg-wpx-canvas mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-4">
        <h1 class="text-wpx-text text-center text-lg font-semibold">Vérification MFA — Administration</h1>

        <div v-if="!page.props.auth.account.mfa_enabled" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-6">
            <p class="text-wpx-text-muted mb-4 text-sm">
                L'accès à la console fondateur exige une double authentification (TOTP).
            </p>

            <button
                v-if="!enrollment"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 w-full bg-gradient-to-br px-4 py-2 font-semibold"
                :disabled="busy"
                @click="beginEnrollment"
            >
                Activer la double authentification
            </button>

            <div v-else class="flex flex-col gap-3">
                <p class="text-wpx-text-muted text-xs">
                    Ajoutez ce secret dans votre application d'authentification (Google Authenticator, etc.) :
                </p>
                <code class="rounded-wpx-sm bg-wpx-raised border-wpx-border border p-2 text-xs break-all">{{
                    enrollment.secret
                }}</code>
                <input
                    v-model="code"
                    type="text"
                    inputmode="numeric"
                    placeholder="Code à 6 chiffres"
                    class="rounded-wpx-sm border-wpx-border border px-3 py-2"
                />
                <button
                    class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-4 py-2 font-semibold disabled:opacity-50"
                    :disabled="busy || code.length < 6"
                    @click="confirmEnrollment"
                >
                    Confirmer
                </button>
            </div>
        </div>

        <div v-else class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex flex-col gap-3 p-6">
            <p class="text-wpx-text-muted text-sm">Saisissez le code de votre application d'authentification.</p>
            <input
                v-model="code"
                type="text"
                inputmode="numeric"
                placeholder="Code à 6 chiffres"
                class="rounded-wpx-sm border-wpx-border border px-3 py-2"
            />
            <button
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-4 py-2 font-semibold disabled:opacity-50"
                :disabled="busy || code.length < 6"
                @click="verify"
            >
                Vérifier
            </button>
        </div>

        <p v-if="error" class="text-wpx-danger-light text-center text-sm">{{ error }}</p>
    </main>
</template>
