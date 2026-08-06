<script setup lang="ts">
import { ref } from 'vue';
import http from '@/lib/http';

interface Consent {
    code: string;
    name: string;
    description: string;
    status: string;
    granted_at: string | null;
}

interface HistoryEntry {
    purpose_code: string;
    event_type: string;
    occurred_at: string;
}

const STATUS_LABELS: Record<string, string> = {
    granted: 'Accordé',
    denied: 'Refusé',
    withdrawn: 'Retiré',
    expired: 'Expiré',
    superseded: 'À redécider',
    not_decided: 'Pas encore décidé',
};

const consents = ref<Consent[]>([]);
const history = ref<HistoryEntry[]>([]);
const showHistory = ref(false);
const loading = ref(true);
const busy = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/me/consents');
        consents.value = data.consents;
    } finally {
        loading.value = false;
    }
}

async function loadHistory(): Promise<void> {
    const { data } = await http.get('/me/consents/history');
    history.value = data.history;
    showHistory.value = true;
}

function isActive(consent: Consent): boolean {
    return consent.status === 'granted';
}

async function toggle(consent: Consent): Promise<void> {
    busy.value = consent.code;
    try {
        if (isActive(consent)) {
            await http.post(`/me/consents/${consent.code}/withdraw`);
        } else {
            await http.post(`/me/consents/${consent.code}/grant`);
        }
        await load();
    } finally {
        busy.value = null;
    }
}

void load();
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-3 p-4">
        <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Mes consentements</p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <div
            v-for="consent in consents"
            :key="consent.code"
            class="border-wpx-border-dark flex items-start justify-between gap-3 border-b pb-3 last:border-0 last:pb-0"
        >
            <div class="flex-1">
                <p class="text-wpx-white-soft text-sm font-semibold">{{ consent.name }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-xs">{{ consent.description }}</p>
                <p
                    class="mt-1 text-[11px] font-semibold"
                    :class="isActive(consent) ? 'text-wpx-success-light' : 'text-wpx-muted-dark'"
                >
                    {{ STATUS_LABELS[consent.status] ?? consent.status }}
                </p>
            </div>
            <button
                type="button"
                class="rounded-wpx-md shrink-0 px-3 py-1.5 text-xs font-semibold whitespace-nowrap disabled:opacity-50"
                :class="
                    isActive(consent)
                        ? 'border-wpx-danger text-wpx-danger-light border'
                        : 'from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br'
                "
                :disabled="busy === consent.code"
                @click="toggle(consent)"
            >
                {{ isActive(consent) ? 'Retirer' : 'Autoriser' }}
            </button>
        </div>

        <p v-if="!loading && consents.length === 0" class="text-wpx-muted-dark text-sm">
            Aucune finalité de consentement publiée pour le moment.
        </p>

        <button type="button" class="text-wpx-blue-light self-start text-xs hover:underline" @click="loadHistory">
            Voir l'historique
        </button>

        <div v-if="showHistory" class="bg-wpx-navy-750 rounded-wpx-md flex flex-col gap-1 p-3">
            <p v-for="(entry, index) in history" :key="index" class="text-wpx-muted-dark text-[11px]">
                {{ entry.purpose_code }} — {{ entry.event_type }} —
                {{ new Date(entry.occurred_at).toLocaleString('fr-FR') }}
            </p>
            <p v-if="history.length === 0" class="text-wpx-muted-dark text-[11px]">Aucun historique pour l'instant.</p>
        </div>
    </div>
</template>
