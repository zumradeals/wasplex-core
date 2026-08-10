<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';
import { useComingSoon } from '@/lib/comingSoon';

const COUNTRIES = [
    { iso2: 'CI', dial: '+225', label: '🇨🇮 Côte d’Ivoire' },
    { iso2: 'SN', dial: '+221', label: '🇸🇳 Sénégal' },
    { iso2: 'BJ', dial: '+229', label: '🇧🇯 Bénin' },
    { iso2: 'TG', dial: '+228', label: '🇹🇬 Togo' },
    { iso2: 'BF', dial: '+226', label: '🇧🇫 Burkina Faso' },
    { iso2: 'ML', dial: '+223', label: '🇲🇱 Mali' },
] as const;

const mode = ref<'login' | 'register'>('login');
const identifierMode = ref<'phone' | 'email'>('phone');
const country = ref<(typeof COUNTRIES)[number]>(COUNTRIES[0]);
const localNumber = ref('');
const email = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);
const { notice: forgotPasswordNotice, announce: announceForgotPassword } = useComingSoon(
    'Réinitialisation du mot de passe bientôt disponible.',
);

const fullPhone = computed(() => `${country.value.dial}${localNumber.value.replace(/\D/g, '')}`);
const localDigits = computed(() => localNumber.value.replace(/\D/g, ''));
const normalizedEmail = computed(() => email.value.trim().toLowerCase());
const canSubmit = computed(
    () =>
        (identifierMode.value === 'phone'
            ? localDigits.value.length >= 6
            : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail.value)) &&
        password.value.length >= 8 &&
        !submitting.value,
);

function switchMode(next: 'login' | 'register'): void {
    mode.value = next;
    if (next === 'register') {
        identifierMode.value = 'phone';
    }
    error.value = null;
}

function switchIdentifierMode(next: 'phone' | 'email'): void {
    identifierMode.value = next;
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
            identifier_value: identifierMode.value === 'phone' ? fullPhone.value : normalizedEmail.value,
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
    <div id="connexion-rapide" class="flex flex-col gap-4">
        <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex gap-1 border p-1">
            <button
                type="button"
                class="rounded-wpx-sm flex-1 py-2.5 text-sm font-semibold transition"
                :class="
                    mode === 'login'
                        ? 'from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br'
                        : 'text-wpx-muted-dark'
                "
                @click="switchMode('login')"
            >
                Connexion
            </button>
            <button
                type="button"
                class="rounded-wpx-sm flex-1 py-2.5 text-sm font-semibold transition"
                :class="
                    mode === 'register'
                        ? 'from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br'
                        : 'text-wpx-muted-dark'
                "
                @click="switchMode('register')"
            >
                Inscription
            </button>
        </div>

        <form class="flex flex-col gap-3.5" @submit.prevent="submit">
            <div v-if="mode === 'login'" class="border-wpx-border-dark rounded-wpx-md grid grid-cols-2 border p-1">
                <button
                    type="button"
                    class="rounded-wpx-sm py-2 text-xs font-semibold transition"
                    :class="identifierMode === 'phone' ? 'bg-wpx-blue text-wpx-navy-950' : 'text-wpx-muted-dark'"
                    @click="switchIdentifierMode('phone')"
                >
                    Téléphone
                </button>
                <button
                    type="button"
                    class="rounded-wpx-sm py-2 text-xs font-semibold transition"
                    :class="identifierMode === 'email' ? 'bg-wpx-blue text-wpx-navy-950' : 'text-wpx-muted-dark'"
                    @click="switchIdentifierMode('email')"
                >
                    Email
                </button>
            </div>

            <label v-if="identifierMode === 'phone'" class="flex flex-col gap-1.5 text-sm">
                <span class="text-wpx-muted-dark font-semibold">Numéro de téléphone</span>
                <div class="flex gap-2">
                    <select
                        v-model="country"
                        class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft w-22 border px-1.5 py-3.5 text-sm font-semibold"
                    >
                        <option v-for="c in COUNTRIES" :key="c.iso2" :value="c">{{ c.dial }}</option>
                    </select>
                    <input
                        v-model="localNumber"
                        type="tel"
                        inputmode="numeric"
                        required
                        placeholder="07 00 00 00 00"
                        class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft focus:ring-wpx-blue w-full border px-4 py-3.5 text-sm focus:ring-2 focus:outline-none"
                    />
                </div>
            </label>

            <label v-else class="flex flex-col gap-1.5 text-sm">
                <span class="text-wpx-muted-dark font-semibold">Adresse email</span>
                <input
                    v-model="email"
                    type="email"
                    inputmode="email"
                    autocomplete="email"
                    required
                    placeholder="nom@exemple.com"
                    class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft focus:ring-wpx-blue border px-4 py-3.5 text-sm focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1.5 text-sm">
                <span class="text-wpx-muted-dark font-semibold">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    minlength="8"
                    required
                    class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft focus:ring-wpx-blue border px-4 py-3.5 text-sm focus:ring-2 focus:outline-none"
                />
            </label>

            <p v-if="error" class="text-wpx-danger text-sm">{{ error }}</p>

            <button
                type="submit"
                :disabled="!canSubmit"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 ease-wpx-standard mt-1 bg-gradient-to-br py-4 text-base font-bold transition duration-200 disabled:opacity-50"
            >
                {{ mode === 'register' ? 'Créer mon compte' : 'Se connecter' }}
            </button>

            <button type="button" class="text-wpx-blue text-center text-sm font-medium" @click="announceForgotPassword">
                Mot de passe oublié ?
            </button>
            <p v-if="forgotPasswordNotice" class="text-wpx-muted-dark text-center text-xs">
                {{ forgotPasswordNotice }}
            </p>

            <button
                v-if="mode === 'login'"
                type="button"
                class="text-wpx-muted-dark text-center text-xs hover:underline"
                @click="switchIdentifierMode(identifierMode === 'phone' ? 'email' : 'phone')"
            >
                {{ identifierMode === 'phone' ? 'Se connecter avec un email' : 'Se connecter avec un téléphone' }}
            </button>
        </form>
    </div>
</template>
