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
    <main class="bg-wpx-navy-950 mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-4">
        <div class="flex flex-col items-center text-center">
            <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-16 w-16 object-contain" />
            <h1 class="text-wpx-white-soft mt-4 text-xl font-semibold">Connexion</h1>
        </div>

        <form
            class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-4 p-6"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Téléphone ou email</span>
                <input
                    v-model="identifierValue"
                    type="text"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <p v-if="error" class="text-wpx-danger text-sm">{{ error }}</p>

            <button
                type="submit"
                :disabled="submitting"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 ease-wpx-standard bg-gradient-to-br px-4 py-2 font-semibold transition duration-200 disabled:opacity-50"
            >
                Se connecter
            </button>

            <a href="/register" class="text-wpx-muted-dark text-center text-sm hover:underline">Créer un compte</a>
        </form>
    </main>
</template>
