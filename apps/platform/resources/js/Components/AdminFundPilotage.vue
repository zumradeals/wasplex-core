<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type QueueCandidate = {
    id: string;
    title: string;
    validated_amount_minor: number;
    currency: string;
    membership?: { program?: { id: string; name: string } };
    category?: { name: string; icon: string | null };
};

type QueueEntry = {
    id: string;
    fund_wish_id: string;
    fund_program_id: string;
    lane: string;
    status: string;
    priority_score: number;
    blocked_reason: string | null;
    wish?: { title: string };
    program?: { id: string; name: string };
};

type Rehabilitation = {
    id: string;
    status: string;
    incident_count: number;
    account?: { display_name: string | null; phone_e164: string | null };
    membership?: { program?: { name: string } };
};

type Pilotage = {
    queue_candidates: QueueCandidate[];
    queue: QueueEntry[];
    reserve: {
        balance_minor: number;
        available_minor: number;
        allocations: Array<{
            id: string;
            amount_minor: number;
            consumed_minor: number;
            status: string;
            reason: string;
            wish?: { title: string };
        }>;
    };
    rehabilitations: Rehabilitation[];
    deficits: Array<{
        snapshot: { id: string; status: string; wish?: { title: string } };
        plan: { missing_minor: number; arrears_minor: number; recommended_actions: string[] };
    }>;
    transparency: {
        realized_wishes: number;
        realized_amount_minor: number;
        participant_count: number;
        solidarity_collected_minor: number;
    };
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const data = ref<Pilotage | null>(null);
const reserveAmounts = ref<Record<string, number>>({});

function money(value: number): string {
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(
        value,
    );
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/admin/funds/pilotage');
        data.value = response.data.data;
    } catch {
        error.value = 'Impossible de charger le pilotage avancé Fonds.';
    } finally {
        loading.value = false;
    }
}

async function queueWish(wishId: string, emergency: boolean): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/admin/funds/wishes/${wishId}/queue`, {
            lane: emergency ? 'emergency' : 'ordinary',
            emergency_verified: emergency,
        });
        await load();
    } catch {
        error.value = emergency
            ? 'Le dossier ne peut pas entrer dans la file urgence sans vérification complète.'
            : 'Le dossier ne peut pas être mis en file.';
    } finally {
        busy.value = false;
    }
}

async function releaseNext(programId: string): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/admin/funds/programs/${programId}/queue/release-next`);
        await load();
    } catch {
        error.value = 'La prochaine collecte ne peut pas être préparée pour le moment.';
    } finally {
        busy.value = false;
    }
}

async function allocateReserve(wishId: string): Promise<void> {
    const amount = reserveAmounts.value[wishId] ?? 0;
    if (amount <= 0) return;
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/admin/funds/wishes/${wishId}/reserve-allocation`, {
            amount_minor: amount,
            reason: 'Intervention autorisée de la réserve',
            justification: 'Allocation P014-F validée avant snapshot de collecte.',
        });
        reserveAmounts.value[wishId] = 0;
        await load();
    } catch {
        error.value = 'L’allocation de réserve n’a pas pu être enregistrée.';
    } finally {
        busy.value = false;
    }
}

async function detectRehabilitations(): Promise<void> {
    busy.value = true;
    try {
        await http.post('/admin/funds/rehabilitations/detect');
        await load();
    } finally {
        busy.value = false;
    }
}

async function completeRehabilitation(id: string): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/admin/funds/rehabilitations/${id}/complete`, {
            note: 'Arriérés régularisés, abonnement et mandat revérifiés.',
        });
        await load();
    } catch {
        error.value = 'La réhabilitation reste bloquée : vérifiez arriérés, abonnement, mandat et programme.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-wpx-cyan text-[10px] font-bold tracking-[0.14em] uppercase">P014-F</p>
                <h3 class="text-wpx-white-soft mt-1 text-base font-extrabold">Files, réserve & réhabilitation</h3>
                <p class="text-wpx-muted-dark mt-1 max-w-2xl text-xs leading-relaxed">
                    L’urgence vérifiée passe avant la réciprocité. La réserve est autorisée avant snapshot et les
                    suspensions Fonds restent séparées du reste de Wasplex.
                </p>
            </div>
            <button
                class="border-wpx-border-dark text-wpx-cyan rounded-lg border px-3 py-2 text-xs font-bold"
                :disabled="busy"
                @click="detectRehabilitations"
            >
                Détecter les réhabilitations
            </button>
        </div>

        <p v-if="loading" class="text-wpx-muted-dark mt-4 text-xs">Chargement…</p>
        <p v-if="error" class="text-wpx-danger mt-3 text-xs">{{ error }}</p>

        <template v-if="data && !loading">
            <div class="mt-4 grid gap-2.5 sm:grid-cols-4">
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Réserve</p>
                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ money(data.reserve.balance_minor) }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Disponible</p>
                    <p class="text-wpx-cyan mt-1 text-sm font-bold">{{ money(data.reserve.available_minor) }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Vœux réalisés</p>
                    <p class="text-wpx-gold mt-1 text-sm font-bold">{{ data.transparency.realized_wishes }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-xl p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Participants</p>
                    <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ data.transparency.participant_count }}</p>
                </div>
            </div>

            <div v-if="data.queue_candidates.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">Prêts à entrer en file</p>
                <div class="mt-2 flex flex-col gap-2">
                    <article
                        v-for="wish in data.queue_candidates"
                        :key="wish.id"
                        class="bg-wpx-navy-950 rounded-xl p-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-wpx-cyan text-[10px] font-bold uppercase">
                                    {{ wish.category?.icon }} {{ wish.category?.name }} ·
                                    {{ wish.membership?.program?.name }}
                                </p>
                                <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ wish.title }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">
                                    Coût validé : {{ money(wish.validated_amount_minor) }}
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    class="border-wpx-border-dark text-wpx-cyan rounded-lg border px-3 py-2 text-xs font-bold"
                                    :disabled="busy"
                                    @click="queueWish(wish.id, false)"
                                >
                                    File ordinaire
                                </button>
                                <button
                                    class="bg-wpx-danger/10 text-wpx-danger rounded-lg px-3 py-2 text-xs font-bold"
                                    :disabled="busy"
                                    @click="queueWish(wish.id, true)"
                                >
                                    Urgence vérifiée
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-white/5 pt-3">
                            <input
                                v-model.number="reserveAmounts[wish.id]"
                                type="number"
                                min="0"
                                placeholder="Réserve FCFA"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft rounded-lg border px-3 py-2 text-xs"
                            />
                            <button
                                class="text-wpx-gold text-xs font-bold"
                                :disabled="busy"
                                @click="allocateReserve(wish.id)"
                            >
                                Autoriser avant snapshot
                            </button>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="data.queue.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">File de financement</p>
                <div class="mt-2 flex flex-col gap-2">
                    <article v-for="entry in data.queue" :key="entry.id" class="bg-wpx-navy-950 rounded-xl p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-wpx-white-soft text-xs font-bold">{{ entry.wish?.title }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                    {{ entry.lane === 'emergency' ? 'Urgence vérifiée' : 'Ordinaire' }} ·
                                    {{ entry.program?.name }} · {{ entry.status }}
                                </p>
                                <p v-if="entry.blocked_reason" class="text-wpx-gold mt-1 text-[10px]">
                                    {{ entry.blocked_reason }}
                                </p>
                            </div>
                            <button
                                v-if="entry.status === 'queued'"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-lg bg-gradient-to-r px-3 py-2 text-xs font-extrabold"
                                :disabled="busy"
                                @click="releaseNext(entry.fund_program_id)"
                            >
                                Préparer la prochaine collecte
                            </button>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="data.deficits.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">Collectes incomplètes</p>
                <div class="mt-2 flex flex-col gap-2">
                    <article
                        v-for="item in data.deficits"
                        :key="item.snapshot.id"
                        class="bg-wpx-gold/5 border-wpx-gold/10 rounded-xl border p-3"
                    >
                        <p class="text-wpx-white-soft text-xs font-bold">{{ item.snapshot.wish?.title }}</p>
                        <p class="text-wpx-gold mt-1 text-xs">Manque : {{ money(item.plan.missing_minor) }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-[10px]">
                            Arriérés inclus : {{ money(item.plan.arrears_minor) }}
                        </p>
                        <p class="text-wpx-muted-dark mt-2 text-[10px]">
                            Options : {{ item.plan.recommended_actions.join(' · ') }}
                        </p>
                    </article>
                </div>
            </div>

            <div v-if="data.rehabilitations.length" class="mt-5">
                <p class="text-wpx-white-soft text-sm font-bold">Réhabilitations à décider</p>
                <div class="mt-2 flex flex-col gap-2">
                    <article v-for="item in data.rehabilitations" :key="item.id" class="bg-wpx-navy-950 rounded-xl p-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-wpx-white-soft text-xs font-bold">
                                    {{ item.account?.display_name || item.account?.phone_e164 || 'Membre' }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                    {{ item.membership?.program?.name }} · {{ item.incident_count }} incident(s) ·
                                    {{ item.status }}
                                </p>
                            </div>
                            <button
                                class="text-wpx-success-light text-xs font-bold"
                                :disabled="busy"
                                @click="completeRehabilitation(item.id)"
                            >
                                Vérifier et réactiver
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </template>
    </section>
</template>
