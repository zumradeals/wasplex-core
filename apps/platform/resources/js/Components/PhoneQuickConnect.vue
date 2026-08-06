<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';
import TapMatchGate from '@/Components/TapMatchGate.vue';

const COUNTRIES = [
    { iso2: 'CI', dial: '+225', label: '🇨🇮 Côte d’Ivoire' },
    { iso2: 'SN', dial: '+221', label: '🇸🇳 Sénégal' },
    { iso2: 'BJ', dial: '+229', label: '🇧🇯 Bénin' },
    { iso2: 'TG', dial: '+228', label: '🇹🇬 Togo' },
    { iso2: 'BF', dial: '+226', label: '🇧🇫 Burkina Faso' },
    { iso2: 'ML', dial: '+223', label: '🇲🇱 Mali' },
] as const;

const mode = ref<'login' | 'register'>('login');
const country = ref<(typeof COUNTRIES)[number]>(COUNTRIES[0]);
const localNumber = ref('');
const password = ref('');
const gateSolved = ref(false);
const error = ref<string | null>(null);
const submitting = ref(false);

const fullPhone = computed(() => `${country.value.dial}${localNumber.value.replace(/\D/g, '')}`);
const canSubmit = computed(
    () => gateSolved.value && localNumber.value.trim().length >= 6 && password.value.length >= 8 && !submitting.value,
);

function switchMode(next: 'login' | 'register'): void {
    mode.value = next;
    error.value = null;
}

async function submit(): Promise<void> {
    if (!canSubmit.value) {
        return;
    }

    error.value = null;
    submitting.value = true;

    try {
        if (mode.value === 'register') {
            await http.post('/register', {
                identifier_type: 'phone',
                identifier_value: fullPhone.value,
                password: password.value,
                country_code: country.value.iso2,
            });
        }

        await http.post('/login', {
            identifier_value: fullPhone.value,
            password: password.value,
        });

        router.visit('/app');
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value =
            message ?? (mode.value === 'register' ? 'La création du compte a échoué.' : 'Identifiants invalides.');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div id="connexion-rapide" class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-4 p-5">
        <div class="bg-wpx-navy-750 rounded-wpx-md flex p-1 text-sm font-semibold">
            <button
                type="button"
                class="rounded-wpx-sm flex-1 py-2 transition"
                :class="mode === 'login' ? 'bg-wpx-blue text-wpx-navy-950' : 'text-wpx-muted-dark'"
                @click="switchMode('login')"
            >
                Connexion
            </button>
            <button
                type="button"
                class="rounded-wpx-sm flex-1 py-2 transition"
                :class="mode === 'register' ? 'bg-wpx-blue text-wpx-navy-950' : 'text-wpx-muted-dark'"
                @click="switchMode('register')"
            >
                Inscription
            </button>
        </div>

        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Numéro de téléphone</span>
                <div class="flex gap-2">
                    <select
                        v-model="country"
                        class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft border px-2 py-2 text-sm"
                    >
                        <option v-for="c in COUNTRIES" :key="c.iso2" :value="c">{{ c.dial }}</option>
                    </select>
                    <input
                        v-model="localNumber"
                        type="tel"
                        inputmode="numeric"
                        required
                        placeholder="07 00 00 00 00"
                        class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue w-full border px-3 py-2 focus:ring-2 focus:outline-none"
                    />
                </div>
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

            <TapMatchGate @solved="gateSolved = true" />

            <p v-if="error" class="text-wpx-danger text-sm">{{ error }}</p>

            <button
                type="submit"
                :disabled="!canSubmit"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 ease-wpx-standard bg-gradient-to-br px-4 py-2 font-semibold transition duration-200 disabled:opacity-50"
            >
                {{ mode === 'register' ? 'Créer mon compte' : 'Se connecter' }}
            </button>

            <a href="/login" class="text-wpx-muted-dark text-center text-xs hover:underline">
                Options avancées (email, autre méthode)
            </a>
        </form>
    </div>
</template>
