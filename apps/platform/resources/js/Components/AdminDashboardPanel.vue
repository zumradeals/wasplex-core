<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Summary {
    ledger: {
        total_debit_minor: number;
        total_credit_minor: number;
        is_balanced: boolean;
        transaction_count: number;
        net_by_account_type: Record<string, number>;
    };
    feed: {
        total_deliveries: number;
        completed: number;
        gain_distributed_minor: number;
        attention_rate: number;
    };
    campaigns: {
        active_campaigns: number;
        total_reserved_minor: number;
        total_captured_minor: number;
        total_released_minor: number;
    };
    subscriptions: {
        active_subscriptions: number;
        total_revenue_minor: number;
    };
}

const summary = ref<Summary | null>(null);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const percentFormatter = new Intl.NumberFormat('fr-FR', { style: 'percent', maximumFractionDigits: 1 });

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/dashboard/summary');
        summary.value = data;
    } finally {
        loading.value = false;
    }
}

onMounted(load);

defineExpose({ load });
</script>

<template>
    <div class="flex flex-col gap-4">
        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
        <template v-else-if="summary">
            <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-wpx-text text-sm font-semibold">Grand Livre</h2>
                    <span
                        class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                        :class="
                            summary.ledger.is_balanced
                                ? 'bg-wpx-success/10 text-wpx-success-light'
                                : 'bg-wpx-danger/10 text-wpx-danger-light'
                        "
                    >
                        {{ summary.ledger.is_balanced ? 'Équilibré' : 'DÉSÉQUILIBRE' }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Transactions</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.ledger.transaction_count) }}
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Total débit</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.ledger.total_debit_minor) }} WP
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Total crédit</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.ledger.total_credit_minor) }} WP
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Wallet utilisateurs en circulation</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.ledger.net_by_account_type.LIABILITY_USER ?? 0) }} WP
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Wallet annonceurs en circulation</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.ledger.net_by_account_type.LIABILITY_ADVERTISER ?? 0) }}
                            WP
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
                <h2 class="text-wpx-text mb-3 text-sm font-semibold">Campagnes</h2>
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Campagnes actives</p>
                        <p class="text-wpx-text text-lg font-semibold">{{ summary.campaigns.active_campaigns }}</p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Budget réservé</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.campaigns.total_reserved_minor) }} WP
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Budget consommé</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.campaigns.total_captured_minor) }} WP
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Budget libéré</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.campaigns.total_released_minor) }} WP
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
                <h2 class="text-wpx-text mb-3 text-sm font-semibold">Feed</h2>
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Livraisons</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.feed.total_deliveries) }}
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Attention qualifiée</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.feed.completed) }}
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Taux d'attention</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ percentFormatter.format(summary.feed.attention_rate) }}
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">WP distribués</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.feed.gain_distributed_minor) }} WP
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
                <h2 class="text-wpx-text mb-3 text-sm font-semibold">Abonnements</h2>
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Abonnements actifs</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ summary.subscriptions.active_subscriptions }}
                        </p>
                    </div>
                    <div class="rounded-wpx-sm bg-wpx-canvas p-3">
                        <p class="text-wpx-text-muted text-xs">Revenu total</p>
                        <p class="text-wpx-text text-lg font-semibold">
                            {{ numberFormatter.format(summary.subscriptions.total_revenue_minor) }} WP
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
