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
    external_reference: string | null;
};
type Dispute = { id: string; status: string; reason: string; resolution_code: string | null };
type WarrantyClaim = {
    id: string;
    status: string;
    issue: string;
    provider_response: string | null;
    resolution_code: string | null;
};
type Warranty = {
    id: string;
    status: string;
    reference: string | null;
    terms: string | null;
    starts_at: string;
    ends_at: string;
    claims: WarrantyClaim[];
};
type Order = {
    id: string;
    status: string;
    total_minor: number;
    currency: string;
    authorized_reserve_minor: number;
    ledger_allocated_minor: number;
    externally_settled_minor: number;
    delivered_at: string | null;
    beneficiary_confirmed_at: string | null;
    completed_at: string | null;
    wish: { id: string; reference: string; title: string; category: { name: string; icon: string | null } | null };
    provider: { id: string; name: string } | null;
    milestones: Milestone[];
    disputes: Dispute[];
    warranty: Warranty | null;
};
type ReadyWish = {
    id: string;
    reference: string;
    title: string;
    validated_amount_minor: number;
    currency: string;
    provider: { id: string; name: string } | null;
};

const loading = ref(false);
const busy = ref(false);
const error = ref('');
const orders = ref<Order[]>([]);
const readyWishes = ref<ReadyWish[]>([]);
const reserveBalance = ref(0);
const milestoneOrder = ref<Order | null>(null);
const milestoneLabel = ref('');
const milestoneAmount = ref<number | null>(null);
const settlementMilestone = ref<{ order: Order; milestone: Milestone } | null>(null);
const settlementReference = ref('');

function money(value: number, currency = 'XOF'): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/admin/funds/realizations');
        orders.value = data.orders ?? [];
        readyWishes.value = data.ready_wishes ?? [];
        reserveBalance.value = data.reserve_balance_minor ?? 0;
    } catch {
        error.value = 'Impossible de charger les réalisations Fonds.';
    } finally {
        loading.value = false;
    }
}

async function createOrder(wish: ReadyWish): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/funds/wishes/${wish.id}/order`);
        await load();
    } catch {
        error.value = 'La commande n’a pas pu être créée.';
    } finally {
        busy.value = false;
    }
}

async function addMilestone(): Promise<void> {
    if (!milestoneOrder.value || !milestoneAmount.value || milestoneAmount.value <= 0) return;
    busy.value = true;
    try {
        await http.post(`/admin/funds/orders/${milestoneOrder.value.id}/milestones`, {
            label: milestoneLabel.value,
            amount_minor: milestoneAmount.value,
            proof_required: true,
        });
        milestoneOrder.value = null;
        milestoneLabel.value = '';
        milestoneAmount.value = null;
        await load();
    } catch {
        error.value = 'Le jalon n’a pas pu être ajouté.';
    } finally {
        busy.value = false;
    }
}

async function approve(order: Order, milestone: Milestone): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/funds/orders/${order.id}/milestones/${milestone.id}/approve`);
        await load();
    } catch {
        error.value = 'Le jalon ne peut pas être validé sans preuve conforme.';
    } finally {
        busy.value = false;
    }
}

async function ledgerPost(order: Order, milestone: Milestone): Promise<void> {
    busy.value = true;
    try {
        await http.post(`/admin/funds/orders/${order.id}/milestones/${milestone.id}/ledger-post`);
        await load();
    } catch {
        error.value = 'L’allocation Ledger a échoué. Vérifiez le financement disponible.';
    } finally {
        busy.value = false;
    }
}

async function confirmSettlement(): Promise<void> {
    if (!settlementMilestone.value || !settlementReference.value.trim()) return;
    busy.value = true;
    try {
        const { order, milestone } = settlementMilestone.value;
        await http.post(`/admin/funds/orders/${order.id}/milestones/${milestone.id}/settlement`, {
            reference: settlementReference.value,
        });
        settlementMilestone.value = null;
        settlementReference.value = '';
        await load();
    } catch {
        error.value = 'Le règlement externe n’a pas pu être confirmé.';
    } finally {
        busy.value = false;
    }
}

async function resolve(
    order: Order,
    dispute: Dispute,
    resolution: 'accept_delivery' | 'resume' | 'cancel',
): Promise<void> {
    const note = window.prompt('Note de résolution obligatoire :');
    if (!note?.trim()) return;
    busy.value = true;
    try {
        await http.post(`/admin/funds/orders/${order.id}/disputes/${dispute.id}/resolve`, { resolution, note });
        await load();
    } catch {
        error.value = 'Le litige n’a pas pu être résolu.';
    } finally {
        busy.value = false;
    }
}

async function resolveWarranty(
    order: Order,
    claim: WarrantyClaim,
    resolutionCode: 'provider_fix' | 'replacement' | 'accepted_as_is' | 'rejected' | 'closed',
): Promise<void> {
    const note = window.prompt('Note de résolution garantie obligatoire :');
    if (!note?.trim()) return;
    busy.value = true;
    try {
        await http.post(`/admin/funds/orders/${order.id}/warranty-claims/${claim.id}/resolve`, {
            resolution_code: resolutionCode,
            resolution_note: note,
        });
        await load();
    } catch {
        error.value = 'La réclamation de garantie n’a pas pu être résolue.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="flex flex-col gap-4">
        <div class="from-wpx-navy-750 to-wpx-navy-950 border-wpx-border-dark rounded-3xl border bg-gradient-to-br p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-black tracking-[0.16em] uppercase">P014-E · Réalisation</p>
                    <h3 class="text-wpx-white-soft mt-1 text-lg font-black">Commandes, preuves et livraison</h3>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-xs leading-relaxed">
                        L’allocation Ledger et le règlement externe sont deux preuves distinctes. Aucun statut « payé »
                        n’est simulé.
                    </p>
                </div>
                <div class="bg-wpx-navy-950 rounded-2xl px-4 py-3 text-right">
                    <p class="text-wpx-muted-dark text-[9px] uppercase">Réserve Fonds disponible</p>
                    <p class="text-wpx-gold mt-1 text-sm font-black">{{ money(reserveBalance) }}</p>
                </div>
            </div>
        </div>
        <p v-if="error" class="text-wpx-danger rounded-xl bg-red-500/10 px-3 py-2 text-xs">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-xs">Chargement…</p>

        <div v-if="readyWishes.length" class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-4">
            <h4 class="text-wpx-white-soft text-sm font-black">Prêts à commander</h4>
            <p class="text-wpx-muted-dark mt-1 text-[10px]">
                Uniquement les vœux dont la collecte est réellement financée.
            </p>
            <div class="mt-3 space-y-2">
                <div
                    v-for="wish in readyWishes"
                    :key="wish.id"
                    class="bg-wpx-navy-950 flex flex-wrap items-center justify-between gap-3 rounded-2xl p-3"
                >
                    <div>
                        <p class="text-wpx-cyan text-[9px] font-black">{{ wish.reference }}</p>
                        <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ wish.title }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ wish.provider?.name ?? 'Prestataire' }}</p>
                    </div>
                    <button
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-2 text-[10px] font-black"
                        :disabled="busy"
                        @click="createOrder(wish)"
                    >
                        Commander · {{ money(wish.validated_amount_minor, wish.currency) }}
                    </button>
                </div>
            </div>
        </div>

        <article
            v-for="order in orders"
            :key="order.id"
            class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-4 md:p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[9px] font-black">{{ order.wish.reference }} · {{ order.status }}</p>
                    <h4 class="text-wpx-white-soft mt-1 text-sm font-black">{{ order.wish.title }}</h4>
                    <p class="text-wpx-muted-dark mt-1 text-[10px]">{{ order.provider?.name ?? 'Prestataire' }}</p>
                </div>
                <div class="text-right">
                    <p class="text-wpx-gold text-sm font-black">{{ money(order.total_minor, order.currency) }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[9px]">
                        Ledger {{ money(order.ledger_allocated_minor, order.currency) }} · externe
                        {{ money(order.externally_settled_minor, order.currency) }}
                    </p>
                </div>
            </div>

            <div v-if="order.warranty" class="border-wpx-border-dark bg-wpx-navy-950/60 mt-4 rounded-2xl border p-3">
                <p class="text-wpx-cyan text-[9px] font-black uppercase">Garantie · {{ order.warranty.status }}</p>
                <p class="text-wpx-white-soft mt-1 text-xs font-bold">
                    {{ order.warranty.reference ?? 'Sans référence' }}
                </p>
                <div
                    v-for="claim in order.warranty.claims.filter((claim) =>
                        ['open', 'provider_responded'].includes(claim.status),
                    )"
                    :key="claim.id"
                    class="border-wpx-border-dark mt-2 rounded-xl border p-2"
                >
                    <p class="text-wpx-white-soft text-[10px] font-bold">{{ claim.issue }}</p>
                    <p v-if="claim.provider_response" class="text-wpx-muted-dark mt-1 text-[9px]">
                        Réponse prestataire : {{ claim.provider_response }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <button
                            class="bg-wpx-success/15 text-wpx-success-light rounded-lg px-2 py-1 text-[9px] font-black"
                            @click="resolveWarranty(order, claim, 'provider_fix')"
                        >
                            Correction</button
                        ><button
                            class="bg-wpx-cyan/10 text-wpx-cyan rounded-lg px-2 py-1 text-[9px] font-black"
                            @click="resolveWarranty(order, claim, 'replacement')"
                        >
                            Remplacement</button
                        ><button
                            class="bg-wpx-danger/10 text-wpx-danger rounded-lg px-2 py-1 text-[9px] font-black"
                            @click="resolveWarranty(order, claim, 'rejected')"
                        >
                            Rejeter
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-4 space-y-2">
                <div
                    v-for="milestone in order.milestones"
                    :key="milestone.id"
                    class="border-wpx-border-dark bg-wpx-navy-950/60 flex flex-wrap items-center justify-between gap-3 rounded-2xl border p-3"
                >
                    <div>
                        <p class="text-wpx-white-soft text-xs font-bold">
                            {{ milestone.ordinal }}. {{ milestone.label }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">
                            {{ milestone.status }} · {{ milestone.external_settlement_status }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-wpx-gold text-[10px] font-black">{{
                            money(milestone.amount_minor, order.currency)
                        }}</span>
                        <button
                            v-if="
                                milestone.status === 'proof_submitted' ||
                                (milestone.status === 'pending' && !milestone.proof_required)
                            "
                            class="bg-wpx-cyan/10 text-wpx-cyan rounded-lg px-2.5 py-1.5 text-[9px] font-black"
                            :disabled="busy"
                            @click="approve(order, milestone)"
                        >
                            Valider
                        </button>
                        <button
                            v-if="milestone.status === 'approved'"
                            class="bg-wpx-blue/10 text-wpx-blue rounded-lg px-2.5 py-1.5 text-[9px] font-black"
                            :disabled="busy"
                            @click="ledgerPost(order, milestone)"
                        >
                            Allouer Ledger
                        </button>
                        <button
                            v-if="milestone.status === 'ledger_posted'"
                            class="bg-wpx-success/10 text-wpx-success-light rounded-lg px-2.5 py-1.5 text-[9px] font-black"
                            :disabled="busy"
                            @click="settlementMilestone = { order, milestone }"
                        >
                            Confirmer règlement
                        </button>
                    </div>
                </div>
            </div>

            <button
                v-if="!['completed', 'cancelled'].includes(order.status)"
                class="border-wpx-border-dark text-wpx-cyan mt-3 rounded-xl border px-3 py-2 text-[10px] font-black"
                @click="milestoneOrder = order"
            >
                + Ajouter un jalon
            </button>

            <div
                v-for="dispute in order.disputes.filter((item) => item.status === 'open')"
                :key="dispute.id"
                class="mt-4 rounded-2xl bg-red-500/10 p-3"
            >
                <p class="text-wpx-danger text-xs font-black">Litige ouvert</p>
                <p class="text-wpx-muted-dark mt-1 text-[10px]">{{ dispute.reason }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button
                        class="text-wpx-success-light text-[9px] font-black"
                        @click="resolve(order, dispute, 'accept_delivery')"
                    >
                        Accepter livraison
                    </button>
                    <button class="text-wpx-cyan text-[9px] font-black" @click="resolve(order, dispute, 'resume')">
                        Reprendre réalisation
                    </button>
                    <button class="text-wpx-danger text-[9px] font-black" @click="resolve(order, dispute, 'cancel')">
                        Annuler
                    </button>
                </div>
            </div>
        </article>
    </section>

    <Teleport to="body">
        <div
            v-if="milestoneOrder"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
            @click.self="milestoneOrder = null"
        >
            <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                <h3 class="text-wpx-white-soft text-lg font-black">Ajouter un jalon</h3>
                <input
                    v-model="milestoneLabel"
                    placeholder="Ex. Acompte commande"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3 text-sm"
                />
                <input
                    v-model.number="milestoneAmount"
                    type="number"
                    min="1"
                    placeholder="Montant"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border px-3 py-3 text-sm"
                />
                <button
                    class="bg-wpx-cyan text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40"
                    :disabled="busy || !milestoneLabel.trim() || !milestoneAmount"
                    @click="addMilestone"
                >
                    Enregistrer le jalon
                </button>
            </div>
        </div>

        <div
            v-if="settlementMilestone"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
            @click.self="settlementMilestone = null"
        >
            <div class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl">
                <h3 class="text-wpx-white-soft text-lg font-black">Confirmer le règlement externe</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Saisissez la référence réelle du paiement au prestataire.
                </p>
                <input
                    v-model="settlementReference"
                    placeholder="Référence opérateur / banque"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-3 py-3 text-sm"
                />
                <button
                    class="bg-wpx-success text-wpx-navy-950 mt-3 w-full rounded-xl px-4 py-3 text-sm font-black disabled:opacity-40"
                    :disabled="busy || !settlementReference.trim()"
                    @click="confirmSettlement"
                >
                    Confirmer
                </button>
            </div>
        </div>
    </Teleport>
</template>
