<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

const deliveryCounts = ref<Record<string, number>>({});
const wpDistributedMinor = ref(0);
const loading = ref(true);
const error = ref<string | null>(null);

const STATUS_LABELS: Record<string, string> = {
    reserved: 'Récompense réservée',
    started: 'Visionnage commencé',
    completed: 'Visionnage terminé et récompensé',
    abandoned: 'Visionnage abandonné',
    expired: 'Session expirée',
    held: 'Retenue par l’antifraude',
    rejected: 'Récompense refusée',
};

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/admin/feed/dashboard');
        deliveryCounts.value = data.delivery_counts;
        wpDistributedMinor.value = data.wp_distributed_minor;
    } catch {
        error.value = 'Impossible de charger les résultats du Feed.';
    } finally {
        loading.value = false;
    }
}

defineExpose({ load });
onMounted(load);
</script>

<template>
    <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-wpx-cyan text-[11px] font-extrabold uppercase tracking-[0.16em]">Attention publicitaire</p>
                <h2 class="mt-1 text-lg font-extrabold text-wpx-white-soft">Ce qui s’est réellement passé dans le Feed.</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs">Uniquement des totaux. Aucune identité d’utilisateur n’est affichée ici.</p>
            </div>
            <div class="from-wpx-orange to-wpx-gold rounded-wpx-lg bg-gradient-to-br px-4 py-3 text-wpx-navy-950">
                <p class="text-[10px] font-extrabold uppercase tracking-wide opacity-70">Récompenses validées</p>
                <p class="mt-1 text-xl font-extrabold">{{ numberFormatter.format(wpDistributedMinor) }} WP</p>
            </div>
        </div>

        <p v-if="loading" class="text-wpx-muted-dark mt-5 text-sm">Chargement…</p>
        <p v-else-if="error" class="text-wpx-danger mt-5 text-sm">{{ error }}</p>
        <div v-else class="mt-5 grid grid-cols-1 gap-2 md:grid-cols-2">
            <div
                v-for="(total, status) in deliveryCounts"
                :key="status"
                class="border-wpx-border-dark flex items-center justify-between gap-3 rounded-wpx-md border bg-wpx-navy-950 px-4 py-3"
            >
                <p class="text-wpx-muted-dark text-xs">{{ STATUS_LABELS[status] ?? status }}</p>
                <p class="text-sm font-extrabold text-wpx-white-soft">{{ numberFormatter.format(total) }}</p>
            </div>
            <p v-if="Object.keys(deliveryCounts).length === 0" class="text-wpx-muted-dark text-sm">Aucune livraison enregistrée pour le moment.</p>
        </div>
    </section>
</template>
