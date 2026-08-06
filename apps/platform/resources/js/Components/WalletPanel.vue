<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

interface HistoryEntry {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    created_at: string | null;
}

const balance = ref<number | null>(null);
const history = ref<HistoryEntry[]>([]);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [balanceRes, historyRes] = await Promise.all([http.get('/me/wallet'), http.get('/me/wallet/history')]);
        balance.value = balanceRes.data.balance_minor;
        history.value = historyRes.data.history.data ?? historyRes.data.history;
    } finally {
        loading.value = false;
    }
}

function describe(entry: HistoryEntry): string {
    if (entry.description) {
        return entry.description;
    }

    return entry.type ?? (entry.direction === 'credit' ? 'Crédit' : 'Débit');
}

defineExpose({ load });

onMounted(load);

const page = usePage<{ auth: AuthShared }>();

// docs/07 §23 / docs/chantiers/P011-CHANTIER.md : le solde se met à jour en
// direct (déblocage antifraude, autre appareil) sans attendre un rechargement
// déclenché par la réponse HTTP de l'action qui vient de créditer.
useEcho(`wallet.${page.props.auth.account.id}`, '.wallet.balance.changed', load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="from-wpx-orange to-wpx-gold shadow-wpx-card-dark rounded-wpx-lg bg-gradient-to-br p-5">
            <p class="text-wpx-navy-950/70 text-xs font-semibold tracking-wide uppercase">Solde disponible</p>
            <p class="text-wpx-navy-950 mt-1 text-3xl font-bold">
                <span v-if="loading">…</span>
                <span v-else>{{ numberFormatter.format(balance ?? 0) }} <span class="text-lg">WP</span></span>
            </p>
            <p class="text-wpx-navy-950/70 mt-1 text-[11px]">1 WP = 1 FCFA</p>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 p-4">
            <p class="text-wpx-muted-dark mb-3 text-xs font-semibold tracking-wide uppercase">Historique</p>
            <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>
            <ul v-else class="flex flex-col gap-2">
                <li
                    v-for="entry in history"
                    :key="entry.id"
                    class="border-wpx-border-dark flex items-center justify-between border-b pb-2 last:border-0"
                >
                    <div>
                        <p class="text-wpx-white-soft text-sm">{{ describe(entry) }}</p>
                        <p class="text-wpx-muted-dark text-[11px]">
                            {{ entry.created_at ? new Date(entry.created_at).toLocaleString('fr-FR') : '' }}
                        </p>
                    </div>
                    <span
                        class="text-sm font-semibold"
                        :class="entry.direction === 'credit' ? 'text-wpx-success-light' : 'text-wpx-danger-light'"
                    >
                        {{ entry.direction === 'credit' ? '+' : '−'
                        }}{{ numberFormatter.format(entry.amount_minor) }} WP
                    </span>
                </li>
                <li v-if="history.length === 0" class="text-wpx-muted-dark text-sm">Aucun mouvement pour le moment.</li>
            </ul>
        </div>
    </div>
</template>
