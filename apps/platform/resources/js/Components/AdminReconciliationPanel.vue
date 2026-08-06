<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Run {
    id: string;
    triggered_by: string | null;
    started_at: string;
    completed_at: string | null;
    total_checked: number;
    summary: Record<string, number>;
}

interface Entry {
    id: string;
    source_module: string;
    source_record_id: string;
    provider_reference: string | null;
    amount_minor: number | null;
    currency: string | null;
    internal_status: string;
    provider_status_raw: string | null;
    result_code: string;
    notes: string | null;
    created_at: string;
}

const RESULT_LABELS: Record<string, string> = {
    matched: 'Rapproché',
    partially_matched: 'Partiellement rapproché',
    unmatched: 'Introuvable chez le prestataire',
    duplicate: 'Doublon',
    amount_mismatch: 'Montant différent',
    status_mismatch: 'Statut incompatible',
    manual_review: 'Revue manuelle requise',
};

const runs = ref<Run[]>([]);
const entries = ref<Entry[]>([]);
const loading = ref(true);
const running = ref(false);
const resultFilter = ref<string>('');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [runsResponse, entriesResponse] = await Promise.all([
            http.get('/admin/reconciliation/runs'),
            http.get('/admin/reconciliation/entries', {
                params: resultFilter.value ? { result: resultFilter.value } : {},
            }),
        ]);
        runs.value = runsResponse.data.runs;
        entries.value = entriesResponse.data.entries;
    } finally {
        loading.value = false;
    }
}

async function triggerRun(): Promise<void> {
    running.value = true;
    try {
        await http.post('/admin/reconciliation/runs');
        await load();
    } finally {
        running.value = false;
    }
}

function exportUrl(): string {
    const query = resultFilter.value ? `?result=${resultFilter.value}` : '';

    return `/api/admin/reconciliation/entries/export${query}`;
}

onMounted(load);

defineExpose({ load });
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-wpx-text text-sm font-semibold">Rapprochement GeniusPay</h2>
                <button
                    type="button"
                    class="rounded-wpx-sm bg-wpx-blue px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                    :disabled="running"
                    @click="triggerRun"
                >
                    {{ running ? 'Rapprochement en cours…' : 'Lancer un rapprochement' }}
                </button>
            </div>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Revérification serveur des paiements GeniusPay (dépôts annonceur, abonnements) — aucune correction
                automatique du Grand Livre, seulement une détection.
            </p>

            <ul v-if="runs.length > 0" class="mb-4 flex flex-col gap-1 text-xs">
                <li v-for="run in runs.slice(0, 5)" :key="run.id" class="text-wpx-text-muted">
                    {{ new Date(run.started_at).toLocaleString() }} — {{ run.total_checked }} vérifié(s) —
                    <span v-for="(count, code) in run.summary" :key="code" class="mr-2">
                        {{ RESULT_LABELS[code] ?? code }}: {{ count }}
                    </span>
                </li>
            </ul>

            <div class="mb-3 flex items-center gap-2">
                <select
                    v-model="resultFilter"
                    class="rounded-wpx-sm bg-wpx-canvas text-wpx-text px-2 py-1 text-xs"
                    @change="load"
                >
                    <option value="">Tous les résultats</option>
                    <option v-for="(label, code) in RESULT_LABELS" :key="code" :value="code">{{ label }}</option>
                </select>
                <a :href="exportUrl()" class="text-wpx-blue text-xs underline" download>Exporter en CSV</a>
            </div>

            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="entry in entries" :key="entry.id" class="rounded-wpx-sm bg-wpx-canvas p-3 text-xs">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-wpx-text font-semibold">{{
                            RESULT_LABELS[entry.result_code] ?? entry.result_code
                        }}</span>
                        <span class="text-wpx-text-muted">{{ new Date(entry.created_at).toLocaleString() }}</span>
                    </div>
                    <p class="text-wpx-text-muted">
                        {{ entry.source_module }} · réf. {{ entry.provider_reference ?? '—' }} · interne
                        {{ entry.internal_status }} / prestataire {{ entry.provider_status_raw ?? '—' }}
                    </p>
                    <p v-if="entry.notes" class="text-wpx-text-muted mt-1 italic">{{ entry.notes }}</p>
                </li>
                <li v-if="!loading && entries.length === 0" class="text-wpx-text-muted text-sm">Aucune entrée.</li>
            </ul>
        </div>
    </div>
</template>
