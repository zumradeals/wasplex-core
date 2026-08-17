<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';
import { useComingSoon } from '@/lib/comingSoon';

interface TaxonomyEntry {
    id: string;
    code: string;
    label: string;
    answer: boolean | null;
}

interface Consent {
    code: string;
    name: string;
    description: string;
    status: string;
}

const props = withDefaults(
    defineProps<{
        initialMode?: 'login' | 'register';
    }>(),
    {
        initialMode: 'login',
    },
);

const COUNTRIES = [
    { iso2: 'CI', dial: '+225', label: '🇨🇮 Côte d’Ivoire' },
    { iso2: 'SN', dial: '+221', label: '🇸🇳 Sénégal' },
    { iso2: 'BJ', dial: '+229', label: '🇧🇯 Bénin' },
    { iso2: 'TG', dial: '+228', label: '🇹🇬 Togo' },
    { iso2: 'BF', dial: '+226', label: '🇧🇫 Burkina Faso' },
    { iso2: 'ML', dial: '+223', label: '🇲🇱 Mali' },
] as const;

const REQUIRED_CONSENT_CODES = ['advertising_personalization', 'smart_profile_usage'];

const mode = ref<'login' | 'register'>(props.initialMode);
const registrationStep = ref<'account' | 'intent' | 'profile' | 'ready'>('account');
const identifierMode = ref<'phone' | 'email'>('phone');
const country = ref<(typeof COUNTRIES)[number]>(COUNTRIES[0]);
const localNumber = ref('');
const email = ref('');
const password = ref('');
const error = ref<string | null>(null);
const submitting = ref(false);
const onboardingLoading = ref(false);
const wantsMember = ref(true);
const wantsAdvertiser = ref(false);
const interests = ref<TaxonomyEntry[]>([]);
const selectedInterestIds = ref<string[]>([]);
const consents = ref<Consent[]>([]);
const selectedConsentCodes = ref<string[]>([]);

const { notice: forgotPasswordNotice, announce: announceForgotPassword } = useComingSoon(
    'Réinitialisation du mot de passe bientôt disponible.',
);

const fullPhone = computed(() => `${country.value.dial}${localNumber.value.replace(/\D/g, '')}`);
const localDigits = computed(() => localNumber.value.replace(/\D/g, ''));
const normalizedEmail = computed(() => email.value.trim().toLowerCase());
const identifierValue = computed(() => (identifierMode.value === 'phone' ? fullPhone.value : normalizedEmail.value));
const canSubmit = computed(
    () =>
        (identifierMode.value === 'phone'
            ? localDigits.value.length >= 6
            : /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalizedEmail.value)) &&
        password.value.length >= 8 &&
        !submitting.value,
);
const relevantConsents = computed(() => consents.value.filter((consent) => REQUIRED_CONSENT_CODES.includes(consent.code)));
const selectedInterestCount = computed(() => selectedInterestIds.value.length);

function switchMode(next: 'login' | 'register'): void {
    mode.value = next;
    registrationStep.value = 'account';
    error.value = null;

    if (next === 'register') {
        identifierMode.value = 'phone';
    }
}

function switchIdentifierMode(next: 'phone' | 'email'): void {
    identifierMode.value = next;
    error.value = null;
}

function toggleIntent(intent: 'member' | 'advertiser'): void {
    if (intent === 'member') {
        if (wantsMember.value && !wantsAdvertiser.value) return;
        wantsMember.value = !wantsMember.value;
        return;
    }

    if (wantsAdvertiser.value && !wantsMember.value) return;
    wantsAdvertiser.value = !wantsAdvertiser.value;
}

function toggleInterest(id: string): void {
    selectedInterestIds.value = selectedInterestIds.value.includes(id)
        ? selectedInterestIds.value.filter((value) => value !== id)
        : [...selectedInterestIds.value, id];
}

function toggleConsent(code: string): void {
    selectedConsentCodes.value = selectedConsentCodes.value.includes(code)
        ? selectedConsentCodes.value.filter((value) => value !== code)
        : [...selectedConsentCodes.value, code];
}

async function submit(): Promise<void> {
    if (!canSubmit.value) return;

    error.value = null;
    submitting.value = true;

    try {
        if (mode.value === 'register') {
            await http.post('/register', {
                identifier_type: identifierMode.value,
                identifier_value: identifierValue.value,
                password: password.value,
                country_code: country.value.iso2,
            });
        }

        await http.post('/login', {
            identifier_value: identifierValue.value,
            password: password.value,
        });

        if (mode.value === 'login') {
            router.visit('/app');
            return;
        }

        registrationStep.value = 'intent';
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value =
            message ?? (mode.value === 'register' ? 'La création du compte a échoué.' : 'Identifiants invalides.');
    } finally {
        submitting.value = false;
    }
}

async function continueFromIntent(): Promise<void> {
    onboardingLoading.value = true;
    error.value = null;

    try {
        const [{ data: profileData }, { data: consentData }] = await Promise.all([
            http.get('/me/smart-profile'),
            http.get('/me/consents'),
        ]);

        const categoryEntries = (profileData.categories?.interest ?? []) as TaxonomyEntry[];
        interests.value = categoryEntries.filter((entry) => entry.code.startsWith('interest.'));
        selectedInterestIds.value = interests.value.filter((entry) => entry.answer === true).map((entry) => entry.id);

        consents.value = (consentData.consents ?? []) as Consent[];
        selectedConsentCodes.value = consents.value
            .filter((consent) => consent.status === 'granted' && REQUIRED_CONSENT_CODES.includes(consent.code))
            .map((consent) => consent.code);

        registrationStep.value = 'profile';
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value = message ?? "Impossible de préparer l'onboarding. Tu peux néanmoins entrer dans ton espace.";
    } finally {
        onboardingLoading.value = false;
    }
}

async function saveProfile(): Promise<void> {
    onboardingLoading.value = true;
    error.value = null;

    try {
        const selectedInterests = interests.value.filter((entry) => selectedInterestIds.value.includes(entry.id));
        await Promise.all(selectedInterests.map((entry) => http.post(`/me/smart-profile/${entry.id}`, { answer: true })));

        const consentGrants = relevantConsents.value
            .filter((consent) => selectedConsentCodes.value.includes(consent.code) && consent.status !== 'granted')
            .map((consent) => http.post(`/me/consents/${consent.code}/grant`));

        await Promise.all(consentGrants);
        registrationStep.value = 'ready';
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value = message ?? 'Impossible d’enregistrer toutes tes préférences.';
    } finally {
        onboardingLoading.value = false;
    }
}

function finishWithoutProfile(): void {
    registrationStep.value = 'ready';
    error.value = null;
}

function enterFeed(): void {
    router.visit('/app');
}

function discoverAdvertiserSpace(): void {
    router.visit('/app?tab=espace');
}
</script>

<template>
    <div id="connexion-rapide" class="flex flex-col gap-4">
        <div
            v-if="registrationStep === 'account'"
            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex gap-1 border p-1"
        >
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

        <form v-if="registrationStep === 'account'" class="flex flex-col gap-3.5" @submit.prevent="submit">
            <div class="border-wpx-border-dark rounded-wpx-md grid grid-cols-2 border p-1">
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
                        autocomplete="tel"
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
                <select
                    v-if="mode === 'register'"
                    v-model="country"
                    aria-label="Pays de résidence"
                    class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft mt-2 border px-3 py-3 text-sm"
                >
                    <option v-for="c in COUNTRIES" :key="c.iso2" :value="c">{{ c.label }}</option>
                </select>
            </label>

            <label class="flex flex-col gap-1.5 text-sm">
                <span class="text-wpx-muted-dark font-semibold">Mot de passe</span>
                <input
                    v-model="password"
                    type="password"
                    minlength="8"
                    required
                    autocomplete="current-password"
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

            <button
                v-if="mode === 'login'"
                type="button"
                class="text-wpx-blue text-center text-sm font-medium"
                @click="announceForgotPassword"
            >
                Mot de passe oublié ?
            </button>
            <p v-if="forgotPasswordNotice" class="text-wpx-muted-dark text-center text-xs">
                {{ forgotPasswordNotice }}
            </p>
        </form>

        <section v-else-if="registrationStep === 'intent'" class="flex flex-col gap-4">
            <div>
                <p class="text-wpx-cyan text-xs font-bold">Étape 2 sur 3</p>
                <h2 class="text-wpx-white-soft mt-1 text-lg font-black">Que veux-tu faire sur Wasplex ?</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs">Tu peux choisir les deux et changer plus tard.</p>
            </div>

            <button
                type="button"
                class="rounded-wpx-lg border p-4 text-left transition"
                :class="
                    wantsMember
                        ? 'border-wpx-cyan bg-wpx-cyan/10'
                        : 'border-wpx-border-dark bg-wpx-navy-850'
                "
                @click="toggleIntent('member')"
            >
                <span class="text-wpx-white-soft block text-sm font-black">👀 Découvrir & gagner</span>
                <span class="text-wpx-muted-dark mt-1 block text-xs">Voir les campagnes pertinentes et gagner des WP.</span>
                <span v-if="wantsMember" class="text-wpx-cyan mt-2 block text-[11px] font-bold">✓ Sélectionné</span>
            </button>

            <button
                type="button"
                class="rounded-wpx-lg border p-4 text-left transition"
                :class="
                    wantsAdvertiser
                        ? 'border-wpx-gold bg-wpx-gold/10'
                        : 'border-wpx-border-dark bg-wpx-navy-850'
                "
                @click="toggleIntent('advertiser')"
            >
                <span class="text-wpx-white-soft block text-sm font-black">📣 Promouvoir mon activité</span>
                <span class="text-wpx-muted-dark mt-1 block text-xs">
                    Découvrir le Studio Annonceur pour créer et suivre mes campagnes.
                </span>
                <span v-if="wantsAdvertiser" class="text-wpx-gold mt-2 block text-[11px] font-bold">✓ Sélectionné</span>
            </button>

            <p v-if="error" class="text-wpx-danger text-xs">{{ error }}</p>

            <button
                type="button"
                :disabled="onboardingLoading"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br py-3.5 text-sm font-black disabled:opacity-50"
                @click="continueFromIntent"
            >
                {{ onboardingLoading ? 'Préparation…' : 'Continuer' }}
            </button>
        </section>

        <section v-else-if="registrationStep === 'profile'" class="flex flex-col gap-4">
            <div>
                <p class="text-wpx-cyan text-xs font-bold">Étape 3 sur 3</p>
                <h2 class="text-wpx-white-soft mt-1 text-lg font-black">Fais de Wasplex ton Feed</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Choisis quelques centres d’intérêt. Ils restent facultatifs, privés et modifiables.
                </p>
            </div>

            <div v-if="interests.length > 0" class="flex flex-wrap gap-2">
                <button
                    v-for="interest in interests"
                    :key="interest.id"
                    type="button"
                    class="rounded-full border px-3 py-2 text-xs font-semibold transition"
                    :class="
                        selectedInterestIds.includes(interest.id)
                            ? 'border-wpx-cyan bg-wpx-cyan/15 text-wpx-cyan'
                            : 'border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft'
                    "
                    @click="toggleInterest(interest.id)"
                >
                    <span v-if="selectedInterestIds.includes(interest.id)">✓ </span>{{ interest.label }}
                </button>
            </div>
            <p v-else class="text-wpx-muted-dark rounded-wpx-md bg-wpx-navy-850 p-3 text-xs">
                Les centres d’intérêt configurés par Wasplex pourront être complétés plus tard dans ton Profil intelligent.
            </p>

            <div v-if="relevantConsents.length > 0" class="border-wpx-border-dark rounded-wpx-lg border p-3.5">
                <p class="text-wpx-white-soft text-sm font-bold">Préparer mes campagnes rémunérées</p>
                <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                    Ces autorisations sont séparées et restent révocables à tout moment dans Mon Espace.
                </p>

                <label
                    v-for="consent in relevantConsents"
                    :key="consent.code"
                    class="border-wpx-border-dark mt-3 flex cursor-pointer items-start gap-3 border-t pt-3"
                >
                    <input
                        type="checkbox"
                        class="mt-0.5"
                        :checked="selectedConsentCodes.includes(consent.code)"
                        @change="toggleConsent(consent.code)"
                    />
                    <span>
                        <span class="text-wpx-white-soft block text-xs font-semibold">{{ consent.name }}</span>
                        <span class="text-wpx-muted-dark mt-0.5 block text-[10px] leading-relaxed">
                            {{ consent.description }}
                        </span>
                    </span>
                </label>
            </div>

            <p class="text-wpx-muted-dark text-[10px]">
                {{ selectedInterestCount }} centre(s) d’intérêt sélectionné(s).
            </p>
            <p v-if="error" class="text-wpx-danger text-xs">{{ error }}</p>

            <button
                type="button"
                :disabled="onboardingLoading"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br py-3.5 text-sm font-black disabled:opacity-50"
                @click="saveProfile"
            >
                {{ onboardingLoading ? 'Enregistrement…' : 'Enregistrer et continuer' }}
            </button>
            <button type="button" class="text-wpx-muted-dark text-xs font-semibold" @click="finishWithoutProfile">
                Passer pour le moment
            </button>
        </section>

        <section v-else class="flex flex-col gap-4 text-center">
            <div class="from-wpx-blue/15 to-wpx-cyan/5 rounded-wpx-xl border-wpx-blue/25 border bg-gradient-to-br p-5">
                <p class="text-3xl">🎉</p>
                <h2 class="text-wpx-white-soft mt-2 text-lg font-black">Ton compte Wasplex est prêt.</h2>
                <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                    Ton Feed peut maintenant utiliser les préférences et autorisations que tu as choisies.
                </p>
            </div>

            <button
                type="button"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br py-3.5 text-sm font-black"
                @click="enterFeed"
            >
                Entrer dans mon Feed
            </button>

            <button
                v-if="wantsAdvertiser"
                type="button"
                class="border-wpx-gold text-wpx-gold rounded-wpx-md border py-3.5 text-sm font-black"
                @click="discoverAdvertiserSpace"
            >
                Activer mon espace annonceur
            </button>
            <p v-if="wantsAdvertiser" class="text-wpx-muted-dark text-[10px] leading-relaxed">
                Tu retrouveras la carte « Vous avez une marque ou une entreprise ? » dans Mon Espace pour créer ton
                Studio Annonceur.
            </p>
        </section>
    </div>
</template>
