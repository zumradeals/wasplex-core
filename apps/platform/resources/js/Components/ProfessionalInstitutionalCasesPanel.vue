<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

const props = defineProps<{ spaceKind: string }>();

type AlertCase = {
    id: string;
    status: string;
    institutional_reference: string | null;
    referred_at: string | null;
    acknowledged_at: string | null;
    started_at: string | null;
    resolved_at: string | null;
    alert: {
        id: string;
        category: string;
        situation: string;
        priority: string;
        title: string;
        description: string;
        city: string | null;
        area_label: string | null;
        exact_location: string | null;
        submitted_at: string | null;
    } | null;
};

type HealthRequest = {
    id: string;
    patient_account_id: string | null;
    purpose: string;
    status: string;
    requested_at: string | null;
    decided_at: string | null;
    expires_at: string | null;
    used_at: string | null;
};

const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const alertCases = ref<AlertCase[]>([]);
const healthRequests = ref<HealthRequest[]>([]);
const patientAccountId = ref('');
const justification = ref('');
const emergencyPatientAccountId = ref('');
const emergencyJustification = ref('');
const capsule = ref<Record<string, unknown> | null>(null);

const isSecurity = computed(() => props.spaceKind === 'security_institution');
const isHealth = computed(() => ['healthcare_institution', 'health_professional'].includes(props.spaceKind));
const canEmergency = computed(() => props.spaceKind === 'health_professional');

function statusLabel(status: string): string {
    return (
        {
            referred: 'Transmis',
            acknowledged: 'Reçu',
            in_progress: 'En intervention',
            resolved: 'Résolu',
            declined: 'Refusé',
            pending: 'En attente du patient',
            approved: 'Autorisé',
            denied: 'Refusé',
            expired: 'Expiré',
        }[status] ?? status
    );
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        if (isSecurity.value) {
            const { data } = await http.get('/professional/alerts');
            alertCases.value = data.cases ?? [];
        } else if (isHealth.value) {
            const { data } = await http.get('/professional/health/access-requests');
            healthRequests.value = data.requests ?? [];
        }
    } catch {
        error.value = 'Impossible de charger les dossiers autorisés pour cet espace.';
    } finally {
        loading.value = false;
    }
}

async function transition(caseId: string, action: 'acknowledge' | 'start' | 'resolve' | 'decline'): Promise<void> {
    let note: string | null = null;
    if (action === 'resolve' || action === 'decline') {
        note = window.prompt(
            action === 'resolve'
                ? 'Note de clôture : indiquez brièvement le résultat réel de l’intervention.'
                : 'Motif du refus de prise en charge :',
        );
        if (!note?.trim()) return;
    }

    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        await http.post(`/professional/alerts/${caseId}/transition`, {
            action,
            note: note?.trim() || null,
        });
        notice.value = 'Statut institutionnel enregistré et horodaté.';
        await load();
    } catch {
        error.value = 'Cette transition n’est pas autorisée ou le dossier a changé.';
    } finally {
        saving.value = false;
    }
}

async function requestHealthAccess(): Promise<void> {
    if (!patientAccountId.value.trim() || justification.value.trim().length < 10) {
        error.value = 'Renseignez l’identifiant du patient et une justification claire.';
        return;
    }

    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        await http.post('/professional/health/access-requests', {
            patient_account_id: patientAccountId.value.trim(),
            justification: justification.value.trim(),
        });
        patientAccountId.value = '';
        justification.value = '';
        notice.value = 'Demande envoyée. Le patient doit maintenant décider.';
        await load();
    } catch {
        error.value = 'Impossible d’envoyer cette demande d’accès.';
    } finally {
        saving.value = false;
    }
}

async function readApprovedCapsule(requestId: string): Promise<void> {
    saving.value = true;
    error.value = '';
    capsule.value = null;
    try {
        const { data } = await http.get(`/professional/health/access-requests/${requestId}/capsule`);
        capsule.value = data.capsule;
        notice.value = 'Accès journalisé dans l’historique du patient.';
    } catch {
        error.value = 'Cette autorisation n’est pas active ou a expiré.';
    } finally {
        saving.value = false;
    }
}

async function readEmergencyCapsule(): Promise<void> {
    if (!emergencyPatientAccountId.value.trim() || emergencyJustification.value.trim().length < 10) {
        error.value = 'L’identifiant du patient et la justification d’urgence sont obligatoires.';
        return;
    }

    saving.value = true;
    error.value = '';
    capsule.value = null;
    try {
        const { data } = await http.post('/professional/health/emergency-capsule', {
            patient_account_id: emergencyPatientAccountId.value.trim(),
            justification: emergencyJustification.value.trim(),
        });
        capsule.value = data.capsule;
        notice.value = 'Accès d’urgence autorisé par le consentement du patient et journalisé.';
    } catch {
        error.value = 'Capsule d’urgence indisponible, consentement absent ou droit insuffisant.';
    } finally {
        saving.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-3xl border p-4 md:p-5">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-white-soft text-sm font-black">
                    {{ isSecurity ? 'Dossiers Alertes transmis' : isHealth ? 'Accès Santé autorisés' : 'Dossiers' }}
                </p>
                <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                    <template v-if="isSecurity">
                        Seuls les signalements explicitement transmis à votre organisation apparaissent ici.
                    </template>
                    <template v-else-if="isHealth">
                        Aucune donnée médicale n’est ouverte automatiquement. Chaque accès normal dépend de la décision
                        du patient.
                    </template>
                    <template v-else> Aucun dossier métier n’est autorisé pour ce type d’espace. </template>
                </p>
            </div>
            <button type="button" class="text-wpx-cyan text-[10px] font-black" @click="load">Rafraîchir</button>
        </div>

        <p v-if="notice" class="mb-3 rounded-2xl bg-emerald-500/10 px-3 py-2.5 text-[10px] font-bold text-emerald-300">
            {{ notice }}
        </p>
        <p v-if="error" class="mb-3 rounded-2xl bg-red-500/10 px-3 py-2.5 text-[10px] font-bold text-red-300">
            {{ error }}
        </p>

        <div v-if="loading" class="text-wpx-muted-dark py-10 text-center text-xs">Chargement…</div>

        <template v-else-if="isSecurity">
            <div v-if="alertCases.length" class="space-y-3">
                <article
                    v-for="item in alertCases"
                    :key="item.id"
                    class="border-wpx-border-dark bg-wpx-navy-950/50 rounded-2xl border p-4"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-wpx-gold text-[9px] font-black">{{ item.alert?.priority }}</span>
                                <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2 py-1 text-[8px] font-black">
                                    {{ statusLabel(item.status) }}
                                </span>
                            </div>
                            <p class="text-wpx-white-soft mt-2 text-xs font-black">{{ item.alert?.title }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                                {{ item.alert?.description }}
                            </p>
                            <p class="text-wpx-muted-dark mt-2 text-[9px]">
                                {{
                                    [item.alert?.area_label, item.alert?.city].filter(Boolean).join(' · ') ||
                                    'Zone non précisée'
                                }}
                            </p>
                            <p
                                v-if="item.alert?.exact_location"
                                class="mt-2 rounded-xl bg-amber-400/8 px-3 py-2 text-[9px] text-amber-200"
                            >
                                Localisation privée transmise : {{ item.alert.exact_location }}
                            </p>
                            <p v-if="item.institutional_reference" class="text-wpx-muted-dark mt-2 text-[9px]">
                                Référence : {{ item.institutional_reference }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="item.status === 'referred'"
                            type="button"
                            :disabled="saving"
                            class="bg-wpx-cyan/10 text-wpx-cyan rounded-xl px-3 py-2 text-[9px] font-black disabled:opacity-50"
                            @click="transition(item.id, 'acknowledge')"
                        >
                            Accuser réception
                        </button>
                        <button
                            v-if="item.status === 'acknowledged'"
                            type="button"
                            :disabled="saving"
                            class="bg-wpx-blue/10 text-wpx-blue rounded-xl px-3 py-2 text-[9px] font-black disabled:opacity-50"
                            @click="transition(item.id, 'start')"
                        >
                            Démarrer l’intervention
                        </button>
                        <button
                            v-if="item.status === 'in_progress'"
                            type="button"
                            :disabled="saving"
                            class="rounded-xl bg-emerald-500/10 px-3 py-2 text-[9px] font-black text-emerald-300 disabled:opacity-50"
                            @click="transition(item.id, 'resolve')"
                        >
                            Clôturer comme résolu
                        </button>
                        <button
                            v-if="item.status === 'referred' || item.status === 'acknowledged'"
                            type="button"
                            :disabled="saving"
                            class="rounded-xl bg-red-500/10 px-3 py-2 text-[9px] font-black text-red-300 disabled:opacity-50"
                            @click="transition(item.id, 'decline')"
                        >
                            Refuser la prise en charge
                        </button>
                    </div>
                </article>
            </div>
            <p v-else class="text-wpx-muted-dark rounded-2xl bg-black/10 py-10 text-center text-xs">
                Aucun signalement n’a été transmis à votre organisation.
            </p>
        </template>

        <template v-else-if="isHealth">
            <form
                class="mb-4 grid gap-2 rounded-2xl bg-black/10 p-3 md:grid-cols-2"
                @submit.prevent="requestHealthAccess"
            >
                <input
                    v-model="patientAccountId"
                    required
                    placeholder="Identifiant Wasplex du patient"
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs"
                />
                <input
                    v-model="justification"
                    required
                    minlength="10"
                    placeholder="Motif de la demande"
                    class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs"
                />
                <button
                    type="submit"
                    :disabled="saving"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-3 text-xs font-black disabled:opacity-50 md:col-span-2"
                >
                    Demander l’autorisation au patient
                </button>
            </form>

            <div v-if="healthRequests.length" class="space-y-2">
                <article
                    v-for="item in healthRequests"
                    :key="item.id"
                    class="border-wpx-border-dark bg-wpx-navy-950/50 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-3"
                >
                    <div>
                        <p class="text-wpx-white-soft text-xs font-black">
                            Patient {{ item.patient_account_id?.slice(-8) || '—' }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ statusLabel(item.status) }}</p>
                    </div>
                    <button
                        v-if="item.status === 'approved'"
                        type="button"
                        :disabled="saving"
                        class="bg-wpx-cyan/10 text-wpx-cyan rounded-xl px-3 py-2 text-[9px] font-black disabled:opacity-50"
                        @click="readApprovedCapsule(item.id)"
                    >
                        Ouvrir la capsule autorisée
                    </button>
                </article>
            </div>
            <p v-else class="text-wpx-muted-dark py-6 text-center text-xs">Aucune demande d’accès pour le moment.</p>

            <div v-if="canEmergency" class="mt-5 border-t border-white/5 pt-4">
                <p class="text-wpx-white-soft text-xs font-black">Accès d’urgence</p>
                <p class="text-wpx-muted-dark mt-1 text-[9px] leading-relaxed">
                    Disponible uniquement si le patient a activé son consentement d’urgence. Chaque ouverture est
                    journalisée.
                </p>
                <form class="mt-3 grid gap-2 md:grid-cols-2" @submit.prevent="readEmergencyCapsule">
                    <input
                        v-model="emergencyPatientAccountId"
                        required
                        placeholder="Identifiant patient"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs"
                    />
                    <input
                        v-model="emergencyJustification"
                        required
                        minlength="10"
                        placeholder="Justification d’urgence"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs"
                    />
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-xl bg-red-500/15 px-3 py-3 text-xs font-black text-red-200 disabled:opacity-50 md:col-span-2"
                    >
                        Ouvrir la capsule d’urgence
                    </button>
                </form>
            </div>

            <div v-if="capsule" class="border-wpx-cyan/20 bg-wpx-cyan/5 mt-4 rounded-2xl border p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-wpx-white-soft text-xs font-black">Capsule autorisée</p>
                    <button type="button" class="text-wpx-muted-dark text-[9px] font-bold" @click="capsule = null">
                        Fermer
                    </button>
                </div>
                <pre class="text-wpx-muted-dark mt-3 overflow-x-auto text-[10px] leading-relaxed whitespace-pre-wrap">{{
                    JSON.stringify(capsule, null, 2)
                }}</pre>
            </div>
        </template>

        <p v-else class="text-wpx-muted-dark rounded-2xl bg-black/10 py-10 text-center text-xs">
            Aucun raccordement de dossiers n’est autorisé pour ce type d’espace professionnel.
        </p>
    </section>
</template>
