<script setup lang="ts">
import { ref, watch } from 'vue';
import http from '@/lib/http';

const props = defineProps<{ campaignId: string }>();

interface Report {
    budget_amount_minor: number;
    budget_reserved_minor: number;
    budget_captured_minor: number;
    budget_released_minor: number;
    feed: {
        total_deliveries: number;
        started: number;
        completed: number;
        abandoned: number;
        expired: number;
        held: number;
        gain_distributed_minor: number;
        attention_rate: number;
    };
}

const report = ref<Report | null>(null);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const percentFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 1 });

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get(`/advertiser/campaigns/${props.campaignId}/report`);
        report.value = data.report;
    } finally {
        loading.value = false;
    }
}

watch(() => props.campaignId, load, { immediate: true });

defineExpose({ load });
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
        <h3 class="text-wpx-text mb-3 text-sm font-semibold">Performance</h3>
        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
        <div v-else-if="report" class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Livraisons</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ numberFormatter.format(report.feed.total_deliveries) }}
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Attention qualifiée</p>
                <p class="text-wpx-text text-lg font-semibold">{{ numberFormatter.format(report.feed.completed) }}</p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Taux d'attention</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ percentFormatter.format(report.feed.attention_rate) }}
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Gain distribué</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ numberFormatter.format(report.feed.gain_distributed_minor) }} WP
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Budget cible</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ numberFormatter.format(report.budget_amount_minor) }} WP
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Budget réservé</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ numberFormatter.format(report.budget_reserved_minor) }} WP
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Budget consommé</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ numberFormatter.format(report.budget_captured_minor) }} WP
                </p>
            </div>
            <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                <p class="text-wpx-text-muted text-xs">Abandons / expirés / en attente</p>
                <p class="text-wpx-text text-lg font-semibold">
                    {{ report.feed.abandoned }} / {{ report.feed.expired }} / {{ report.feed.held }}
                </p>
            </div>
        </div>
    </div>
</template>
