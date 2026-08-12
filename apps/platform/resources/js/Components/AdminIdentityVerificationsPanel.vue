<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type Status = 'submitted' | 'approved' | 'rejected';

interface VerificationSummary {
    id: string;
    status: Status;
    document_type: string | null;
    document_country: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
    account: {
        id: string;
        primary_identifier: string;
        first_name: string | null;
        last_name: string | null;
        country_code: string | null;
    };
}

interface VerificationDetail extends VerificationSummary {
    birth_date: string | null;
    document_number: string | null;
    has_document_front: boolean;
    has_selfie: boolean;
}

const tabs: Array<{ key: Status; label: string }> = [
    { key: 'submitted', label: 'À vérifier' },
    { key: 'approved', label: 'Validés' },
    { key: 'rejected', label: 'Refusés' },
];

const activeStatus = ref<Status>('submitted');
const rows = ref<VerificationSummary[]>([]);
const selected = ref<VerificationDetail | null>(null);
const loading = ref(true);
const busy = ref(false);
const error = ref<string | null>(null);
const rejectionReason = ref('');
const showReject = ref(false);

const selectedName = computed(() => {
    if (!selected.value) return '';
    const full = [selected.value.account.first_name, selected.value.account.last_name].filter(Boolean).join(' ').trim();
    return full || selected.value.account.primary_identifier;
});

function documentTypeLabel(value: string | null): string {
    return (
        {
            national_id: 'Carte nationale d’identité',
            passport: 'Passeport',
            drivers_license: 'Permis de conduire',
            residence_permit: 'Titre de séjour',
        }[value ?? ''] ??
        value ??
        '—'
    );
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR', { dateStyle: 'medium' });
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get<{ verifications: VerificationSummary[] }>('/admin/identity-verifications', {
            params: { status: activeStatus.value },
        });
        rows.value = data.verifications;
        if (selected.value && !rows.value.some((row) => row.id === selected.value?.id)) selected.value = null;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger les dossiers KYC.';
    } finally {
        loading.value = false;
    }
}

async function selectVerification(id: string): Promise<void> {
    error.value = null;
    showReject.value = false;
    rejectionReason.value = '';
    try {
        const { data } = await http.get<{ verification: VerificationDetail }>(`/admin/identity-verifications/${id}`);
        selected.value = data.verification;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible d’ouvrir ce dossier.';
    }
}

async function switchStatus(status: Status): Promise<void> {
    activeStatus.value = status;
    selected.value = null;
    await load();
}

async function openDocument(kind: 'document' | 'selfie'): Promise<void> {
    if (!selected.value) return;
    error.value = null;
    try {
        const response = await http.get(`/admin/identity-verifications/${selected.value.id}/documents/${kind}`, {
            responseType: 'blob',
        });
        const url = URL.createObjectURL(response.data as Blob);
        const opened = window.open(url, '_blank', 'noopener,noreferrer');
        if (!opened) error.value = 'Autorisez l’ouverture d’un nouvel onglet pour consulter le document.';
        window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible d’ouvrir ce document.';
    }
}

async function approve(): Promise<void> {
    if (!selected.value || selected.value.status !== 'submitted') return;
    busy.value = true;
    error.value = null;
    try {
        await http.post(`/admin/identity-verifications/${selected.value.id}/approve`);
        selected.value = null;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Validation impossible.';
    } finally {
        busy.value = false;
    }
}

async function reject(): Promise<void> {
    if (!selected.value || rejectionReason.value.trim().length < 5) return;
    busy.value = true;
    error.value = null;
    try {
        await http.post(`/admin/identity-verifications/${selected.value.id}/reject`, {
            reason: rejectionReason.value.trim(),
        });
        selected.value = null;
        showReject.value = false;
        rejectionReason.value = '';
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Refus impossible.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="text-wpx-white-soft flex flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">
                        Identité &amp; KYC
                    </p>
                    <h2 class="mt-1 text-xl font-extrabold">Vérifier avec méthode, décider avec une trace.</h2>
                    <p class="text-wpx-muted-dark mt-1 max-w-2xl text-sm">
                        Les pièces restent privées. Chaque consultation et chaque décision sont protégées par le MFA
                        admin et journalisées.
                    </p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-wpx-md flex gap-1 border p-1">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="rounded-wpx-sm px-3 py-2 text-xs font-extrabold"
                        :class="
                            activeStatus === tab.key ? 'bg-wpx-white-soft text-wpx-navy-950' : 'text-wpx-muted-dark'
                        "
                        @click="switchStatus(tab.key)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger rounded-wpx-md border-wpx-danger/20 border p-3 text-xs">
            {{ error }}
        </p>

        <div class="grid min-h-[520px] grid-cols-1 gap-4 lg:grid-cols-[340px_minmax(0,1fr)]">
            <aside
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
            >
                <div class="border-wpx-border-dark flex items-center justify-between border-b px-4 py-3.5">
                    <p class="text-sm font-extrabold">{{ tabs.find((tab) => tab.key === activeStatus)?.label }}</p>
                    <span class="text-wpx-muted-dark text-xs">{{ rows.length }}</span>
                </div>
                <p v-if="loading" class="text-wpx-muted-dark p-4 text-sm">Chargement…</p>
                <div v-else class="max-h-[650px] overflow-y-auto p-2">
                    <button
                        v-for="row in rows"
                        :key="row.id"
                        type="button"
                        class="rounded-wpx-md mb-1 w-full border p-3 text-left transition"
                        :class="
                            selected?.id === row.id
                                ? 'border-wpx-cyan/40 bg-wpx-navy-750'
                                : 'hover:bg-wpx-navy-750/60 border-transparent'
                        "
                        @click="selectVerification(row.id)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-[13px] font-bold">
                                    {{
                                        [row.account.first_name, row.account.last_name].filter(Boolean).join(' ') ||
                                        row.account.primary_identifier
                                    }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 truncate text-[10px]">
                                    {{ row.account.primary_identifier }}
                                </p>
                            </div>
                            <span class="text-wpx-cyan text-[10px] font-bold">{{ row.document_country ?? '—' }}</span>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 text-[10px]">
                            {{ documentTypeLabel(row.document_type) }} · {{ formatDate(row.submitted_at) }}
                        </p>
                    </button>
                    <p v-if="rows.length === 0" class="text-wpx-muted-dark p-5 text-center text-sm">
                        Aucun dossier dans cette file.
                    </p>
                </div>
            </aside>

            <section
                v-if="selected"
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5"
            >
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div>
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">Dossier KYC</p>
                        <h3 class="mt-1 text-xl font-extrabold">{{ selectedName }}</h3>
                        <p class="text-wpx-muted-dark mt-1 text-xs">{{ selected.account.primary_identifier }}</p>
                    </div>
                    <span
                        class="rounded-wpx-full px-3 py-1.5 text-[10px] font-extrabold"
                        :class="
                            selected.status === 'approved'
                                ? 'bg-wpx-success/15 text-wpx-success'
                                : selected.status === 'rejected'
                                  ? 'bg-wpx-danger/15 text-wpx-danger'
                                  : 'bg-wpx-gold/15 text-wpx-gold'
                        "
                    >
                        {{
                            selected.status === 'approved'
                                ? 'Validé'
                                : selected.status === 'rejected'
                                  ? 'Refusé'
                                  : 'À vérifier'
                        }}
                    </span>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-wpx-md border p-3">
                        <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Date de naissance</p>
                        <p class="mt-1 text-sm font-bold">{{ selected.birth_date ?? '—' }}</p>
                    </div>
                    <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-wpx-md border p-3">
                        <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Pays de la pièce</p>
                        <p class="mt-1 text-sm font-bold">{{ selected.document_country ?? '—' }}</p>
                    </div>
                    <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-wpx-md border p-3 sm:col-span-2">
                        <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Pièce</p>
                        <p class="mt-1 text-sm font-bold">{{ documentTypeLabel(selected.document_type) }}</p>
                        <p class="text-wpx-muted-dark mt-1 font-mono text-xs">{{ selected.document_number ?? '—' }}</p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="border-wpx-blue/25 bg-wpx-blue/8 rounded-wpx-md border p-3 text-left"
                        @click="openDocument('document')"
                    >
                        <span class="text-wpx-blue block text-xs font-extrabold">Voir la pièce ↗</span>
                        <span class="text-wpx-muted-dark mt-1 block text-[10px]">Ouverture privée et journalisée</span>
                    </button>
                    <button
                        type="button"
                        class="border-wpx-cyan/25 bg-wpx-cyan/8 rounded-wpx-md border p-3 text-left"
                        @click="openDocument('selfie')"
                    >
                        <span class="text-wpx-cyan block text-xs font-extrabold">Voir le selfie ↗</span>
                        <span class="text-wpx-muted-dark mt-1 block text-[10px]">Comparer visage et document</span>
                    </button>
                </div>

                <div v-if="selected.status === 'submitted'" class="border-wpx-border-dark mt-5 border-t pt-5">
                    <div v-if="!showReject" class="flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            :disabled="busy"
                            class="bg-wpx-success text-wpx-navy-950 rounded-wpx-md flex-1 px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                            @click="approve"
                        >
                            {{ busy ? 'Traitement…' : 'Valider l’identité' }}
                        </button>
                        <button
                            type="button"
                            :disabled="busy"
                            class="border-wpx-danger/40 text-wpx-danger rounded-wpx-md flex-1 border px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                            @click="showReject = true"
                        >
                            Refuser / demander correction
                        </button>
                    </div>
                    <div v-else class="border-wpx-danger/20 bg-wpx-danger/5 rounded-wpx-lg border p-4">
                        <p class="text-sm font-extrabold">Pourquoi ce dossier doit-il être corrigé ?</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Le membre verra ce motif dans Mon Espace.</p>
                        <textarea
                            v-model="rejectionReason"
                            rows="4"
                            maxlength="1000"
                            placeholder="Ex. La photo de la pièce est trop floue…"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft rounded-wpx-md mt-3 w-full border p-3 text-sm outline-none"
                        />
                        <div class="mt-3 flex gap-2">
                            <button
                                type="button"
                                class="border-wpx-border-dark text-wpx-muted-dark rounded-wpx-md border px-4 py-2.5 text-xs font-bold"
                                @click="showReject = false"
                            >
                                Annuler
                            </button>
                            <button
                                type="button"
                                :disabled="busy || rejectionReason.trim().length < 5"
                                class="bg-wpx-danger text-wpx-white-soft rounded-wpx-md flex-1 px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                                @click="reject"
                            >
                                {{ busy ? 'Envoi…' : 'Refuser avec ce motif' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-else-if="selected.status === 'rejected' && selected.rejection_reason"
                    class="bg-wpx-danger/8 text-wpx-danger rounded-wpx-md mt-5 p-3 text-xs"
                >
                    <span class="font-extrabold">Motif :</span> {{ selected.rejection_reason }}
                </div>
            </section>

            <section
                v-else
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 text-wpx-muted-dark flex min-h-[360px] items-center justify-center border p-8 text-center text-sm"
            >
                Sélectionnez un dossier pour afficher les informations et les documents privés.
            </section>
        </div>
    </div>
</template>
