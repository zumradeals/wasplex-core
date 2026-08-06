<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';

const identifierType = ref<'email' | 'phone'>('email');
const identifierValue = ref('');
const password = ref('');
const countryCode = ref('CI');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;

    try {
        await http.post('/register', {
            identifier_type: identifierType.value,
            identifier_value: identifierValue.value,
            password: password.value,
            country_code: countryCode.value,
        });

        await http.post('/login', {
            identifier_value: identifierValue.value,
            password: password.value,
        });

        router.visit('/app');
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value = message ?? 'La création du compte a échoué.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <main class="bg-wpx-navy-950 mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-4 py-10">
        <div class="flex flex-col items-center text-center">
            <div class="rounded-wpx-lg bg-white p-2 shadow">
                <img src="/brand/wasplex-logo-full.png" alt="Wasplex" class="h-14 w-14 object-contain" />
            </div>
            <h1 class="text-wpx-white-soft mt-4 text-xl font-semibold">Créer un compte</h1>
        </div>

        <form
            class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-4 p-6"
            @submit.prevent="submit"
        >
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Type d'identifiant</span>
                <select
                    v-model="identifierType"
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft border px-3 py-2"
                >
                    <option value="email">Email</option>
                    <option value="phone">Téléphone</option>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Email ou téléphone</span>
                <input
                    v-model="identifierValue"
                    type="text"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Pays (code ISO2)</span>
                <input
                    v-model="countryCode"
                    type="text"
                    maxlength="2"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 uppercase focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    minlength="8"
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
                Créer mon compte
            </button>

            <a href="/login" class="text-wpx-muted-dark text-center text-sm hover:underline">J'ai déjà un compte</a>
        </form>
    </main>
</template>
