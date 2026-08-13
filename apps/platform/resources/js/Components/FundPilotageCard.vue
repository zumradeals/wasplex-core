<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type PilotageData = {
    reciprocity: {
        score: number;
        level: string;
        factors: Record<string, unknown>;
        calculated_at: string;
    };
    queue: Array<{
        id: string;
        wish_id: string;
        wish_title: string | null;
        lane: string;
        status: string;
        queued_at: string;
        released_at: string | null;
        blocked_reason: string | null;
    }>;
    rehabilitation: null | {
        id: string;
        status: string;
        incident_count: number;
        required_actions: string[];
        opened_at: string;
    };
    transparency: {
        realized_wishes: number;
        realized_amount_minor: number;
        solidarity_collected_minor: number;
        participant_count: number;
        reserve_balance_minor: number;
        reserve_available_minor: number;
        categories: Array<{ name: string; realized_count: number }>;
    };
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const data = ref<PilotageData | null>(null);

function money(value: number): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(value);
}

function levelLabel(level: string): string {
    return (
        {
            starting: 'En construction',
            regular: 'Régulier',
            solid: 'Solide',
            exemplary: 'Exemplaire',
        }[level] ?? level
    );
}

function queueLabel(status: string): string {
    return (
        {
            queued: 'En attente',
            released: 'Collecte préparée',
            blocked: 'À revoir',
            cancelled: 'Annulé',
        }[status] ?? status
    );
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/funds/pilotage');
        data.value = response.data.data;
    } catch {
        error.value = 'Impossible de charger le suivi Fonds.';
    } finally {
        loading.value = false;
    }
}

async function startRehabilitation(): Promise<void> {
    if (!data.value?.rehabilitation) return;
    busy.value = true;
    try {
        await http.post(`/funds/rehabilitations/${data.value.rehabilitation.id}/start`);
        await load();
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-cyan text-[10px] font-bold tracking-[0.14em] uppercase">Réciprocité & transparence</p>
                <h3 class="text-wpx-white-soft mt-1 text-base font-extrabold">Ma participation Fonds</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Cet indice reflète votre régularité. Ce n’est ni de l’argent, ni une garantie, ni une priorité achetable.
                </p>
            </div>
            <span class="bg-wpx-blue/10 text-wpx-cyan rounded-xl px-3 py-2 text-sm font-extrabold" v-if="data">
                {{ data.reciprocity.score }}/100
            </span>
        </div>

        <p v-if="loading" class="text-wpx-muted-dark mt-4 text-xs">Chargement…</p>
        <p v-if="error" class="text-wpx-danger mt-4 text-xs">{{ error }}</p>

        <template v-if="data && !loading">
            <div class="mt-4 grid grid-cols-2 gap-2.5">
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Niveau</p>
                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ levelLabel(data.reciprocity.level) }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Vœux réalisés</p>
                    <p class="text-wpx-gold mt-1 text-sm font-bold">{{ data.transparency.realized_wishes }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Participants</p>
                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ data.transparency.participant_count }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Réserve Fonds</p>
                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ money(data.transparency.reserve_balance_minor) }}</p>
                </div>
            </div>

            <div v-if="data.queue.length" class="mt-4 border-t border-white/5 pt-4">
                <p class="text-wpx-white-soft text-xs font-bold">Mes dossiers en file</p>
                <div class="mt-2 flex flex-col gap-2">
                    <div v-for="entry in data.queue" :key="entry.id" class="bg-wpx-navy-950 rounded-lg p-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-wpx-white-soft min-w-0 truncate text-xs font-semibold">{{ entry.wish_title }}</p>
                            <span class="text-wpx-cyan shrink-0 text-[10px] font-bold">{{ queueLabel(entry.status) }}</span>
                        </div>
                        <p class="text-wpx-muted-dark mt-1 text-[10px]">
                            {{ entry.lane === 'emergency' ? 'Urgence vérifiée' : 'File ordinaire' }}
                        </p>
                        <p v-if="entry.blocked_reason" class="text-wpx-gold mt-1 text-[10px]">{{ entry.blocked_reason }}</p>
                    </div>
                </div>
            </div>

            <div v-if="data.rehabilitation" class="bg-wpx-gold/10 border-wpx-gold/20 mt-4 rounded-xl border p-3">
                <p class="text-wpx-gold text-xs font-extrabold">Réhabilitation Fonds requise</p>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Des contributions anciennes doivent être régularisées avant de reprendre de nouveaux engagements Fonds.
                </p>
                <button
                    v-if="data.rehabilitation.status === 'required'"
                    type="button"
                    class="bg-wpx-gold text-wpx-navy-950 mt-3 rounded-lg px-3 py-2 text-xs font-extrabold"
                    :disabled="busy"
                    @click="startRehabilitation"
                >
                    Commencer la régularisation
                </button>
            </div>

            <p class="text-wpx-muted-dark mt-4 text-[10px] leading-relaxed">
                Les statistiques publiques restent agrégées : aucune identité de bénéficiaire, donnée médicale ou détail financier d’un autre membre n’est exposé.
            </p>
        </template>
    </section>
</template>
