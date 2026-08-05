<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';

const identifierValue = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;

    try {
        await http.post('/login', {
            identifier_value: identifierValue.value,
            password: password.value,
        });
        router.visit('/app');
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value = message ?? 'Identifiants invalides.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-4">
        <div class="text-center">
            <span class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-3 py-1 text-sm font-semibold">
                Wasplex
            </span>
            <h1 class="text-wasplex-black mt-4 text-xl font-semibold">Connexion</h1>
        </div>

        <form class="rounded-wasplex-lg shadow-wasplex-card flex flex-col gap-4 bg-white p-6" @submit.prevent="submit">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Téléphone ou email</span>
                <input
                    v-model="identifierValue"
                    type="text"
                    required
                    class="rounded-wasplex-sm focus:ring-wasplex-gold border border-black/10 px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="rounded-wasplex-sm focus:ring-wasplex-gold border border-black/10 px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <p v-if="error" class="text-wasplex-danger text-sm">{{ error }}</p>

            <button
                type="submit"
                :disabled="submitting"
                class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-4 py-2 font-semibold transition disabled:opacity-50"
            >
                Se connecter
            </button>

            <a href="/register" class="text-wasplex-black/60 text-center text-sm hover:underline">Créer un compte</a>
        </form>
    </main>
</template>
