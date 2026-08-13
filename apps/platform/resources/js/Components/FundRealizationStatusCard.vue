<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type Milestone = {
    id: string;
    ordinal: number;
    label: string;
    amount_minor: number;
    status: string;
    external_settlement_status: string;
};
type Order = {
    id: string;
    status: string;
    total_minor: number;
    currency: string;
    ledger_allocated_minor: number;
    externally_settled_minor: number;
    delivered_at: string | null;
    beneficiary_confirmed_at: string | null;
    completed_at: string | null;
    wish: { reference: string; title: string; category: { name: string; icon: string | null } | null };
    provider: { id: string; name: string } | null;
    milestones: Milestone[];
    open_dispute: boolean;
};

const orders = ref<Order[]>([]);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const disputeOrder = ref<Order | null>(null);
const disputeReason = ref('');

function money(value: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

function label(status: string): string {
    return ({
        issued: 'Commande transmise',
        in_progress: 'En réalisation',
        delivered: 'Livraison à confirmer',
        disputed: 'Litige en cours',
        completed: 'Réalisé',
        cancelled: 'Annulé',
    } as Record<string, string>)[status] ?? status;
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/funds/realizations');
        orders.value = data.orders ?? [];
    } catch {
        error.value = 'Impossible de charger le suivi de réalisation.';
    } finally {
        loading.value = false;
    }
}

async function confirm(order: Order): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/funds/orders/${order.id}/confirm-delivery`);
        await load();
    } catch {
        error.value = 'La confirmation de livraison n’a pas pu être enregistrée.';
    } finally {
        busy.value = false;
    }
}

async function openDispute(): Promise<void> {
    if (!disputeOrder.value || disputeReason.value.trim().length < 10) return;
    busy.value = true;
    try {
        await http.post(`/funds/orders/${disputeOrder.value.id}/disputes`, { reason: disputeReason.value });
        disputeOrder.value = null;
        disputeReason.value = '';
        await load();
    } catch {
        error.value = 'Le litige n’a pas pu être ouvert.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section v-if="loading || orders.length" class="flex flex-col gap-3">
        <div>
            <p class="text-wpx-cyan text-[10px] font-black tracking-[0.14em] uppercase">Réalisation</p>
            <h2 class="text-wpx-white-soft mt-1 text-base font-extrabold">Mes projets en cours</h2>
            <p class="text-wpx-muted-dark mt-1 text-xs">Commande, preuves, livraison et règlement sont suivis séparément.</p>
        </div>
        <p v-if="error" class="text-wpx-danger rounded-xl bg-red-500/10 px-3 py-2 text-xs">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-xs">Chargement…</p>
        <article v-for="order in orders" :key="order.id" class="bg-wpx-navy-850 border-wpx-border-dark rounded-2xl border p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">{{ order.wish.reference }}</p>
                    <h3 class="text-wpx-white-soft mt-1 text-sm font-black">{{ order.wish.title }}</h3>
                    <p class="text-wpx-muted-dark mt-1 text-[10px]">Prestataire : {{ order.provider?.name ?? '—' }}</p>
                </div>
                <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2.5 py-1 text-[9px] font-black">{{ label(order.status) }}</span>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[9px] uppercase">Coût validé</p>
                    <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ money(order.total_minor, order.currency) }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[9px] uppercase">Règlement confirmé</p>
                    <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ money(order.externally_settled_minor, order.currency) }}</p>
                </div>
            </div>
            <div v-if="order.milestones.length" class="mt-3 space-y-2">
                <div v-for="milestone in order.milestones" :key="milestone.id" class="border-wpx-border-dark flex items-center justify-between rounded-xl border px-3 py-2">
                    <div>
                        <p class="text-wpx-white-soft text-[11px] font-bold">{{ milestone.ordinal }}. {{ milestone.label }}</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-[9px]">{{ milestone.external_settlement_status === 'confirmed' ? 'Règlement confirmé' : 'Suivi en cours' }}</p>
                    </div>
                    <span class="text-wpx-gold text-[10px] font-black">{{ money(milestone.amount_minor, order.currency) }}</span>
                </div>
            </div>
            <div v-if="order.status === 'delivered' && !order.beneficiary_confirmed_at" class="mt-3 grid grid-cols-2 gap-2">
                <button class="bg-wpx-success/15 text-wpx-success-light rounded-xl px-3 py-2.5 text-[10px] font-black" :disabled="busy" @click="confirm(order)">Confirmer la livraison</button>
                <button class="bg-wpx-danger/10 text-wpx-danger rounded-xl px-3 py-2.5 text-[10px] font-black" :disabled="busy" @click="disputeOrder = order">Signaler un problème</button>
            </div>
            <p v-if="order.open_dispute" class="text-wpx-gold mt-3 text-[10px]">Un litige est ouvert : la clôture reste bloquée jusqu’à résolution.</p>
            <p v-if="order.status === 'completed'" class="text-wpx-success-light mt-3 text-xs font-bold">✓ Réalisation clôturée avec livraison et règlements confirmés.</p>
        </article>
    </section>

    <Teleport to="body">
        <div v-if="disputeOrder" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="disputeOrder = null">
            <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                <h3 class="text-wpx-white-soft text-lg font-black">Signaler un problème</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">Explique précisément ce qui ne correspond pas à la livraison attendue.</p>
                <textarea v-model="disputeReason" rows="5" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border p-3 text-sm" />
                <button class="bg-wpx-danger text-white mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40" :disabled="busy || disputeReason.trim().length < 10" @click="openDispute">Ouvrir le litige</button>
            </div>
        </div>
    </Teleport>
</template>
