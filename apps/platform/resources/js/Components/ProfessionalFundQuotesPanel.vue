<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type Quote = {
    id: string;
    status: string;
    amount_minor: number;
    currency: string;
    lead_time_days: number | null;
    valid_until: string | null;
    terms: string | null;
    notes: string | null;
    submitted_at: string;
};

type QuoteRequest = {
    id: string;
    status: string;
    request_summary: string | null;
    requirements: Record<string, unknown> | null;
    requested_at: string;
    expires_at: string | null;
    wish: {
        reference: string;
        category: { name: string; icon: string | null } | null;
        title: string;
        description: string;
        country_code: string;
        city: string | null;
        estimated_amount_minor: number | null;
        currency: string;
    };
    quote: Quote | null;
};

defineProps<{ canRespond: boolean }>();
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const requests = ref<QuoteRequest[]>([]);
const editing = ref<QuoteRequest | null>(null);
const form = ref({
    amount_minor: null as number | null,
    lead_time_days: null as number | null,
    valid_until: '',
    terms: '',
    notes: '',
});

const openRequests = computed(() =>
    requests.value.filter((item) => item.status === 'requested' || item.status === 'responded'),
);

function money(value: number | null, currency = 'XOF'): string {
    if (value === null) return 'À préciser';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

function statusLabel(status: string): string {
    return (
        (
            {
                requested: 'À traiter',
                responded: 'Devis envoyé',
                declined: 'Décliné',
                expired: 'Expiré',
                selected: 'Sélectionné',
                not_selected: 'Non retenu',
            } as Record<string, string>
        )[status] ?? status
    );
}

function statusClass(status: string): string {
    if (status === 'selected') return 'bg-emerald-500/12 text-emerald-300';
    if (status === 'requested') return 'bg-wpx-gold/10 text-wpx-gold';
    if (status === 'responded') return 'bg-wpx-cyan/10 text-wpx-cyan';
    return 'bg-white/5 text-wpx-muted-dark';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/professional/funds/quote-requests');
        requests.value = response.data.quote_requests;
    } catch {
        error.value = 'Les demandes Fonds ne sont pas disponibles pour ce rôle.';
    } finally {
        loading.value = false;
    }
}

function openQuote(item: QuoteRequest): void {
    editing.value = item;
    form.value = {
        amount_minor: item.quote?.amount_minor ?? item.wish.estimated_amount_minor,
        lead_time_days: item.quote?.lead_time_days ?? null,
        valid_until: item.quote?.valid_until?.slice(0, 10) ?? '',
        terms: item.quote?.terms ?? '',
        notes: item.quote?.notes ?? '',
    };
}

async function submitQuote(): Promise<void> {
    if (!editing.value || !form.value.amount_minor) return;
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/professional/funds/quote-requests/${editing.value.id}/quote`, {
            amount_minor: form.value.amount_minor,
            currency: editing.value.wish.currency,
            lead_time_days: form.value.lead_time_days,
            valid_until: form.value.valid_until || null,
            terms: form.value.terms || null,
            notes: form.value.notes || null,
        });
        editing.value = null;
        await load();
    } catch {
        error.value = 'Le devis n’a pas pu être envoyé. Vérifie les montants et la date de validité.';
    } finally {
        busy.value = false;
    }
}

async function decline(item: QuoteRequest): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/professional/funds/quote-requests/${item.id}/decline`);
        await load();
    } catch {
        error.value = 'Cette demande ne peut plus être déclinée.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <section
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-cyan/20 rounded-3xl border bg-gradient-to-br p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-black tracking-[0.18em] uppercase">Fonds · Partenaires</p>
                    <h2 class="text-wpx-white-soft mt-1 text-xl font-black">Demandes de devis</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm leading-relaxed">
                        Répondez uniquement aux dossiers transmis à votre organisation. Les informations affichées sont
                        limitées au besoin nécessaire pour chiffrer l’offre.
                    </p>
                </div>
                <div class="bg-wpx-cyan/8 border-wpx-cyan/15 rounded-2xl border px-4 py-3 text-center">
                    <p class="text-wpx-white-soft text-2xl font-black">{{ openRequests.length }}</p>
                    <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">Dossier(s) actif(s)</p>
                </div>
            </div>
        </section>

        <p v-if="error" class="rounded-2xl bg-red-500/10 px-4 py-3 text-xs font-bold text-red-300">{{ error }}</p>
        <div v-if="loading" class="text-wpx-muted-dark py-12 text-center text-sm">Chargement des demandes…</div>

        <div
            v-else-if="requests.length === 0"
            class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-8 text-center"
        >
            <div class="bg-wpx-cyan/10 mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-2xl">✓</div>
            <p class="text-wpx-white-soft mt-4 font-black">Aucune demande en attente</p>
            <p class="text-wpx-muted-dark mt-1 text-xs">
                Les nouveaux dossiers Fonds apparaîtront ici après transmission explicite par Wasplex.
            </p>
        </div>

        <article
            v-for="item in requests"
            :key="item.id"
            class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-3xl border"
        >
            <div class="p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-wpx-cyan font-mono text-[10px] font-black">{{
                                item.wish.reference
                            }}</span>
                            <span
                                class="rounded-full px-2.5 py-1 text-[9px] font-black"
                                :class="statusClass(item.status)"
                                >{{ statusLabel(item.status) }}</span
                            >
                        </div>
                        <p class="text-wpx-muted-dark mt-3 text-[10px] font-bold uppercase">
                            {{ item.wish.category?.icon }} {{ item.wish.category?.name }}
                        </p>
                        <h3 class="text-wpx-white-soft mt-1 text-lg font-black">{{ item.wish.title }}</h3>
                        <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm leading-relaxed">
                            {{ item.wish.description }}
                        </p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl px-4 py-3 text-right">
                        <p class="text-wpx-muted-dark text-[9px] uppercase">Budget indicatif</p>
                        <p class="text-wpx-gold mt-1 text-sm font-black">
                            {{ money(item.wish.estimated_amount_minor, item.wish.currency) }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ item.wish.city || 'Ville non précisée' }}</p>
                    </div>
                </div>

                <div v-if="item.request_summary" class="bg-wpx-blue/8 border-wpx-blue/15 mt-4 rounded-2xl border p-3">
                    <p class="text-wpx-cyan text-[9px] font-black uppercase">Demande Wasplex</p>
                    <p class="text-wpx-white-soft mt-1 text-xs leading-relaxed">{{ item.request_summary }}</p>
                </div>

                <div v-if="item.quote" class="mt-4 grid gap-2 sm:grid-cols-3">
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[9px] uppercase">Votre offre</p>
                        <p class="text-wpx-white-soft mt-1 text-sm font-black">
                            {{ money(item.quote.amount_minor, item.quote.currency) }}
                        </p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[9px] uppercase">Délai</p>
                        <p class="text-wpx-white-soft mt-1 text-sm font-black">
                            {{ item.quote.lead_time_days ?? '—' }} jour(s)
                        </p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[9px] uppercase">Statut</p>
                        <p class="text-wpx-cyan mt-1 text-sm font-black">{{ statusLabel(item.quote.status) }}</p>
                    </div>
                </div>

                <div
                    v-if="canRespond && (item.status === 'requested' || item.status === 'responded')"
                    class="mt-4 flex flex-wrap gap-2"
                >
                    <button
                        type="button"
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-xl bg-gradient-to-r px-4 py-2.5 text-xs font-black"
                        @click="openQuote(item)"
                    >
                        {{ item.quote ? 'Mettre à jour le devis' : 'Répondre avec un devis' }}
                    </button>
                    <button
                        v-if="item.status === 'requested'"
                        type="button"
                        class="border-wpx-border-dark text-wpx-muted-dark rounded-xl border px-4 py-2.5 text-xs font-bold"
                        :disabled="busy"
                        @click="decline(item)"
                    >
                        Décliner
                    </button>
                </div>
            </div>
        </article>

        <Teleport to="body">
            <div
                v-if="editing"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/75 sm:items-center"
                @click.self="editing = null"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-cyan text-[10px] font-black uppercase">{{ editing.wish.reference }}</p>
                            <h3 class="text-wpx-white-soft mt-1 text-xl font-black">Votre proposition</h3>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Le prix, le délai et les conditions seront comparés aux autres offres reçues.
                            </p>
                        </div>
                        <button
                            class="bg-wpx-navy-850 text-wpx-white-soft h-9 w-9 rounded-full"
                            @click="editing = null"
                        >
                            ×
                        </button>
                    </div>

                    <label class="text-wpx-muted-dark mt-5 block text-xs font-bold"
                        >Montant total ({{ editing.wish.currency }})</label
                    >
                    <input
                        v-model.number="form.amount_minor"
                        type="number"
                        min="1"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    />

                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <label class="text-wpx-muted-dark text-xs font-bold"
                            >Délai en jours<input
                                v-model.number="form.lead_time_days"
                                type="number"
                                min="0"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                        <label class="text-wpx-muted-dark text-xs font-bold"
                            >Valable jusqu’au<input
                                v-model="form.valid_until"
                                type="date"
                                class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                        /></label>
                    </div>

                    <label class="text-wpx-muted-dark mt-3 block text-xs font-bold">Conditions</label>
                    <textarea
                        v-model="form.terms"
                        rows="3"
                        placeholder="Garantie, modalités, ce qui est inclus…"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    ></textarea>
                    <label class="text-wpx-muted-dark mt-3 block text-xs font-bold">Note complémentaire</label>
                    <textarea
                        v-model="form.notes"
                        rows="2"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    ></textarea>

                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-black disabled:opacity-40"
                        :disabled="busy || !form.amount_minor"
                        @click="submitQuote"
                    >
                        {{ busy ? 'Envoi…' : 'Envoyer le devis' }}
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
