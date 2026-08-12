<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type ReadyWish = {
    id: string;
    reference: string;
    title: string;
    currency: string;
    validated_amount_minor: number;
    required_personal_contribution_minor: number;
    personal_contribution_minor: number;
    personal_contribution_complete: boolean;
    category: { name: string; icon: string | null } | null;
    provider: { name: string } | null;
    program: { name: string } | null;
};

type Snapshot = {
    id: string;
    status: string;
    snapshot_hash: string;
    collective_amount_minor: number;
    participant_count: number;
    solidarity_paid_minor: number;
    solidarity_remaining_minor: number;
    wasplex_fees_collected_minor: number;
    arrears_minor: number;
    scheduled_at: string;
    wish: { reference: string; title: string; category: { name: string; icon: string | null } | null } | null;
    program: { name: string } | null;
};

type Data = {
    ready_wishes: ReadyWish[];
    snapshots: Snapshot[];
    metrics: { scheduled: number; collecting: number; funded: number; open_arrears_minor: number };
};

const loading = ref(true);
const busyId = ref('');
const error = ref('');
const data = ref<Data | null>(null);

const activeSnapshots = computed(() =>
    data.value?.snapshots.filter((item) => ['scheduled', 'collecting', 'partially_funded', 'failed'].includes(item.status)) ?? [],
);

function money(value: number, currency = 'XOF'): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

function statusLabel(status: string): string {
    return (
        {
            scheduled: 'Préavis en cours',
            collecting: 'Collecte en cours',
            partially_funded: 'Partiellement collecté',
            funded: 'Collecte complète',
            failed: 'À régulariser',
        }[status] ?? status
    );
}

function due(snapshot: Snapshot): boolean {
    return new Date(snapshot.scheduled_at).getTime() <= Date.now();
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/admin/funds/collections');
        data.value = response.data;
    } catch {
        error.value = 'Impossible de charger les collectes Fonds.';
    } finally {
        loading.value = false;
    }
}

async function prepare(wish: ReadyWish): Promise<void> {
    busyId.value = wish.id;
    error.value = '';
    try {
        await http.post(`/admin/funds/wishes/${wish.id}/collection-snapshot`);
        await load();
    } catch (caught: unknown) {
        const response = (caught as { response?: { data?: { message?: string } } }).response;
        error.value = response?.data?.message ?? 'La collecte ne peut pas encore être préparée.';
    } finally {
        busyId.value = '';
    }
}

async function execute(snapshot: Snapshot): Promise<void> {
    busyId.value = snapshot.id;
    error.value = '';
    try {
        await http.post(`/admin/funds/collections/${snapshot.id}/execute`);
        await load();
    } catch (caught: unknown) {
        const response = (caught as { response?: { data?: { message?: string } } }).response;
        error.value = response?.data?.message ?? 'La collecte n’a pas pu être exécutée.';
    } finally {
        busyId.value = '';
    }
}

onMounted(load);
</script>

<template>
    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-wpx-cyan text-[10px] font-bold tracking-[0.16em] uppercase">Collecte automatique</p>
                <h3 class="text-wpx-white-soft mt-1 text-lg font-extrabold">Snapshots & contributions</h3>
                <p class="text-wpx-muted-dark mt-1 max-w-2xl text-xs leading-relaxed">
                    Chaque collecte est figée avant le préavis : participants, plafonds, solidarité, frais et règle de calcul restent auditables.
                </p>
            </div>
            <button type="button" class="border-wpx-border-dark text-wpx-cyan rounded-lg border px-3 py-2 text-xs font-bold" @click="load">
                Actualiser
            </button>
        </div>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger mt-3 rounded-lg px-3 py-2 text-xs">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark mt-4 text-sm">Chargement des collectes…</p>

        <template v-if="data && !loading">
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="bg-wpx-navy-950 rounded-lg p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Préavis</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-extrabold">{{ data.metrics.scheduled }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-lg p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">En cours</p>
                    <p class="text-wpx-cyan mt-1 text-lg font-extrabold">{{ data.metrics.collecting }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-lg p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Financées</p>
                    <p class="text-wpx-success-light mt-1 text-lg font-extrabold">{{ data.metrics.funded }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-lg p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">À régulariser</p>
                    <p class="text-wpx-gold mt-1 text-sm font-extrabold">{{ money(data.metrics.open_arrears_minor, 'XOF') }}</p>
                </div>
            </div>

            <div v-if="data.ready_wishes.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">Prêts à préparer</p>
                <div class="mt-2 grid gap-2 lg:grid-cols-2">
                    <article v-for="wish in data.ready_wishes" :key="wish.id" class="bg-wpx-navy-950 border-wpx-border-dark rounded-xl border p-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">{{ wish.reference }} · {{ wish.category?.icon }} {{ wish.category?.name }}</p>
                                <p class="text-wpx-white-soft mt-1 truncate text-sm font-bold">{{ wish.title }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ wish.program?.name }} · {{ wish.provider?.name }}</p>
                            </div>
                            <button
                                type="button"
                                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 shrink-0 rounded-lg bg-gradient-to-r px-3 py-2 text-[11px] font-extrabold disabled:opacity-40"
                                :disabled="busyId === wish.id || !wish.personal_contribution_complete"
                                @click="prepare(wish)"
                            >
                                {{ wish.personal_contribution_complete ? 'Préparer' : 'Apport incomplet' }}
                            </button>
                        </div>
                        <div class="mt-3 flex items-center justify-between text-xs">
                            <span class="text-wpx-muted-dark">Coût validé</span>
                            <span class="text-wpx-white-soft font-bold">{{ money(wish.validated_amount_minor, wish.currency) }}</span>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="activeSnapshots.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">Collectes actives</p>
                <div class="mt-2 flex flex-col gap-2">
                    <article v-for="snapshot in activeSnapshots" :key="snapshot.id" class="bg-wpx-navy-950 border-wpx-border-dark rounded-xl border p-3.5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">{{ snapshot.wish?.reference }} · {{ snapshot.program?.name }}</p>
                                <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ snapshot.wish?.title }}</p>
                                <p class="text-wpx-cyan mt-1 text-[11px] font-bold">{{ statusLabel(snapshot.status) }}</p>
                            </div>
                            <button
                                type="button"
                                class="border-wpx-cyan/30 text-wpx-cyan rounded-lg border px-3 py-2 text-[11px] font-bold disabled:opacity-40"
                                :disabled="busyId === snapshot.id || (!due(snapshot) && snapshot.status === 'scheduled')"
                                @click="execute(snapshot)"
                            >
                                {{ snapshot.status === 'scheduled' && !due(snapshot) ? 'Préavis en cours' : 'Exécuter / réessayer' }}
                            </button>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Collectif</p>
                                <p class="text-wpx-white-soft text-xs font-bold">{{ money(snapshot.collective_amount_minor) }}</p>
                            </div>
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Collecté</p>
                                <p class="text-wpx-success-light text-xs font-bold">{{ money(snapshot.solidarity_paid_minor) }}</p>
                            </div>
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Participants</p>
                                <p class="text-wpx-white-soft text-xs font-bold">{{ snapshot.participant_count }}</p>
                            </div>
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Arriérés</p>
                                <p class="text-wpx-gold text-xs font-bold">{{ money(snapshot.arrears_minor) }}</p>
                            </div>
                        </div>
                        <p class="text-wpx-muted-dark mt-3 truncate font-mono text-[9px]">Snapshot {{ snapshot.snapshot_hash }}</p>
                    </article>
                </div>
            </div>
        </template>
    </section>
</template>
