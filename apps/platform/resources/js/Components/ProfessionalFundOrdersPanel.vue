<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type Milestone = {
    id: string;
    ordinal: number;
    label: string;
    amount_minor: number;
    status: string;
    proof_required: boolean;
    external_settlement_status: string;
};
type Order = {
    id: string;
    status: string;
    total_minor: number;
    currency: string;
    externally_settled_minor: number;
    wish: { reference: string; title: string; category: { name: string; icon: string | null } | null };
    milestones: Milestone[];
};

const props = defineProps<{ canAct: boolean }>();
const orders = ref<Order[]>([]);
const loading = ref(false);
const busy = ref(false);
const error = ref('');
const proofOrder = ref<Order | null>(null);
const proofMilestone = ref<Milestone | null>(null);
const proofReference = ref('');
const proofDescription = ref('');
const deliveryOrder = ref<Order | null>(null);
const deliveryReference = ref('');
const deliveryDescription = ref('');

function money(value: number, currency: string): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/professional/funds/orders');
        orders.value = data.orders ?? [];
    } catch {
        error.value = 'Impossible de charger les commandes Fonds.';
    } finally {
        loading.value = false;
    }
}

function openProof(order: Order, milestone: Milestone): void {
    proofOrder.value = order;
    proofMilestone.value = milestone;
    proofReference.value = '';
    proofDescription.value = '';
}

async function submitProof(): Promise<void> {
    if (!proofOrder.value || !proofMilestone.value) return;
    busy.value = true;
    try {
        await http.post(`/professional/funds/orders/${proofOrder.value.id}/proofs`, {
            milestone_id: proofMilestone.value.id,
            proof_type: 'milestone',
            reference: proofReference.value || null,
            description: proofDescription.value || null,
        });
        proofOrder.value = null;
        proofMilestone.value = null;
        await load();
    } catch {
        error.value = 'La preuve n’a pas pu être transmise.';
    } finally {
        busy.value = false;
    }
}

async function markDelivered(): Promise<void> {
    if (!deliveryOrder.value || !deliveryReference.value.trim()) return;
    busy.value = true;
    try {
        await http.post(`/professional/funds/orders/${deliveryOrder.value.id}/delivered`, {
            reference: deliveryReference.value,
            description: deliveryDescription.value || null,
        });
        deliveryOrder.value = null;
        deliveryReference.value = '';
        deliveryDescription.value = '';
        await load();
    } catch {
        error.value = 'La livraison n’a pas pu être déclarée.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="mt-4 flex flex-col gap-4">
        <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-5">
            <p class="text-wpx-cyan text-[10px] font-black tracking-[0.16em] uppercase">Réalisation Fonds</p>
            <h2 class="text-wpx-white-soft mt-1 text-lg font-black">Commandes et preuves</h2>
            <p class="text-wpx-muted-dark mt-2 text-[11px] leading-relaxed">
                Une commande n’apparaît ici qu’après sélection de votre devis et financement effectif. Le règlement externe reste distinct de l’allocation Ledger Wasplex.
            </p>
        </div>
        <p v-if="error" class="rounded-2xl bg-red-500/10 px-4 py-3 text-xs text-red-300">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-xs">Chargement…</p>
        <div v-if="!loading && orders.length === 0" class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark rounded-3xl border p-6 text-center text-xs">
            Aucune commande Fonds n’est actuellement adressée à votre organisation.
        </div>
        <article v-for="order in orders" :key="order.id" class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-4 md:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-wpx-cyan text-[9px] font-black uppercase">{{ order.wish.reference }}</p>
                    <h3 class="text-wpx-white-soft mt-1 text-sm font-black">{{ order.wish.title }}</h3>
                    <p class="text-wpx-muted-dark mt-1 text-[10px]">{{ order.wish.category?.icon }} {{ order.wish.category?.name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-wpx-gold text-sm font-black">{{ money(order.total_minor, order.currency) }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[9px]">Règlement confirmé : {{ money(order.externally_settled_minor, order.currency) }}</p>
                </div>
            </div>
            <div class="mt-4 space-y-2">
                <div v-for="milestone in order.milestones" :key="milestone.id" class="border-wpx-border-dark bg-wpx-navy-950/60 flex flex-wrap items-center justify-between gap-2 rounded-2xl border p-3">
                    <div>
                        <p class="text-wpx-white-soft text-xs font-bold">{{ milestone.ordinal }}. {{ milestone.label }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ milestone.status }} · {{ milestone.external_settlement_status === 'confirmed' ? 'règlement externe confirmé' : 'règlement à confirmer' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-wpx-gold text-[10px] font-black">{{ money(milestone.amount_minor, order.currency) }}</span>
                        <button v-if="props.canAct && ['pending', 'proof_submitted'].includes(milestone.status)" type="button" class="bg-wpx-cyan/10 text-wpx-cyan rounded-xl px-3 py-2 text-[9px] font-black" @click="openProof(order, milestone)">Preuve</button>
                    </div>
                </div>
            </div>
            <button v-if="props.canAct && !['completed', 'cancelled', 'delivered'].includes(order.status)" type="button" class="bg-wpx-success/15 text-wpx-success-light mt-4 w-full rounded-2xl px-4 py-3 text-[10px] font-black" @click="deliveryOrder = order">Déclarer la livraison</button>
        </article>
    </section>

    <Teleport to="body">
        <div v-if="proofOrder && proofMilestone" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="proofOrder = null">
            <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                <h3 class="text-wpx-white-soft text-lg font-black">Preuve du jalon</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">{{ proofMilestone.label }}</p>
                <input v-model="proofReference" placeholder="Référence facture / BL / dossier" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3 text-sm" />
                <textarea v-model="proofDescription" rows="4" placeholder="Décrivez la preuve fournie" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border p-3 text-sm" />
                <button class="bg-wpx-cyan text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black" :disabled="busy" @click="submitProof">Transmettre la preuve</button>
            </div>
        </div>

        <div v-if="deliveryOrder" class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center" @click.self="deliveryOrder = null">
            <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                <h3 class="text-wpx-white-soft text-lg font-black">Déclarer la livraison</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">Une référence de livraison est obligatoire et restera auditée.</p>
                <input v-model="deliveryReference" placeholder="Bon de livraison / référence" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3 text-sm" />
                <textarea v-model="deliveryDescription" rows="4" placeholder="Détails utiles" class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border p-3 text-sm" />
                <button class="bg-wpx-success text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40" :disabled="busy || !deliveryReference.trim()" @click="markDelivered">Confirmer la livraison</button>
            </div>
        </div>
    </Teleport>
</template>
