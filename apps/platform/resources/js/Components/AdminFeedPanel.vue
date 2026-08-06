<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

const deliveryCounts = ref<Record<string, number>>({});
const wpDistributedMinor = ref(0);
const loading = ref(true);
const error = ref<string | null>(null);

const STATUS_LABELS: Record<string, string> = {
    reserved: 'Réservées (gain annoncé)',
    started: 'En cours de lecture',
    completed: 'Complétées (gain versé)',
    abandoned: 'Abandonnées',
    expired: 'Expirées',
    held: 'En attente de revue (antifraude)',
    rejected: 'Rejetées (aucun gain)',
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
        error.value = 'Impossible de charger le tableau de bord Feed.';
    } finally {
        loading.value = false;
    }
}

defineExpose({ load });

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Feed — attention publicitaire</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Agrégats uniquement, aucune identité de compte n'est exposée ici (même discipline que l'audit Matching).
            </p>

            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <p v-else-if="error" class="text-wpx-danger text-sm">{{ error }}</p>
            <template v-else>
                <div class="from-wpx-orange to-wpx-gold shadow-wpx-card rounded-wpx-lg mb-4 bg-gradient-to-br p-4">
                    <p class="text-wpx-navy-950/70 text-xs font-semibold tracking-wide uppercase">
                        WP distribués (gains validés)
                    </p>
                    <p class="text-wpx-navy-950 mt-1 text-2xl font-bold">
                        {{ numberFormatter.format(wpDistributedMinor) }} WP
                    </p>
                </div>

                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-wpx-text-muted border-wpx-border border-b text-xs uppercase">
                            <th class="pb-2">Statut de livraison</th>
                            <th class="pb-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(total, status) in deliveryCounts"
                            :key="status"
                            class="border-wpx-border text-wpx-text border-b last:border-0"
                        >
                            <td class="py-2">{{ STATUS_LABELS[status] ?? status }}</td>
                            <td class="py-2 text-right font-semibold">{{ numberFormatter.format(total) }}</td>
                        </tr>
                        <tr v-if="Object.keys(deliveryCounts).length === 0">
                            <td colspan="2" class="text-wpx-text-muted py-2 text-center">
                                Aucune livraison enregistrée pour le moment.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </template>
        </div>
    </div>
</template>
