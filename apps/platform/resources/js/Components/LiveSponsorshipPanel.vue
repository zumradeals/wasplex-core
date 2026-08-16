<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import http from '@/lib/http';

type LiveStatus = 'draft' | 'scheduled' | 'live' | 'paused' | 'ended';

type SponsorshipStatus = 'draft' | 'quoted' | 'funds_reserved' | 'cancelled';

interface SponsorshipQuote {
    id: string;
    status: 'active' | 'consumed' | 'superseded';
    currency: string;
    budget_amount_minor: number;
    wasplex_share_minor: number;
    spectator_envelope_minor: number;
    estimated_reach_min: number;
    estimated_reach_max: number;
    planned_duration_minutes: number;
    block_duration_seconds: number;
    quoted_at: string | null;
}

interface SponsorshipSummary {
    status: SponsorshipStatus;
    segment: { country_code: string | null; economic_classes: string[] } | null;
    latest_quote: SponsorshipQuote | null;
    reservation: {
        status: 'reserved' | 'released';
        amount_minor: number;
        reserved_at: string | null;
        released_at: string | null;
    } | null;
    can_schedule: boolean;
}

interface SponsorableLive {
    id: string;
    type: 'standard' | 'sponsored';
    status: LiveStatus;
    scheduled_at: string | null;
    sponsorship: SponsorshipSummary | null;
}

interface ApiError {
    response?: { data?: { message?: string } };
}

const props = defineProps<{ live: SponsorableLive }>();
const emit = defineEmits<{ updated: [live: SponsorableLive] }>();

const busy = ref(false);
const error = ref<string | null>(null);
const estimate = ref<{ estimated_reach_min: number; estimated_reach_max: number; too_small: boolean } | null>(null);
const countryCode = ref('CI');
const budgetAmount = ref('');
const blockDurationSeconds = ref(300);
const plannedSchedule = ref('');

const sponsorship = computed(() => props.live.sponsorship);
const isFunded = computed(() => sponsorship.value?.status === 'funds_reserved' && sponsorship.value.can_schedule);

function syncForm(): void {
    countryCode.value = props.live.sponsorship?.segment?.country_code ?? 'CI';
    const quote = props.live.sponsorship?.latest_quote;
    if (quote && budgetAmount.value === '') budgetAmount.value = String(quote.budget_amount_minor);
    if (quote) blockDurationSeconds.value = quote.block_duration_seconds;
    plannedSchedule.value = toDatetimeLocal(props.live.scheduled_at);
}

function toDatetimeLocal(value: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 16);
}

function messageFrom(cause: unknown): string {
    return (cause as ApiError)?.response?.data?.message ?? 'Une erreur est survenue. Réessayez.';
}

function money(value: number): string {
    return new Intl.NumberFormat('fr-FR').format(value) + ' WP';
}

function updateLive(live: SponsorableLive): void {
    emit('updated', live);
}

async function configure(): Promise<SponsorableLive> {
    const { data } = await http.patch(`/advertiser/lives/${props.live.id}/sponsorship`, {
        country_code: countryCode.value.trim().toUpperCase() || null,
    });
    updateLive(data.live);

    return data.live;
}

async function activate(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        await configure();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function estimateAudience(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        await configure();
        const { data } = await http.post(`/advertiser/lives/${props.live.id}/segment-estimate`);
        estimate.value = data.estimate;
        updateLive(data.live);
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function quote(): Promise<void> {
    const amount = Number(budgetAmount.value);
    if (!Number.isInteger(amount) || amount < 2) {
        error.value = 'Renseignez un budget entier en WP.';
        return;
    }

    busy.value = true;
    error.value = null;
    try {
        await configure();
        const { data } = await http.post(`/advertiser/lives/${props.live.id}/quote`, {
            budget_amount_minor: amount,
            block_duration_seconds: blockDurationSeconds.value,
        });
        updateLive(data.live);
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function fund(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/advertiser/lives/${props.live.id}/fund`);
        updateLive(data.live);
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function schedule(): Promise<void> {
    if (plannedSchedule.value === '') {
        error.value = 'Choisissez la date et l’heure du Live.';
        return;
    }

    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/advertiser/lives/${props.live.id}/schedule`, {
            scheduled_at: new Date(plannedSchedule.value).toISOString(),
        });
        updateLive(data.live);
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function cancelSponsorship(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/advertiser/lives/${props.live.id}/sponsorship/cancel`);
        estimate.value = null;
        budgetAmount.value = '';
        updateLive(data.live);
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

watch(() => props.live, syncForm, { immediate: true, deep: true });
</script>

<template>
    <section class="border-wpx-border-dark bg-wpx-navy-950 rounded-2xl border p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-gold text-[10px] font-bold tracking-[0.14em] uppercase">Live sponsorisé</p>
                <h3 class="text-wpx-white-soft mt-1 text-sm font-extrabold">Financement du direct</h3>
                <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                    Un Live standard ne rémunère personne. La sponsorisation réserve d’abord le budget annonceur avant
                    toute programmation officielle.
                </p>
            </div>
            <span
                v-if="live.type === 'sponsored'"
                class="bg-wpx-gold/10 text-wpx-gold rounded-full px-2.5 py-1 text-[10px] font-bold"
            >
                {{ sponsorship?.status ?? 'draft' }}
            </span>
        </div>

        <p v-if="error" class="bg-wpx-danger/12 text-wpx-danger mt-3 rounded-xl px-3 py-2.5 text-xs" aria-live="polite">
            {{ error }}
        </p>

        <button
            v-if="live.type === 'standard'"
            type="button"
            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
            :disabled="busy"
            @click="activate"
        >
            {{ busy ? 'Activation…' : 'Activer la sponsorisation' }}
        </button>

        <template v-else>
            <div class="mt-4 grid grid-cols-2 gap-2">
                <div>
                    <label class="text-wpx-muted-dark block text-[11px]">Pays</label>
                    <input
                        v-model="countryCode"
                        maxlength="2"
                        placeholder="CI"
                        :disabled="isFunded"
                        class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm uppercase outline-none disabled:opacity-50"
                    />
                </div>
                <div>
                    <label class="text-wpx-muted-dark block text-[11px]">Bloc d’attention</label>
                    <select
                        v-model.number="blockDurationSeconds"
                        :disabled="isFunded"
                        class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none disabled:opacity-50"
                    >
                        <option :value="120">2 minutes</option>
                        <option :value="300">5 minutes</option>
                        <option :value="600">10 minutes</option>
                    </select>
                </div>
            </div>

            <button
                v-if="!isFunded"
                type="button"
                class="border-wpx-border-dark text-wpx-white-soft mt-3 w-full rounded-xl border px-4 py-3 text-sm font-bold disabled:opacity-50"
                :disabled="busy"
                @click="estimateAudience"
            >
                Estimer l’audience
            </button>

            <p v-if="estimate" class="text-wpx-muted-dark mt-2 text-xs">
                Audience protégée : {{ estimate.estimated_reach_min }}–{{ estimate.estimated_reach_max }} membres
                <span v-if="estimate.too_small">· segment trop petit</span>
            </p>

            <template v-if="!isFunded">
                <label class="text-wpx-muted-dark mt-4 block text-[11px]">Budget total · WP</label>
                <input
                    v-model="budgetAmount"
                    type="number"
                    min="2"
                    step="2"
                    placeholder="Ex. 100000"
                    class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none"
                />

                <button
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                    :disabled="busy || budgetAmount === ''"
                    @click="quote"
                >
                    Générer le devis 50/50
                </button>
            </template>

            <div v-if="sponsorship?.latest_quote" class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-2xl border p-3.5">
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Budget</p>
                        <p class="text-wpx-white-soft mt-1 text-xs font-extrabold">
                            {{ money(sponsorship.latest_quote.budget_amount_minor) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Spectateurs</p>
                        <p class="text-wpx-gold mt-1 text-xs font-extrabold">
                            {{ money(sponsorship.latest_quote.spectator_envelope_minor) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Wasplex</p>
                        <p class="text-wpx-white-soft mt-1 text-xs font-extrabold">
                            {{ money(sponsorship.latest_quote.wasplex_share_minor) }}
                        </p>
                    </div>
                </div>
                <p class="text-wpx-muted-dark mt-3 text-[11px]">
                    Audience estimée : {{ sponsorship.latest_quote.estimated_reach_min }}–{{
                        sponsorship.latest_quote.estimated_reach_max
                    }}. Les places et le gain par bloc seront attribués par le moteur Live, jamais par le navigateur.
                </p>
            </div>

            <button
                v-if="sponsorship?.status === 'quoted'"
                type="button"
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                :disabled="busy"
                @click="fund"
            >
                {{ busy ? 'Réservation…' : 'Réserver le budget annonceur' }}
            </button>

            <div v-if="isFunded" class="mt-4">
                <p class="text-wpx-gold text-xs font-bold">✓ Budget réservé au Grand Livre</p>
                <label class="text-wpx-muted-dark mt-3 block text-[11px]">Date officielle</label>
                <input
                    v-model="plannedSchedule"
                    type="datetime-local"
                    class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-xs outline-none"
                />
                <button
                    v-if="live.status === 'draft'"
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                    :disabled="busy || plannedSchedule === ''"
                    @click="schedule"
                >
                    Programmer officiellement
                </button>
            </div>

            <button
                v-if="live.status === 'draft' || live.status === 'scheduled'"
                type="button"
                class="border-wpx-danger/35 text-wpx-danger mt-4 w-full rounded-xl border px-4 py-3 text-xs font-bold disabled:opacity-50"
                :disabled="busy"
                @click="cancelSponsorship"
            >
                Annuler la sponsorisation
            </button>
        </template>
    </section>
</template>
