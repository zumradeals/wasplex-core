<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type Obligation = {
    id: string;
    reference: string | null;
    category: { name: string; icon: string | null } | null;
    collection_status: string | null;
    participant_status: string;
    solidarity_due_minor: number;
    fee_due_minor: number;
    solidarity_paid_minor: number;
    fee_paid_minor: number;
    arrears_minor: number;
    notice_at: string | null;
    scheduled_at: string | null;
    last_attempted_at: string | null;
    arrear_grace_ends_at: string | null;
    currency: string;
};

type Data = {
    obligations: Obligation[];
    summary: { open_arrears_minor: number; pending_count: number };
};

const loading = ref(true);
const error = ref('');
const data = ref<Data | null>(null);

const visibleObligations = computed(() => data.value?.obligations.slice(0, 6) ?? []);

function money(value: number): string {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value) + ' WP';
}

function statusLabel(item: Obligation): string {
    if (item.participant_status === 'paid') return 'Réglée';
    if (item.participant_status === 'partial') return 'Partiellement réglée';
    if (item.participant_status === 'arrears') return 'À régulariser';
    if (item.collection_status === 'scheduled') return 'Préavis en cours';
    return 'Contribution prévue';
}

function statusClass(item: Obligation): string {
    if (item.participant_status === 'paid') return 'text-wpx-success-light bg-wpx-success/10';
    if (['partial', 'arrears'].includes(item.participant_status)) return 'text-wpx-gold bg-wpx-gold/10';
    return 'text-wpx-cyan bg-wpx-cyan/10';
}

function dateLabel(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/funds/collection-obligations');
        data.value = response.data;
    } catch {
        error.value = 'Impossible de charger vos contributions Fonds.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-cyan text-[10px] font-bold tracking-[0.14em] uppercase">Ma solidarité</p>
                <h2 class="text-wpx-white-soft mt-1 text-base font-extrabold">Mes contributions Fonds</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Chaque prélèvement respecte le plafond de votre mandat et est annoncé avant exécution.
                </p>
            </div>
            <button type="button" class="text-wpx-cyan shrink-0 text-xs font-bold" @click="load">Actualiser</button>
        </div>

        <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger mt-3 rounded-lg px-3 py-2 text-xs">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark mt-3 text-xs">Chargement…</p>

        <template v-if="data && !loading">
            <div
                v-if="data.summary.open_arrears_minor > 0"
                class="bg-wpx-gold/10 border-wpx-gold/20 mt-4 rounded-xl border p-3.5"
            >
                <div class="flex items-start gap-3">
                    <span class="bg-wpx-gold/15 flex h-9 w-9 shrink-0 items-center justify-center rounded-full">◷</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-wpx-gold text-sm font-extrabold">
                            {{ money(data.summary.open_arrears_minor) }} à régulariser
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                            Alimentez votre Solde Fonds. Wasplex pourra compléter les engagements déjà constitués sans
                            rendre votre Wallet négatif.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="visibleObligations.length" class="mt-4 flex flex-col gap-2.5">
                <article v-for="item in visibleObligations" :key="item.id" class="bg-wpx-navy-950 rounded-xl p-3.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">
                                {{ item.reference }} · {{ item.category?.icon }} {{ item.category?.name }}
                            </p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">Contribution solidaire</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold" :class="statusClass(item)">
                            {{ statusLabel(item) }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div>
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Solidarité</p>
                            <p class="text-wpx-white-soft mt-0.5 text-xs font-bold">
                                {{ money(item.solidarity_due_minor) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Frais</p>
                            <p class="text-wpx-white-soft mt-0.5 text-xs font-bold">{{ money(item.fee_due_minor) }}</p>
                        </div>
                        <div>
                            <p class="text-wpx-muted-dark text-[9px] uppercase">Reste</p>
                            <p
                                class="mt-0.5 text-xs font-bold"
                                :class="item.arrears_minor > 0 ? 'text-wpx-gold' : 'text-wpx-success-light'"
                            >
                                {{ money(item.arrears_minor) }}
                            </p>
                        </div>
                    </div>

                    <div class="border-wpx-border-dark mt-3 border-t pt-2.5">
                        <p v-if="item.collection_status === 'scheduled'" class="text-wpx-muted-dark text-[11px]">
                            Prélèvement prévu après le
                            <span class="text-wpx-white-soft font-semibold">{{ dateLabel(item.scheduled_at) }}</span
                            >.
                        </p>
                        <p v-else-if="item.arrears_minor > 0" class="text-wpx-muted-dark text-[11px]">
                            Délai de régularisation :
                            <span class="text-wpx-gold font-semibold">{{ dateLabel(item.arrear_grace_ends_at) }}</span
                            >.
                        </p>
                        <p v-else class="text-wpx-muted-dark text-[11px]">
                            Payé : {{ money(item.solidarity_paid_minor) }} · Frais prélevés :
                            {{ money(item.fee_paid_minor) }}
                        </p>
                    </div>
                </article>
            </div>

            <div v-else class="mt-4 rounded-xl bg-white/[0.025] p-4 text-center">
                <p class="text-wpx-white-soft text-sm font-bold">Aucune contribution prévue</p>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Vos futures contributions apparaîtront ici après leur préavis.
                </p>
            </div>
        </template>
    </section>
</template>
