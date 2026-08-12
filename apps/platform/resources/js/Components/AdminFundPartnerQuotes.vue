<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type Partner = {
    professional_space_id: string;
    organization_id: string;
    name: string;
    space_kind: string;
    country_code: string;
    territory: Record<string, string> | null;
};

type Quote = {
    id: string;
    status: string;
    amount_minor: number;
    currency: string;
    lead_time_days: number | null;
    valid_until: string | null;
    terms: string | null;
    notes: string | null;
};
type QuoteRequest = {
    id: string;
    organization_id: string;
    organization_name: string | null;
    status: string;
    requested_at: string;
    expires_at: string | null;
    quote: Quote | null;
};
type Wish = {
    id: string;
    reference: string;
    title: string;
    category: { name: string; icon: string | null } | null;
    city: string | null;
    estimated_amount_minor: number | null;
    validated_amount_minor: number | null;
    currency: string;
    selected_quote_id: string | null;
    provider: { organization_id: string; name: string } | null;
    quote_requests: QuoteRequest[];
};
type Dashboard = {
    partners: Partner[];
    wishes: Wish[];
    metrics: { verified_partners: number; open_wishes: number; quotes_received: number; providers_selected: number };
};

const loading = ref(true);
const busy = ref(false);
const error = ref('');
const data = ref<Dashboard | null>(null);
const current = ref<Wish | null>(null);
const selectedPartnerIds = ref<string[]>([]);
const summary = ref('');
const expiryDays = ref(7);

function money(value: number | null, currency = 'XOF'): string {
    if (value === null) return '—';
    return new Intl.NumberFormat('fr-FR', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);
}

function kindLabel(kind: string): string {
    return (
        (
            {
                partner: 'Partenaire',
                merchant: 'Commerce',
                service_provider: 'Prestataire',
                healthcare_institution: 'Établissement santé',
                financial_operator: 'Opérateur financier',
            } as Record<string, string>
        )[kind] ?? kind
    );
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const response = await http.get('/admin/funds/partner-dashboard');
        data.value = response.data;
    } catch {
        error.value = 'Impossible de charger le centre partenaires.';
    } finally {
        loading.value = false;
    }
}

function openRequest(wish: Wish): void {
    current.value = wish;
    selectedPartnerIds.value = [];
    summary.value = `Merci de chiffrer précisément « ${wish.title} » avec délai, conditions et garantie éventuelle.`;
    expiryDays.value = 7;
}

async function requestQuotes(): Promise<void> {
    if (!current.value || selectedPartnerIds.value.length === 0) return;
    busy.value = true;
    error.value = '';
    try {
        const expires = new Date();
        expires.setDate(expires.getDate() + expiryDays.value);
        await http.post(`/admin/funds/wishes/${current.value.id}/quote-requests`, {
            professional_space_ids: selectedPartnerIds.value,
            request_summary: summary.value || null,
            expires_at: expires.toISOString(),
        });
        current.value = null;
        await load();
    } catch {
        error.value =
            'Les demandes n’ont pas pu être envoyées. Vérifie que les prestataires sont éligibles dans le pays du vœu.';
    } finally {
        busy.value = false;
    }
}

async function selectQuote(wish: Wish, quote: Quote): Promise<void> {
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/admin/funds/wishes/${wish.id}/quotes/${quote.id}/select`);
        await load();
    } catch {
        error.value =
            'Ce devis ne peut pas être sélectionné. Il est peut-être expiré ou un autre fournisseur a déjà été choisi.';
    } finally {
        busy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <section class="flex flex-col gap-4">
        <div
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-cyan/20 rounded-3xl border bg-gradient-to-br p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-black tracking-[0.18em] uppercase">
                        P014-C · Réseau vérifié
                    </p>
                    <h3 class="text-wpx-white-soft mt-1 text-xl font-black">Centre partenaires Fonds</h3>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm leading-relaxed">
                        Demandez plusieurs devis aux organisations P019 vérifiées, comparez les réponses puis
                        sélectionnez le fournisseur réel. Aucune donnée privée du membre n’est envoyée au prestataire.
                    </p>
                </div>
                <span class="bg-wpx-gold/10 text-wpx-gold rounded-2xl px-3 py-2 text-[10px] font-black"
                    >P019 vérifié uniquement</span
                >
            </div>
        </div>

        <p v-if="error" class="rounded-2xl bg-red-500/10 px-4 py-3 text-xs font-bold text-red-300">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark py-8 text-center text-sm">Chargement du réseau partenaires…</p>

        <template v-if="data && !loading">
            <div class="grid gap-3 sm:grid-cols-4">
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Partenaires vérifiés</p>
                    <p class="text-wpx-white-soft mt-2 text-2xl font-black">{{ data.metrics.verified_partners }}</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Vœux à chiffrer</p>
                    <p class="text-wpx-gold mt-2 text-2xl font-black">{{ data.metrics.open_wishes }}</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Devis reçus</p>
                    <p class="text-wpx-cyan mt-2 text-2xl font-black">{{ data.metrics.quotes_received }}</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Fournisseurs choisis</p>
                    <p class="mt-2 text-2xl font-black text-emerald-300">{{ data.metrics.providers_selected }}</p>
                </div>
            </div>

            <div
                v-if="data.partners.length === 0"
                class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-6 text-center"
            >
                <p class="text-wpx-white-soft font-black">Aucun prestataire P019 vérifié</p>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Vérifiez d’abord une organisation partenaire, commerce ou prestataire dans les Espaces
                    professionnels.
                </p>
            </div>

            <article
                v-for="wish in data.wishes"
                :key="wish.id"
                class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-3xl border"
            >
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-wpx-cyan font-mono text-[10px] font-black">{{ wish.reference }}</p>
                            <p class="text-wpx-muted-dark mt-2 text-[10px] font-bold uppercase">
                                {{ wish.category?.icon }} {{ wish.category?.name }} ·
                                {{ wish.city || 'Ville à préciser' }}
                            </p>
                            <h4 class="text-wpx-white-soft mt-1 text-base font-black">{{ wish.title }}</h4>
                        </div>
                        <div class="text-right">
                            <p class="text-wpx-muted-dark text-[9px] uppercase">
                                {{ wish.validated_amount_minor ? 'Coût validé' : 'Estimation' }}
                            </p>
                            <p class="text-wpx-gold mt-1 text-sm font-black">
                                {{ money(wish.validated_amount_minor ?? wish.estimated_amount_minor, wish.currency) }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="wish.provider"
                        class="mt-4 rounded-2xl border border-emerald-400/15 bg-emerald-500/8 p-4"
                    >
                        <p class="text-[9px] font-black text-emerald-300 uppercase">Fournisseur sélectionné</p>
                        <p class="text-wpx-white-soft mt-1 font-black">{{ wish.provider.name }}</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Le coût est désormais validé. Les étapes de commande et paiement arriveront dans P014-E.
                        </p>
                    </div>

                    <div v-else class="mt-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-wpx-muted-dark text-[10px] font-black uppercase">
                                Consultation prestataires
                            </p>
                            <button
                                type="button"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-2 text-[10px] font-black"
                                @click="openRequest(wish)"
                            >
                                + Demander des devis
                            </button>
                        </div>

                        <div v-if="wish.quote_requests.length" class="mt-3 grid gap-2 lg:grid-cols-2">
                            <div
                                v-for="request in wish.quote_requests"
                                :key="request.id"
                                class="bg-wpx-navy-950 rounded-2xl p-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <p class="text-wpx-white-soft text-xs font-black">
                                            {{ request.organization_name || 'Organisation partenaire' }}
                                        </p>
                                        <p class="text-wpx-muted-dark mt-0.5 text-[9px] uppercase">
                                            {{ request.status.replaceAll('_', ' ') }}
                                        </p>
                                    </div>
                                    <span v-if="request.quote" class="text-wpx-cyan text-sm font-black">{{
                                        money(request.quote.amount_minor, request.quote.currency)
                                    }}</span>
                                </div>
                                <div
                                    v-if="request.quote"
                                    class="mt-3 flex items-end justify-between gap-3 border-t border-white/5 pt-3"
                                >
                                    <div class="text-wpx-muted-dark text-[10px] leading-relaxed">
                                        <p>Délai : {{ request.quote.lead_time_days ?? '—' }} jour(s)</p>
                                        <p v-if="request.quote.valid_until">
                                            Validité :
                                            {{ new Date(request.quote.valid_until).toLocaleDateString('fr-FR') }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        class="bg-wpx-gold text-wpx-navy-950 rounded-lg px-3 py-2 text-[10px] font-black disabled:opacity-40"
                                        :disabled="busy || request.quote.status !== 'submitted'"
                                        @click="selectQuote(wish, request.quote)"
                                    >
                                        Sélectionner
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-wpx-muted-dark mt-3 text-xs">Aucun devis demandé pour le moment.</p>
                    </div>
                </div>
            </article>

            <div
                v-if="data.wishes.length === 0"
                class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-6 text-center"
            >
                <p class="text-wpx-white-soft font-black">Aucun vœu validé à chiffrer</p>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Les vœux approuvés apparaîtront ici avant la phase de collecte.
                </p>
            </div>
        </template>

        <Teleport to="body">
            <div
                v-if="current"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/75 sm:items-center"
                @click.self="current = null"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-cyan text-[10px] font-black">{{ current.reference }}</p>
                            <h3 class="text-wpx-white-soft mt-1 text-xl font-black">Choisir les prestataires</h3>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                La même demande peut partir à plusieurs organisations pour permettre une vraie
                                comparaison.
                            </p>
                        </div>
                        <button class="bg-wpx-navy-850 h-9 w-9 rounded-full" @click="current = null">×</button>
                    </div>
                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="partner in data?.partners ?? []"
                            :key="partner.professional_space_id"
                            class="border-wpx-border-dark bg-wpx-navy-850 flex cursor-pointer items-start gap-3 rounded-2xl border p-3"
                            ><input
                                v-model="selectedPartnerIds"
                                type="checkbox"
                                :value="partner.professional_space_id"
                                class="mt-1"
                            /><span
                                ><strong class="text-wpx-white-soft block text-xs">{{ partner.name }}</strong
                                ><span class="text-wpx-muted-dark mt-0.5 block text-[10px]">{{
                                    kindLabel(partner.space_kind)
                                }}</span></span
                            ></label
                        >
                    </div>
                    <label class="text-wpx-muted-dark mt-4 block text-xs font-bold">Message de consultation</label
                    ><textarea
                        v-model="summary"
                        rows="3"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    ></textarea>
                    <label class="text-wpx-muted-dark mt-3 block text-xs font-bold">Délai de réponse (jours)</label
                    ><input
                        v-model.number="expiryDays"
                        type="number"
                        min="1"
                        max="30"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3"
                    />
                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 font-black disabled:opacity-40"
                        :disabled="busy || selectedPartnerIds.length === 0"
                        @click="requestQuotes"
                    >
                        {{ busy ? 'Envoi…' : `Envoyer à ${selectedPartnerIds.length} prestataire(s)` }}
                    </button>
                </div>
            </div>
        </Teleport>
    </section>
</template>
