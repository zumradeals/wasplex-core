<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';

type ProfessionalSpace = {
    id: string;
    user_space_id: string;
    organization_id: string;
    organization_name: string | null;
    space_kind: string;
    verification_status: string;
    legal_name: string;
    trade_name: string | null;
    country_code: string;
    territory: Record<string, string> | null;
    purposes: string[];
    review_note: string | null;
    submitted_at: string | null;
    verified_at: string | null;
    space_status: string | null;
};

const kinds = [
    ['security_institution', 'Sécurité & protection', 'Police, gendarmerie, sécurité civile ou structure assimilée'],
    ['healthcare_institution', 'Établissement de Santé', 'Clinique, hôpital, centre ou établissement de soins'],
    ['health_professional', 'Professionnel de Santé', 'Professionnel exerçant dans un cadre vérifiable'],
    ['service_provider', 'Prestataire', 'Prestataire Fonds ou service professionnel'],
    ['partner', 'Partenaire', 'Partenaire commercial ou opérationnel Wasplex'],
    ['merchant', 'Commerce / point de vente', 'Commerce, réseau ou point de service'],
    ['financial_operator', 'Opérateur financier', 'Équipe financière ou opérateur autorisé'],
    ['field_agent', 'Agent de terrain', 'Activité terrain rattachée à une organisation'],
] as const;

const spaces = ref<ProfessionalSpace[]>([]);
const loading = ref(true);
const saving = ref(false);
const showForm = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

const spaceKind = ref('');
const legalName = ref('');
const tradeName = ref('');
const registrationNumber = ref('');
const countryCode = ref('CI');
const city = ref('');
const areaLabel = ref('');
const purposeText = ref('');

const hasSpaces = computed(() => spaces.value.length > 0);

function kindLabel(kind: string): string {
    return kinds.find(([key]) => key === kind)?.[1] ?? kind;
}

function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending_verification: 'Vérification en attente',
        under_review: 'En cours de vérification',
        verified: 'Vérifié',
        restricted: 'Accès restreint',
        suspended: 'Suspendu',
        rejected: 'À corriger',
    };
    return labels[status] ?? status;
}

function statusClass(status: string): string {
    if (status === 'verified') return 'bg-emerald-500/12 text-emerald-300';
    if (status === 'rejected' || status === 'suspended') return 'bg-red-500/12 text-red-300';
    if (status === 'restricted') return 'bg-amber-500/12 text-amber-300';
    return 'bg-wpx-blue/12 text-wpx-blue';
}

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await http.get('/professional-spaces');
        spaces.value = data.spaces ?? [];
    } catch {
        errorMessage.value = 'Impossible de charger vos espaces professionnels.';
    } finally {
        loading.value = false;
    }
}

async function submit(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        await http.post('/professional-spaces', {
            space_kind: spaceKind.value,
            legal_name: legalName.value,
            trade_name: tradeName.value || null,
            registration_number: registrationNumber.value || null,
            country_code: countryCode.value,
            territory: {
                country_code: countryCode.value,
                city: city.value || null,
                area_label: areaLabel.value || null,
            },
            purposes: purposeText.value
                .split('\n')
                .map((value) => value.trim())
                .filter(Boolean)
                .slice(0, 10),
        });

        showForm.value = false;
        successMessage.value = 'Votre demande a été envoyée pour vérification.';
        spaceKind.value = '';
        legalName.value = '';
        tradeName.value = '';
        registrationNumber.value = '';
        city.value = '';
        areaLabel.value = '';
        purposeText.value = '';
        await load();
    } catch {
        errorMessage.value = 'Impossible d’envoyer la demande. Vérifiez les informations puis réessayez.';
    } finally {
        saving.value = false;
    }
}

async function openSpace(space: ProfessionalSpace): Promise<void> {
    if (space.verification_status !== 'verified' || space.space_status !== 'active') return;

    try {
        await http.post(`/me/spaces/${space.user_space_id}/switch`);
        router.visit('/pro');
    } catch {
        errorMessage.value = 'Impossible d’ouvrir cet espace pour le moment.';
    }
}

onMounted(load);
</script>

<template>
    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl overflow-hidden border shadow-lg">
        <div class="p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="bg-wpx-cyan/12 flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-xl">🏛️</span>
                    <div class="min-w-0">
                        <p class="text-wpx-white-soft text-sm font-black">Espace professionnel</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                            Pour une institution, un établissement, un partenaire ou une équipe professionnelle vérifiée.
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="border-wpx-cyan/25 text-wpx-cyan shrink-0 rounded-full border px-3 py-2 text-[10px] font-black"
                    @click="showForm = !showForm"
                >
                    {{ showForm ? 'Fermer' : '+ Demander' }}
                </button>
            </div>

            <div class="mt-3 rounded-2xl border border-white/5 bg-black/10 p-3">
                <p class="text-wpx-muted-dark text-[10px] leading-relaxed">
                    Chaque agent utilise son propre compte. Wasplex vérifie l’organisation avant d’activer les accès sensibles.
                </p>
            </div>
        </div>

        <div v-if="loading" class="text-wpx-muted-dark border-wpx-border-dark border-t px-4 py-5 text-center text-xs">
            Chargement…
        </div>

        <div v-else-if="hasSpaces" class="border-wpx-border-dark flex flex-col gap-2 border-t p-3">
            <article
                v-for="space in spaces"
                :key="space.id"
                class="bg-wpx-navy-950/55 border-wpx-border-dark rounded-2xl border p-3"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-wpx-white-soft truncate text-xs font-black">
                            {{ space.trade_name || space.legal_name }}
                        </p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px]">{{ kindLabel(space.space_kind) }}</p>
                    </div>
                    <span class="rounded-full px-2 py-1 text-[8px] font-black" :class="statusClass(space.verification_status)">
                        {{ statusLabel(space.verification_status) }}
                    </span>
                </div>

                <p v-if="space.review_note" class="mt-2 rounded-xl bg-amber-500/8 px-3 py-2 text-[10px] text-amber-200">
                    {{ space.review_note }}
                </p>

                <button
                    v-if="space.verification_status === 'verified' && space.space_status === 'active'"
                    type="button"
                    class="from-wpx-cyan to-wpx-blue text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-3 py-2.5 text-[10px] font-black"
                    @click="openSpace(space)"
                >
                    Ouvrir l’espace professionnel
                </button>
                <p v-else class="text-wpx-muted-dark mt-3 text-[9px] leading-relaxed">
                    L’espace deviendra accessible après vérification. Aucun accès institutionnel sensible n’est accordé avant cette étape.
                </p>
            </article>
        </div>

        <form v-if="showForm" class="border-wpx-border-dark flex flex-col gap-3 border-t p-4" @submit.prevent="submit">
            <div>
                <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Type d’espace</label>
                <select
                    v-model="spaceKind"
                    required
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                >
                    <option value="">Choisir</option>
                    <option v-for="kind in kinds" :key="kind[0]" :value="kind[0]">{{ kind[1] }} — {{ kind[2] }}</option>
                </select>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Nom légal</label>
                    <input
                        v-model="legalName"
                        required
                        maxlength="180"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                    />
                </div>
                <div>
                    <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Nom courant</label>
                    <input
                        v-model="tradeName"
                        maxlength="180"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                    />
                </div>
            </div>

            <div>
                <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">N° d’immatriculation / référence officielle</label>
                <input
                    v-model="registrationNumber"
                    maxlength="180"
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                    placeholder="Si disponible"
                />
                <p class="text-wpx-muted-dark mt-1 text-[8px]">Cette référence reste privée et chiffrée.</p>
            </div>

            <div class="grid grid-cols-3 gap-2">
                <div>
                    <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Pays</label>
                    <input
                        v-model="countryCode"
                        maxlength="2"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs uppercase"
                    />
                </div>
                <div class="col-span-2">
                    <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Ville</label>
                    <input
                        v-model="city"
                        maxlength="100"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                    />
                </div>
            </div>

            <div>
                <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Zone / territoire</label>
                <input
                    v-model="areaLabel"
                    maxlength="160"
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                    placeholder="Ex. Abobo, Abidjan Nord, national…"
                />
            </div>

            <div>
                <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Finalités demandées</label>
                <textarea
                    v-model="purposeText"
                    rows="3"
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                    placeholder="Une finalité par ligne : traiter des signalements, accès Santé autorisés…"
                />
            </div>

            <button
                type="submit"
                :disabled="saving"
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-4 py-3 text-xs font-black disabled:opacity-50"
            >
                {{ saving ? 'Envoi…' : 'Envoyer pour vérification' }}
            </button>
        </form>

        <p v-if="successMessage" class="border-wpx-border-dark border-t px-4 py-3 text-[10px] text-emerald-300">
            {{ successMessage }}
        </p>
        <p v-if="errorMessage" class="border-wpx-border-dark border-t px-4 py-3 text-[10px] text-red-300">
            {{ errorMessage }}
        </p>
    </section>
</template>
