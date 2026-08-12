<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import HealthProfessionalAccessRequestsPanel from '@/Components/HealthProfessionalAccessRequestsPanel.vue';

type HealthTab = 'capsule' | 'consents' | 'representatives' | 'accesses';

type Capsule = {
    blood_group: string | null;
    critical_allergies: string[];
    critical_conditions: string[];
    vital_treatments: string[];
    urgent_instructions: string | null;
    emergency_contact: { name?: string; phone?: string; relationship?: string } | null;
    reference_care: { name?: string; type?: string; phone?: string } | null;
    provenance: string;
    verified_at: string | null;
    last_reviewed_at: string | null;
};

type Representative = {
    id: string;
    name: string;
    phone: string | null;
    relationship: string;
    scope: string;
    status: string;
    proof_status: string;
};

type AccessEvent = {
    id: string;
    actor_type: string;
    access_type: string;
    purpose: string;
    institution_name: string | null;
    accessed_at: string | null;
};

const tabs: Array<{ key: HealthTab; label: string }> = [
    { key: 'capsule', label: 'Capsule' },
    { key: 'consents', label: 'Consentements' },
    { key: 'representatives', label: 'Représentants' },
    { key: 'accesses', label: 'Accès' },
];

const activeTab = ref<HealthTab>('capsule');
const loading = ref(true);
const saving = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const patientExists = ref(false);
const consentGranted = ref(false);
const representatives = ref<Representative[]>([]);
const accesses = ref<AccessEvent[]>([]);

const bloodGroup = ref('');
const allergiesText = ref('');
const conditionsText = ref('');
const treatmentsText = ref('');
const urgentInstructions = ref('');
const emergencyName = ref('');
const emergencyPhone = ref('');
const emergencyRelationship = ref('');
const referenceName = ref('');
const referenceType = ref('');
const referencePhone = ref('');
const provenance = ref('self_declared');
const verifiedAt = ref<string | null>(null);
const lastReviewedAt = ref<string | null>(null);

const representativeName = ref('');
const representativePhone = ref('');
const representativeRelationship = ref('');
const representativeScope = ref('emergency_contact');

const completion = computed(() => {
    const checks = [
        bloodGroup.value,
        allergiesText.value,
        conditionsText.value,
        treatmentsText.value,
        urgentInstructions.value,
        emergencyName.value,
        emergencyPhone.value,
    ];
    const filled = checks.filter((value) => value.trim().length > 0).length;
    return Math.round((filled / checks.length) * 100);
});

const provenanceLabel = computed(() => {
    const labels: Record<string, string> = {
        self_declared: 'Déclaré par vous',
        professional_recorded: 'Enregistré par un professionnel',
        laboratory_verified: 'Vérifié par un laboratoire',
        institution_verified: 'Vérifié par une institution',
        expired: 'À mettre à jour',
    };
    return labels[provenance.value] ?? provenance.value;
});

function splitLines(value: string): string[] {
    return value
        .split('\n')
        .map((item) => item.trim())
        .filter(Boolean)
        .slice(0, 10);
}

function joinLines(value: string[] | undefined): string {
    return (value ?? []).join('\n');
}

function hydrateCapsule(capsule: Capsule | null): void {
    if (!capsule) return;

    bloodGroup.value = capsule.blood_group ?? '';
    allergiesText.value = joinLines(capsule.critical_allergies);
    conditionsText.value = joinLines(capsule.critical_conditions);
    treatmentsText.value = joinLines(capsule.vital_treatments);
    urgentInstructions.value = capsule.urgent_instructions ?? '';
    emergencyName.value = capsule.emergency_contact?.name ?? '';
    emergencyPhone.value = capsule.emergency_contact?.phone ?? '';
    emergencyRelationship.value = capsule.emergency_contact?.relationship ?? '';
    referenceName.value = capsule.reference_care?.name ?? '';
    referenceType.value = capsule.reference_care?.type ?? '';
    referencePhone.value = capsule.reference_care?.phone ?? '';
    provenance.value = capsule.provenance;
    verifiedAt.value = capsule.verified_at;
    lastReviewedAt.value = capsule.last_reviewed_at;
}

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await http.get('/health/me');
        patientExists.value = Boolean(data.patient);
        consentGranted.value = Boolean(data.emergency_consent);
        representatives.value = data.representatives ?? [];
        accesses.value = data.accesses ?? [];
        hydrateCapsule(data.capsule ?? null);
    } catch {
        errorMessage.value = 'Impossible de charger vos informations Santé pour le moment.';
    } finally {
        loading.value = false;
    }
}

async function saveCapsule(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';

    try {
        await http.put('/health/me/capsule', {
            blood_group: bloodGroup.value || null,
            critical_allergies: splitLines(allergiesText.value),
            critical_conditions: splitLines(conditionsText.value),
            vital_treatments: splitLines(treatmentsText.value),
            urgent_instructions: urgentInstructions.value || null,
            emergency_contact:
                emergencyName.value || emergencyPhone.value || emergencyRelationship.value
                    ? {
                          name: emergencyName.value || null,
                          phone: emergencyPhone.value || null,
                          relationship: emergencyRelationship.value || null,
                      }
                    : null,
            reference_care:
                referenceName.value || referenceType.value || referencePhone.value
                    ? {
                          name: referenceName.value || null,
                          type: referenceType.value || null,
                          phone: referencePhone.value || null,
                      }
                    : null,
        });

        patientExists.value = true;
        provenance.value = 'self_declared';
        verifiedAt.value = null;
        lastReviewedAt.value = new Date().toISOString();
        successMessage.value = 'Votre capsule d’urgence a été enregistrée.';
    } catch {
        errorMessage.value = 'Impossible d’enregistrer la capsule. Vérifiez les informations puis réessayez.';
    } finally {
        saving.value = false;
    }
}

async function toggleConsent(): Promise<void> {
    const next = !consentGranted.value;
    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        const { data } = await http.put('/health/me/consents/emergency-capsule', { granted: next });
        consentGranted.value = Boolean(data.granted);
        patientExists.value = true;
        successMessage.value = next
            ? 'Accès d’urgence à votre capsule autorisé.'
            : 'Accès d’urgence à votre capsule révoqué.';
    } catch {
        errorMessage.value = 'Impossible de modifier votre consentement.';
    } finally {
        saving.value = false;
    }
}

async function addRepresentative(): Promise<void> {
    saving.value = true;
    errorMessage.value = '';
    successMessage.value = '';
    try {
        await http.post('/health/me/representatives', {
            name: representativeName.value,
            phone: representativePhone.value || null,
            relationship: representativeRelationship.value,
            scope: representativeScope.value,
        });
        representativeName.value = '';
        representativePhone.value = '';
        representativeRelationship.value = '';
        successMessage.value = 'Représentant ajouté. Il reste à vérifier avant tout pouvoir sensible.';
        await load();
        activeTab.value = 'representatives';
    } catch {
        errorMessage.value = 'Impossible d’ajouter ce représentant. Vérifiez les informations.';
    } finally {
        saving.value = false;
    }
}

async function suspendRepresentative(id: string): Promise<void> {
    saving.value = true;
    errorMessage.value = '';
    try {
        await http.post(`/health/me/representatives/${id}/suspend`);
        await load();
        activeTab.value = 'representatives';
    } catch {
        errorMessage.value = 'Impossible de suspendre ce représentant.';
    } finally {
        saving.value = false;
    }
}

function formatDate(value: string | null): string {
    if (!value) return 'Non vérifié';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <section
            class="from-wpx-cyan/16 via-wpx-blue/8 to-wpx-navy-900 border-wpx-cyan/20 rounded-wpx-xl overflow-hidden border bg-gradient-to-br p-4 shadow-xl"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="mb-2 flex items-center gap-2">
                        <span class="bg-wpx-cyan/12 flex h-10 w-10 items-center justify-center rounded-2xl text-xl"
                            >❤️‍🩹</span
                        >
                        <div>
                            <p class="text-wpx-cyan text-[9px] font-black tracking-[0.18em] uppercase">Wasplex Santé</p>
                            <p class="text-wpx-white-soft text-base font-black">Ma Santé</p>
                        </div>
                    </div>
                    <p class="text-wpx-muted-dark max-w-sm text-[11px] leading-relaxed">
                        Gardez ici seulement les informations qui peuvent vraiment aider en cas d’urgence.
                    </p>
                </div>
                <div
                    class="border-wpx-border-dark bg-wpx-navy-950/70 min-w-16 rounded-2xl border px-3 py-2 text-center"
                >
                    <p class="text-wpx-white-soft text-sm font-black">{{ completion }}%</p>
                    <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">prête</p>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-white/5 bg-black/10 p-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-wpx-white-soft text-xs font-black">Capsule d’urgence</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px]">{{ provenanceLabel }}</p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[9px] font-black"
                        :class="
                            consentGranted ? 'bg-emerald-500/12 text-emerald-300' : 'bg-amber-500/10 text-amber-300'
                        "
                    >
                        {{ consentGranted ? 'URGENCE AUTORISÉE' : 'ACCÈS FERMÉ' }}
                    </span>
                </div>
            </div>
        </section>

        <nav class="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1 pb-1" aria-label="Sections Santé">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="shrink-0 rounded-full border px-3 py-2 text-[10px] font-black transition"
                :class="
                    activeTab === tab.key
                        ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan'
                        : 'border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </nav>

        <div v-if="loading" class="text-wpx-muted-dark py-8 text-center text-xs">Chargement de Ma Santé…</div>

        <template v-else-if="activeTab === 'capsule'">
            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4 shadow-lg">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-white-soft text-sm font-black">Informations vitales</p>
                        <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                            Une information saisie par vous est affichée comme déclarée, jamais comme certifiée
                            médicalement.
                        </p>
                    </div>
                    <span class="bg-wpx-blue/10 text-wpx-blue rounded-full px-2 py-1 text-[8px] font-black">PRIVÉ</span>
                </div>

                <div class="flex flex-col gap-3">
                    <div>
                        <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Groupe sanguin</label>
                        <select
                            v-model="bloodGroup"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-3 text-xs"
                        >
                            <option value="">Je ne sais pas / non renseigné</option>
                            <option
                                v-for="group in ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']"
                                :key="group"
                                :value="group"
                            >
                                {{ group }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold"
                                >Allergies critiques</label
                            >
                            <textarea
                                v-model="allergiesText"
                                rows="3"
                                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                                placeholder="Une allergie par ligne"
                            />
                        </div>
                        <div>
                            <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold"
                                >Pathologies critiques utiles</label
                            >
                            <textarea
                                v-model="conditionsText"
                                rows="3"
                                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                                placeholder="Ex. diabète, épilepsie…"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Traitements vitaux</label>
                        <textarea
                            v-model="treatmentsText"
                            rows="3"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                            placeholder="Un traitement par ligne"
                        />
                    </div>

                    <div>
                        <label class="text-wpx-muted-dark mb-1 block text-[9px] font-bold">Instructions urgentes</label>
                        <textarea
                            v-model="urgentInstructions"
                            rows="3"
                            maxlength="1000"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                            placeholder="Seulement ce qui peut aider immédiatement un professionnel de santé"
                        />
                    </div>
                </div>
            </section>

            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <p class="text-wpx-white-soft text-sm font-black">Contact d’urgence</p>
                <p class="text-wpx-muted-dark mt-1 text-[10px]">
                    Une personne à contacter si vous ne pouvez pas répondre.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <input
                        v-model="emergencyName"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Nom"
                    />
                    <input
                        v-model="emergencyPhone"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Téléphone"
                    />
                    <input
                        v-model="emergencyRelationship"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Lien avec vous"
                    />
                </div>
            </section>

            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <p class="text-wpx-white-soft text-sm font-black">Médecin ou établissement de référence</p>
                <p class="text-wpx-muted-dark mt-1 text-[10px]">
                    Facultatif. La vérification professionnelle viendra avec P019.
                </p>
                <div class="mt-3 grid gap-2 sm:grid-cols-3">
                    <input
                        v-model="referenceName"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Nom"
                    />
                    <input
                        v-model="referenceType"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Type / spécialité"
                    />
                    <input
                        v-model="referencePhone"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Téléphone"
                    />
                </div>
            </section>

            <button
                type="button"
                class="from-wpx-cyan to-wpx-blue text-wpx-navy-950 w-full rounded-xl bg-gradient-to-r py-3 text-xs font-black shadow-lg disabled:opacity-60"
                :disabled="saving"
                @click="saveCapsule"
            >
                {{ saving ? 'Enregistrement…' : 'Enregistrer ma capsule' }}
            </button>

            <p class="text-wpx-muted-dark px-1 text-center text-[9px]">
                Dernière mise à jour : {{ lastReviewedAt ? formatDate(lastReviewedAt) : 'jamais' }} · Vérification :
                {{ verifiedAt ? formatDate(verifiedAt) : 'non vérifiée' }}
            </p>
        </template>

        <template v-else-if="activeTab === 'consents'">
            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <div class="flex items-start gap-3">
                    <span class="bg-wpx-cyan/10 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-lg"
                        >🛡️</span
                    >
                    <div>
                        <p class="text-wpx-white-soft text-sm font-black">Accès médical d’urgence</p>
                        <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                            Ce consentement autorise seulement la projection médicale minimale de votre capsule. Il ne
                            donne jamais accès à un futur dossier médical complet.
                        </p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-white/5 bg-black/10 p-3">
                    <p class="text-wpx-white-soft text-xs font-black">
                        {{ consentGranted ? 'Vous autorisez cet accès' : 'Vous n’autorisez pas cet accès' }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[9px] leading-relaxed">
                        Un futur bris de glace exigera en plus un professionnel habilité, une institution active, une
                        MFA récente, un SOS identifié, une justification, une durée courte et un audit.
                    </p>
                </div>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl border py-3 text-xs font-black"
                    :class="
                        consentGranted
                            ? 'border-red-400/30 bg-red-500/10 text-red-300'
                            : 'border-wpx-cyan/30 bg-wpx-cyan/10 text-wpx-cyan'
                    "
                    :disabled="saving"
                    @click="toggleConsent"
                >
                    {{ consentGranted ? 'Révoquer l’accès d’urgence' : 'Autoriser l’accès d’urgence' }}
                </button>
            </section>
        </template>

        <template v-else-if="activeTab === 'representatives'">
            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <p class="text-wpx-white-soft text-sm font-black">Ajouter une personne de confiance</p>
                <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                    Ajouter quelqu’un ne lui donne aucun pouvoir médical automatique. Le statut reste « à vérifier »
                    tant qu’aucune preuve n’a été validée.
                </p>

                <div class="mt-3 flex flex-col gap-2">
                    <input
                        v-model="representativeName"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        placeholder="Nom complet"
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model="representativePhone"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                            placeholder="Téléphone"
                        />
                        <input
                            v-model="representativeRelationship"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                            placeholder="Lien : parent, conjoint…"
                        />
                    </div>
                    <select
                        v-model="representativeScope"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs outline-none"
                    >
                        <option value="emergency_contact">Contact d’urgence</option>
                        <option value="legal_representation">Représentation légale à vérifier</option>
                    </select>
                    <button
                        type="button"
                        class="bg-wpx-blue/15 text-wpx-blue rounded-xl py-3 text-xs font-black disabled:opacity-60"
                        :disabled="
                            saving ||
                            representativeName.trim().length < 2 ||
                            representativeRelationship.trim().length < 2
                        "
                        @click="addRepresentative"
                    >
                        Ajouter
                    </button>
                </div>
            </section>

            <section class="flex flex-col gap-2">
                <article
                    v-for="representative in representatives"
                    :key="representative.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-white-soft text-xs font-black">{{ representative.name }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-[9px]">
                                {{ representative.relationship }} ·
                                {{ representative.phone || 'Téléphone non renseigné' }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2 py-1 text-[8px] font-black"
                            :class="
                                representative.status === 'suspended'
                                    ? 'bg-red-500/10 text-red-300'
                                    : 'bg-amber-500/10 text-amber-300'
                            "
                        >
                            {{ representative.status === 'suspended' ? 'SUSPENDU' : 'À VÉRIFIER' }}
                        </span>
                    </div>
                    <button
                        v-if="representative.status !== 'suspended'"
                        type="button"
                        class="text-wpx-muted-dark mt-3 text-[9px] font-black underline underline-offset-2"
                        :disabled="saving"
                        @click="suspendRepresentative(representative.id)"
                    >
                        Suspendre
                    </button>
                </article>

                <div
                    v-if="representatives.length === 0"
                    class="text-wpx-muted-dark rounded-xl bg-white/3 p-5 text-center text-xs"
                >
                    Aucun représentant ajouté.
                </div>
            </section>
        </template>

        <template v-else>
            <HealthProfessionalAccessRequestsPanel />

            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <div class="flex items-center gap-3">
                    <span class="bg-wpx-blue/10 flex h-10 w-10 items-center justify-center rounded-xl text-lg">👁️</span>
                    <div>
                        <p class="text-wpx-white-soft text-sm font-black">Qui a accédé à mes données ?</p>
                        <p class="text-wpx-muted-dark mt-1 text-[10px]">
                            Les futurs accès professionnels et bris de glace seront visibles ici et audités.
                        </p>
                    </div>
                </div>
            </section>

            <section class="flex flex-col gap-2">
                <article
                    v-for="access in accesses"
                    :key="access.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5"
                >
                    <p class="text-wpx-white-soft text-xs font-black">
                        {{ access.institution_name || access.actor_type }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[9px]">
                        {{ access.purpose }} · {{ access.access_type }} · {{ formatDate(access.accessed_at) }}
                    </p>
                </article>
                <div
                    v-if="accesses.length === 0"
                    class="text-wpx-muted-dark rounded-xl bg-white/3 p-5 text-center text-xs"
                >
                    Aucun accès externe enregistré. C’est normal pour cette première version citoyenne.
                </div>
            </section>
        </template>

        <p
            v-if="successMessage"
            class="rounded-xl bg-emerald-500/10 p-3 text-center text-[10px] font-semibold text-emerald-300"
        >
            {{ successMessage }}
        </p>
        <p v-if="errorMessage" class="rounded-xl bg-red-500/10 p-3 text-center text-[10px] font-semibold text-red-300">
            {{ errorMessage }}
        </p>

        <section class="border-wpx-border-dark rounded-wpx-lg border bg-black/10 p-3">
            <p class="text-wpx-muted-dark text-[9px] leading-relaxed">
                🔒 Vos données Santé ne servent ni à la publicité, ni au Wallet, ni au ciblage. Elles restent dans un
                domaine technique séparé d’Alertes.
            </p>
        </section>
    </div>
</template>
