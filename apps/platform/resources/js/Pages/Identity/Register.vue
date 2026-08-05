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
    <main class="mx-auto flex min-h-screen max-w-sm flex-col justify-center gap-6 px-4 py-10">
        <div class="text-center">
            <span class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-3 py-1 text-sm font-semibold">
                Wasplex
            </span>
            <h1 class="text-wasplex-black mt-4 text-xl font-semibold">Créer un compte</h1>
        </div>

        <form class="rounded-wasplex-lg shadow-wasplex-card flex flex-col gap-4 bg-white p-6" @submit.prevent="submit">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Type d'identifiant</span>
                <select v-model="identifierType" class="rounded-wasplex-sm border border-black/10 px-3 py-2">
                    <option value="email">Email</option>
                    <option value="phone">Téléphone</option>
                </select>
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Email ou téléphone</span>
                <input
                    v-model="identifierValue"
                    type="text"
                    required
                    class="rounded-wasplex-sm focus:ring-wasplex-gold border border-black/10 px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Pays (code ISO2)</span>
                <input
                    v-model="countryCode"
                    type="text"
                    maxlength="2"
                    required
                    class="rounded-wasplex-sm focus:ring-wasplex-gold border border-black/10 px-3 py-2 uppercase focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wasplex-black font-medium">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    minlength="8"
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
                Créer mon compte
            </button>

            <a href="/login" class="text-wasplex-black/60 text-center text-sm hover:underline">J'ai déjà un compte</a>
        </form>
    </main>
</template>
