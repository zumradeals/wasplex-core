<script setup lang="ts">
import { computed, ref } from 'vue';
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
const showManager = ref(false);
const loading = ref(true);
const busy = ref<string | null>(null);

const activeCount = computed(() => consents.value.filter(isActive).length);
const allActive = computed(() => consents.value.length > 0 && activeCount.value === consents.value.length);

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
    <button
        type="button"
        class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg shadow-wpx-card-dark flex w-full items-center gap-3 border p-3.5 text-left"
        @click="showManager = true"
    >
        <span class="bg-wpx-cyan/14 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
                <path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z" stroke="#2BC4DE" stroke-width="1.7" />
                <path d="M9 12l2 2 4-4" stroke="#2BC4DE" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="text-wpx-white-soft block text-sm font-bold">Mes consentements</span>
            <span v-if="loading" class="text-wpx-muted-dark mt-0.5 block text-[11px]">Chargement…</span>
            <span v-else class="text-wpx-muted-dark mt-0.5 block text-[11px]">
                {{ activeCount }}/{{ consents.length }} actifs · modifiables à tout moment
            </span>
        </span>
        <span
            class="rounded-full px-2.5 py-1 text-[10px] font-bold"
            :class="allActive ? 'bg-wpx-success/12 text-wpx-success-light' : 'bg-wpx-gold/15 text-wpx-gold'"
        >
            {{ allActive ? 'À jour' : 'À vérifier' }}
        </span>
    </button>

    <Teleport to="body">
        <div
            v-if="showManager"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 sm:items-center"
            @click.self="showManager = false"
        >
            <div
                class="bg-wpx-navy-950 border-wpx-border-dark max-h-[88vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-4 shadow-2xl sm:rounded-3xl"
            >
                <div class="bg-wpx-navy-950 sticky top-0 z-10 flex items-start justify-between gap-3 pb-4">
                    <div>
                        <p class="text-wpx-white-soft text-lg font-bold">Gérer mes consentements</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                            Vous gardez le contrôle. Chaque autorisation peut être retirée ou réactivée ici.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                        @click="showManager = false"
                    >
                        ×
                    </button>
                </div>

                <p v-if="loading" class="text-wpx-muted-dark py-6 text-center text-sm">Chargement…</p>

                <div v-else class="flex flex-col gap-3">
                    <article
                        v-for="consent in consents"
                        :key="consent.code"
                        class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg border p-3.5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-wpx-white-soft text-sm font-semibold">{{ consent.name }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">{{ consent.description }}</p>
                                <p
                                    class="mt-2 text-[11px] font-bold"
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
                    </article>

                    <p v-if="consents.length === 0" class="text-wpx-muted-dark py-4 text-center text-sm">
                        Aucune finalité de consentement publiée pour le moment.
                    </p>

                    <button
                        type="button"
                        class="text-wpx-blue-light self-start text-xs font-semibold"
                        @click="loadHistory"
                    >
                        Voir l'historique
                    </button>

                    <div v-if="showHistory" class="bg-wpx-navy-850 rounded-wpx-lg flex flex-col gap-1.5 p-3.5">
                        <p v-for="(entry, index) in history" :key="index" class="text-wpx-muted-dark text-[11px]">
                            {{ entry.purpose_code }} — {{ entry.event_type }} —
                            {{ new Date(entry.occurred_at).toLocaleString('fr-FR') }}
                        </p>
                        <p v-if="history.length === 0" class="text-wpx-muted-dark text-[11px]">
                            Aucun historique pour l'instant.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
