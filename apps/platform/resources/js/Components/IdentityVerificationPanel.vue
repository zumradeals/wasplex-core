<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import http from '@/lib/http';

interface VerificationResponse {
    verification: {
        status: 'not_started' | 'draft' | 'submitted' | 'approved' | 'rejected';
        document_type: string | null;
        document_country: string | null;
        has_document_front: boolean;
        has_selfie: boolean;
        rejection_reason: string | null;
        submitted_at: string | null;
        reviewed_at: string | null;
    };
    identity: {
        first_name: string | null;
        last_name: string | null;
        country_code: string | null;
    };
}

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: []; submitted: [] }>();

const data = ref<VerificationResponse | null>(null);
const loading = ref(false);
const submitting = ref(false);
const error = ref<string | null>(null);
const firstName = ref('');
const lastName = ref('');
const birthDate = ref('');
const documentType = ref('national_id');
const documentCountry = ref('CI');
const documentNumber = ref('');
const documentFront = ref<File | null>(null);
const selfie = ref<File | null>(null);

const status = computed(() => data.value?.verification.status ?? 'not_started');
const canSubmit = computed(() => ['not_started', 'draft', 'rejected'].includes(status.value));

const statusLabel = computed(() => {
    switch (status.value) {
        case 'submitted':
            return 'En cours de vérification';
        case 'approved':
            return 'Identité vérifiée';
        case 'rejected':
            return 'À corriger';
        default:
            return 'Vérification à faire';
    }
});

const statusDescription = computed(() => {
    switch (status.value) {
        case 'submitted':
            return 'Votre dossier est protégé et attend la vérification Wasplex.';
        case 'approved':
            return 'Votre identité a été validée. Vous n’avez rien à refaire.';
        case 'rejected':
            return 'Votre dossier nécessite une correction avant une nouvelle soumission.';
        default:
            return 'Confirmez votre identité pour renforcer la confiance et sécuriser les services sensibles.';
    }
});

function statusClass(): string {
    if (status.value === 'approved') return 'border-wpx-success/30 bg-wpx-success/10 text-wpx-success-light';
    if (status.value === 'rejected') return 'border-wpx-danger/30 bg-wpx-danger/10 text-wpx-danger-light';
    if (status.value === 'submitted') return 'border-wpx-blue/30 bg-wpx-blue/10 text-wpx-blue';
    return 'border-wpx-gold/30 bg-wpx-gold/10 text-wpx-gold';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const response = await http.get<VerificationResponse>('/me/identity-verification');
        data.value = response.data;
        firstName.value = response.data.identity.first_name ?? '';
        lastName.value = response.data.identity.last_name ?? '';
        documentCountry.value =
            response.data.verification.document_country ?? response.data.identity.country_code ?? 'CI';
        documentType.value = response.data.verification.document_type ?? 'national_id';
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger votre vérification.';
    } finally {
        loading.value = false;
    }
}

function selectFile(event: Event, target: 'document' | 'selfie'): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (target === 'document') documentFront.value = file;
    else selfie.value = file;
}

async function submit(): Promise<void> {
    if (!documentFront.value || !selfie.value) {
        error.value = 'Ajoutez la pièce d’identité et le selfie demandé.';
        return;
    }

    submitting.value = true;
    error.value = null;

    const payload = new FormData();
    payload.append('first_name', firstName.value.trim());
    payload.append('last_name', lastName.value.trim());
    payload.append('birth_date', birthDate.value);
    payload.append('document_type', documentType.value);
    payload.append('document_country', documentCountry.value.trim().toUpperCase());
    payload.append('document_number', documentNumber.value.trim());
    payload.append('document_front', documentFront.value);
    payload.append('selfie', selfie.value);

    try {
        const response = await http.post<VerificationResponse>('/me/identity-verification', payload);
        data.value = response.data;
        documentNumber.value = '';
        birthDate.value = '';
        documentFront.value = null;
        selfie.value = null;
        emit('submitted');
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data
                ?.message ?? 'Le dossier n’a pas pu être envoyé.';
    } finally {
        submitting.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) void load();
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
                        <p class="text-wpx-white-soft text-lg font-bold">Identité &amp; KYC</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Votre identité, protégée et vérifiable.</p>
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
                    <div v-if="loading" class="text-wpx-muted-dark py-10 text-center text-sm">Chargement…</div>

                    <template v-else>
                        <div :class="['rounded-wpx-xl border p-4', statusClass()]">
                            <div class="flex items-start gap-3">
                                <span
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-current/10"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                        <path
                                            d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                        />
                                        <path
                                            v-if="status === 'approved'"
                                            d="M9 12l2 2 4-4"
                                            stroke="currentColor"
                                            stroke-width="1.7"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <div>
                                    <p class="font-bold">{{ statusLabel }}</p>
                                    <p class="mt-1 text-xs leading-relaxed opacity-80">{{ statusDescription }}</p>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="status === 'rejected' && data?.verification.rejection_reason"
                            class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-3 text-xs"
                        >
                            <span class="font-bold">Motif :</span> {{ data.verification.rejection_reason }}
                        </div>

                        <div
                            v-if="status === 'submitted' || status === 'approved'"
                            class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4"
                        >
                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-wpx-navy-750 rounded-wpx-md p-3">
                                    <p class="text-wpx-muted-dark text-[10px] uppercase">Pièce</p>
                                    <p class="text-wpx-white-soft mt-1 text-xs font-bold">Document reçu ✓</p>
                                </div>
                                <div class="bg-wpx-navy-750 rounded-wpx-md p-3">
                                    <p class="text-wpx-muted-dark text-[10px] uppercase">Selfie</p>
                                    <p class="text-wpx-white-soft mt-1 text-xs font-bold">Photo reçue ✓</p>
                                </div>
                            </div>
                            <p v-if="data?.verification.submitted_at" class="text-wpx-muted-dark mt-3 text-[10px]">
                                Dossier transmis le
                                {{ new Date(data.verification.submitted_at).toLocaleDateString('fr-FR') }}.
                            </p>
                        </div>

                        <form v-if="canSubmit" class="flex flex-col gap-3" @submit.prevent="submit">
                            <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4">
                                <p class="text-wpx-white-soft text-sm font-bold">1. Vos informations</p>
                                <div class="mt-3 grid grid-cols-2 gap-2.5">
                                    <label class="flex flex-col gap-1.5">
                                        <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">Prénom</span>
                                        <input
                                            v-model="firstName"
                                            required
                                            maxlength="120"
                                            autocomplete="given-name"
                                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                        />
                                    </label>
                                    <label class="flex flex-col gap-1.5">
                                        <span class="text-wpx-muted-dark text-[10px] font-bold uppercase">Nom</span>
                                        <input
                                            v-model="lastName"
                                            required
                                            maxlength="120"
                                            autocomplete="family-name"
                                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                        />
                                    </label>
                                </div>
                                <label class="mt-2.5 flex flex-col gap-1.5">
                                    <span class="text-wpx-muted-dark text-[10px] font-bold uppercase"
                                        >Date de naissance</span
                                    >
                                    <input
                                        v-model="birthDate"
                                        required
                                        type="date"
                                        autocomplete="bday"
                                        class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                    />
                                </label>
                            </div>

                            <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4">
                                <p class="text-wpx-white-soft text-sm font-bold">2. Votre pièce d’identité</p>
                                <div class="mt-3 flex flex-col gap-2.5">
                                    <select
                                        v-model="documentType"
                                        class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                    >
                                        <option value="national_id">Carte nationale d’identité</option>
                                        <option value="passport">Passeport</option>
                                        <option value="drivers_license">Permis de conduire</option>
                                        <option value="residence_permit">Titre de séjour</option>
                                    </select>
                                    <div class="grid grid-cols-[90px_1fr] gap-2.5">
                                        <input
                                            v-model="documentCountry"
                                            required
                                            maxlength="2"
                                            placeholder="CI"
                                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm uppercase outline-none"
                                        />
                                        <input
                                            v-model="documentNumber"
                                            required
                                            maxlength="120"
                                            placeholder="Numéro de la pièce"
                                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2.5 text-sm outline-none"
                                        />
                                    </div>
                                    <label
                                        class="border-wpx-border-dark bg-wpx-navy-750 rounded-wpx-md border border-dashed p-3 text-center"
                                    >
                                        <span class="text-wpx-blue block text-xs font-bold">Ajouter la pièce</span>
                                        <span class="text-wpx-muted-dark mt-1 block text-[10px]"
                                            >JPG, PNG ou PDF · 8 Mo max.</span
                                        >
                                        <input
                                            class="hidden"
                                            type="file"
                                            accept="image/jpeg,image/png,application/pdf"
                                            required
                                            @change="selectFile($event, 'document')"
                                        />
                                        <span
                                            v-if="documentFront"
                                            class="text-wpx-success-light mt-2 block truncate text-[10px]"
                                            >✓ {{ documentFront.name }}</span
                                        >
                                    </label>
                                </div>
                            </div>

                            <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4">
                                <p class="text-wpx-white-soft text-sm font-bold">3. Selfie de vérification</p>
                                <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                                    Photo nette, visage découvert, bonne lumière. Elle reste privée.
                                </p>
                                <label
                                    class="border-wpx-border-dark bg-wpx-navy-750 rounded-wpx-md mt-3 block border border-dashed p-3 text-center"
                                >
                                    <span class="text-wpx-cyan block text-xs font-bold"
                                        >Prendre ou choisir un selfie</span
                                    >
                                    <span class="text-wpx-muted-dark mt-1 block text-[10px]"
                                        >JPG ou PNG · 8 Mo max.</span
                                    >
                                    <input
                                        class="hidden"
                                        type="file"
                                        accept="image/jpeg,image/png"
                                        capture="user"
                                        required
                                        @change="selectFile($event, 'selfie')"
                                    />
                                    <span v-if="selfie" class="text-wpx-success-light mt-2 block truncate text-[10px]"
                                        >✓ {{ selfie.name }}</span
                                    >
                                </label>
                            </div>

                            <div class="bg-wpx-blue/8 border-wpx-blue/20 rounded-wpx-lg border p-3">
                                <p class="text-wpx-muted-dark text-[10px] leading-relaxed">
                                    🔒 Vos documents sont stockés dans l’espace privé Wasplex et ne sont jamais affichés
                                    aux annonceurs.
                                </p>
                            </div>

                            <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs">
                                {{ error }}
                            </p>

                            <button
                                type="submit"
                                :disabled="submitting"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md bg-gradient-to-br px-4 py-3 text-sm font-bold disabled:opacity-50"
                            >
                                {{
                                    submitting
                                        ? 'Envoi sécurisé…'
                                        : status === 'rejected'
                                          ? 'Renvoyer mon dossier'
                                          : 'Envoyer pour vérification'
                                }}
                            </button>
                        </form>

                        <p
                            v-if="error && !canSubmit"
                            class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs"
                        >
                            {{ error }}
                        </p>
                    </template>
                </div>
            </section>
        </div>
    </Teleport>
</template>
