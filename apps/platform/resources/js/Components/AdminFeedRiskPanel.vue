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
    heartbeat_rate_abuse: 'Visionnage inhabituel détecté',
    overclaimed_duration: 'Durée de visionnage incohérente',
    risk_signal_threshold: 'Plusieurs signaux suspects détectés',
};

const REASON_EXPLANATIONS: Record<string, string> = {
    heartbeat_rate_abuse: 'Le rythme des confirmations envoyées par le lecteur ne ressemble pas à un visionnage normal.',
    overclaimed_duration: 'Le temps déclaré comme visionné dépasse ce que Wasplex a réellement observé.',
    risk_signal_threshold: 'Plusieurs contrôles de sécurité se sont déclenchés sur ce visionnage.',
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
    const note = window.prompt('Note de vérification (optionnelle) avant de verser la récompense :') ?? undefined;
    busy.value = hold.id;
    actionError.value = null;
    try {
        await http.post(`/admin/feed/holds/${hold.id}/release`, { note });
        await load();
        emit('resolved');
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'La récompense ne peut pas être libérée.';
    } finally {
        busy.value = null;
    }
}

async function reject(hold: Hold): Promise<void> {
    const note = window.prompt('Motif du refus (optionnel) :') ?? undefined;
    busy.value = hold.id;
    actionError.value = null;
    try {
        await http.post(`/admin/feed/holds/${hold.id}/reject`, { note });
        await load();
        emit('resolved');
    } catch (e) {
        actionError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le refus ne peut pas être enregistré.';
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <section class="border-wpx-border-dark overflow-hidden rounded-wpx-xl border bg-wpx-navy-850 shadow-wpx-card-dark">
        <div class="border-wpx-border-dark flex flex-col gap-2 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-extrabold text-wpx-white-soft">Décisions antifraude</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Wasplex ne verse jamais automatiquement une récompense suspecte. Une décision humaine est requise.
                </p>
            </div>
            <span
                class="rounded-wpx-full px-3 py-1 text-xs font-extrabold"
                :class="holds.length ? 'bg-wpx-danger/15 text-wpx-danger' : 'bg-wpx-success/15 text-wpx-success'"
            >
                {{ holds.length ? `${holds.length} à examiner` : 'Aucune retenue' }}
            </span>
        </div>

        <p v-if="actionError" class="bg-wpx-danger/15 text-wpx-danger m-4 rounded-wpx-md p-3 text-xs">{{ actionError }}</p>
        <p v-if="loading" class="text-wpx-muted-dark p-5 text-sm">Chargement…</p>

        <div v-else-if="holds.length" class="divide-wpx-border-dark divide-y">
            <article v-for="hold in holds" :key="hold.id" class="p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="bg-wpx-danger/15 text-wpx-danger flex h-8 w-8 items-center justify-center rounded-wpx-md font-extrabold">!</span>
                            <h3 class="text-sm font-extrabold text-wpx-white-soft">{{ REASON_LABELS[hold.reason_code] ?? 'Visionnage à vérifier' }}</h3>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 max-w-2xl text-xs">
                            {{ REASON_EXPLANATIONS[hold.reason_code] ?? 'Un contrôle de sécurité a retenu cette récompense.' }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 text-xs">
                            <span class="bg-wpx-gold/12 text-wpx-gold rounded-wpx-full px-2.5 py-1 font-extrabold">
                                {{ numberFormatter.format(hold.amount_minor) }} WP en attente
                            </span>
                            <span class="border-wpx-border-dark text-wpx-muted-dark rounded-wpx-full border px-2.5 py-1">
                                {{ new Date(hold.opened_at).toLocaleString('fr-FR') }}
                            </span>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap gap-2">
                        <button
                            type="button"
                            class="bg-wpx-success rounded-wpx-md px-4 py-2 text-xs font-extrabold text-wpx-navy-950 disabled:opacity-50"
                            :disabled="busy === hold.id"
                            @click="release(hold)"
                        >
                            Verser les {{ numberFormatter.format(hold.amount_minor) }} WP
                        </button>
                        <button
                            type="button"
                            class="border-wpx-danger/50 text-wpx-danger rounded-wpx-md border px-4 py-2 text-xs font-extrabold disabled:opacity-50"
                            :disabled="busy === hold.id"
                            @click="reject(hold)"
                        >
                            Refuser la récompense
                        </button>
                    </div>
                </div>

                <details class="border-wpx-border-dark mt-4 rounded-wpx-md border bg-wpx-navy-950 p-3">
                    <summary class="text-wpx-muted-dark cursor-pointer text-[11px] font-extrabold">Voir les détails techniques</summary>
                    <div class="text-wpx-muted-dark mt-3 grid grid-cols-1 gap-2 font-mono text-[10px] sm:grid-cols-2">
                        <p>signaux : {{ hold.evidence?.risk_signal_count ?? '—' }}</p>
                        <p>durée visible : {{ hold.evidence?.visible_duration_ms ?? '—' }} ms</p>
                        <p>durée requise : {{ hold.evidence?.required_duration_ms ?? '—' }} ms</p>
                        <p class="break-all">campagne : {{ hold.campaign_id ?? '—' }}</p>
                    </div>
                </details>
            </article>
        </div>

        <div v-else class="p-8 text-center">
            <p class="text-wpx-success text-sm font-extrabold">✓ Rien à examiner</p>
            <p class="text-wpx-muted-dark mt-1 text-xs">Aucune récompense n’est actuellement retenue par l’antifraude.</p>
        </div>
    </section>
</template>
