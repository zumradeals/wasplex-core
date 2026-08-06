<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Hold {
    id: string;
    feed_ad_delivery_id: string;
    account_id: string | null;
    campaign_id: string | null;
    amount_minor: number;
    reason_code: string;
    status: string;
    opened_at: string;
    resolved_at: string | null;
    resolution_note: string | null;
    evidence: Record<string, number>;
}

const REASON_LABELS: Record<string, string> = {
    heartbeat_rate_abuse: 'Rythme de heartbeat anormal',
    overclaimed_duration: 'Durée déclarée supérieure au temps réel',
    risk_signal_threshold: 'Seuil de signaux atteint',
};

const emit = defineEmits<{ resolved: [] }>();

const holds = ref<Hold[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);
const actionError = ref<string | null>(null);

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/feed/holds');
        holds.value = data.holds;
    } finally {
        loading.value = false;
    }
}

async function release(hold: Hold): Promise<void> {
    const note = window.prompt('Note de vérification (optionnelle) avant de créditer le gain :') ?? undefined;
    busy.value = hold.id;
    actionError.value = null;
    try {
        await http.post(`/admin/feed/holds/${hold.id}/release`, { note });
        await load();
        emit('resolved');
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Libération impossible.';
    } finally {
        busy.value = null;
    }
}

async function reject(hold: Hold): Promise<void> {
    const note = window.prompt('Motif du rejet (optionnel) :') ?? undefined;
    busy.value = hold.id;
    actionError.value = null;
    try {
        await http.post(`/admin/feed/holds/${hold.id}/reject`, { note });
        await load();
        emit('resolved');
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Rejet impossible.';
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Retenues antifraude Feed ({{ holds.length }})</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Une publicité suspecte n'est jamais créditée automatiquement : elle attend ici une décision humaine —
                libérer le gain, ou le rejeter sans aucun versement.
            </p>

            <p v-if="actionError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-sm mb-3 p-2 text-xs">
                {{ actionError }}
            </p>

            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <ul v-else class="flex flex-col gap-2">
                <li v-for="hold in holds" :key="hold.id" class="rounded-wpx-sm bg-wpx-canvas p-3 text-sm">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-wpx-text font-semibold">
                            {{ REASON_LABELS[hold.reason_code] ?? hold.reason_code }}
                        </span>
                        <span class="text-wpx-text-muted text-xs">{{ new Date(hold.opened_at).toLocaleString() }}</span>
                    </div>
                    <p class="text-wpx-text-muted mb-2 text-xs">
                        Gain suspendu : {{ numberFormatter.format(hold.amount_minor) }} WP · campagne
                        {{ hold.campaign_id ?? '—' }}
                    </p>
                    <p v-if="hold.evidence" class="text-wpx-text-muted mb-2 font-mono text-[11px]">
                        signaux : {{ hold.evidence.risk_signal_count ?? '—' }} · durée visible :
                        {{ hold.evidence.visible_duration_ms ?? '—' }} ms / requise
                        {{ hold.evidence.required_duration_ms ?? '—' }} ms
                    </p>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-wpx-sm bg-wpx-success text-wpx-navy-950 px-3 py-1.5 text-xs font-semibold disabled:opacity-50"
                            :disabled="busy === hold.id"
                            @click="release(hold)"
                        >
                            Libérer le gain
                        </button>
                        <button
                            type="button"
                            class="rounded-wpx-sm bg-wpx-danger px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                            :disabled="busy === hold.id"
                            @click="reject(hold)"
                        >
                            Rejeter sans gain
                        </button>
                    </div>
                </li>
                <li v-if="!loading && holds.length === 0" class="text-wpx-text-muted text-sm">
                    Aucune retenue en attente.
                </li>
            </ul>
        </div>
    </div>
</template>
