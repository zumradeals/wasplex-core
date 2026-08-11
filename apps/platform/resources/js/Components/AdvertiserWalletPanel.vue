<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import http from '@/lib/http';

interface Deposit {
    id: string;
    amount_minor: number;
    currency: string;
    status: string;
    provider_reference: string | null;
    checkout_url: string | null;
    created_at: string;
}

interface CampaignReport {
    status: string;
    budget_captured_minor: number;
}

const QUICK_AMOUNTS = [2500, 5000, 10000, 25000, 50000] as const;

const wallet = ref<{ available_minor: number; currency: string } | null>(null);
const deposits = ref<Deposit[]>([]);
const campaignReports = ref<CampaignReport[]>([]);
const loading = ref(true);
const showRecharge = ref(false);
const selectedAmount = ref<number>(QUICK_AMOUNTS[1]);
const customAmount = ref('');
const creatingDeposit = ref(false);
const pendingDeposit = ref<Deposit | null>(null);
const justCredited = ref(false);
const error = ref<string | null>(null);
const loadError = ref<string | null>(null);

let pollTimer: ReturnType<typeof setInterval> | null = null;

const numberFormatter = new Intl.NumberFormat('fr-FR');
const dateFormatter = new Intl.DateTimeFormat('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
});

const totalSpentMinor = computed(() =>
    campaignReports.value.reduce((total, report) => total + report.budget_captured_minor, 0),
);
const activeCampaignsCount = computed(
    () => campaignReports.value.filter((report) => report.status === 'approved').length,
);
const effectiveAmount = computed<number>(() => {
    const custom = Number.parseInt(customAmount.value, 10);
    return Number.isFinite(custom) && custom > 0 ? custom : selectedAmount.value;
});

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [walletRes, depositsRes, reportsRes] = await Promise.all([
            http.get('/advertiser/wallet'),
            http.get('/advertiser/wallet/deposits'),
            http.get('/advertiser/campaigns/report'),
        ]);
        wallet.value = walletRes.data.wallet;
        deposits.value = depositsRes.data.deposits;
        campaignReports.value = reportsRes.data.reports;
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Votre solde publicitaire est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function pickQuickAmount(amount: number): void {
    selectedAmount.value = amount;
    customAmount.value = '';
}

function openRecharge(): void {
    error.value = null;
    pendingDeposit.value = null;
    showRecharge.value = true;
}

function closeRecharge(): void {
    if (!creatingDeposit.value) {
        showRecharge.value = false;
    }
}

async function startRecharge(): Promise<void> {
    error.value = null;
    creatingDeposit.value = true;
    try {
        const { data } = await http.post('/advertiser/wallet/deposits', {
            amount_minor: effectiveAmount.value,
            currency: 'XOF',
        });
        pendingDeposit.value = data.deposit;
        deposits.value = [data.deposit, ...deposits.value];

        if (data.deposit.checkout_url) {
            window.open(data.deposit.checkout_url, '_blank', 'noopener,noreferrer');
        }

        beginPolling(data.deposit.id);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'La recharge n’a pas pu être démarrée.';
    } finally {
        creatingDeposit.value = false;
    }
}

function beginPolling(depositId: string): void {
    stopPolling();

    let attempts = 0;
    pollTimer = setInterval(async () => {
        attempts += 1;
        const { data } = await http.get(`/advertiser/wallet/deposits/${depositId}`);
        pendingDeposit.value = data.deposit;

        const index = deposits.value.findIndex((deposit) => deposit.id === depositId);
        if (index !== -1) {
            deposits.value[index] = data.deposit;
        }

        if (data.deposit.status === 'credited') {
            stopPolling();
            justCredited.value = true;
            showRecharge.value = false;
            await load();
            setTimeout(() => {
                justCredited.value = false;
            }, 1800);
        } else if (['rejected', 'expired'].includes(data.deposit.status) || attempts >= 40) {
            stopPolling();
        }
    }, 3000);
}

function stopPolling(): void {
    if (pollTimer !== null) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

function statusLabel(status: string): string {
    return (
        {
            created: 'Initié',
            awaiting_payment: 'Paiement en attente',
            confirmed: 'Confirmé',
            credited: 'Ajouté au solde',
            rejected: 'Refusé',
            expired: 'Expiré',
        }[status] ?? status
    );
}

function statusClasses(status: string): string {
    if (status === 'credited' || status === 'confirmed') {
        return 'bg-wpx-success/15 text-wpx-success-light';
    }
    if (status === 'rejected' || status === 'expired') {
        return 'bg-wpx-danger/15 text-wpx-danger-light';
    }

    return 'bg-wpx-gold/12 text-wpx-gold';
}

function formatDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : dateFormatter.format(date);
}

onBeforeUnmount(stopPolling);
void load();
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 lg:gap-5">
        <div v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-2xl p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <section
                class="from-wpx-orange to-wpx-gold shadow-wpx-card-dark relative overflow-hidden rounded-3xl bg-gradient-to-br p-5 sm:p-6"
                :class="justCredited ? 'shadow-wpx-reward' : ''"
            >
                <div class="relative z-10 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-wpx-navy-950/65 text-xs font-extrabold tracking-wide uppercase">
                            Mon solde publicitaire
                        </p>
                        <p
                            class="text-wpx-navy-950 mt-1 text-4xl font-black [font-variant-numeric:tabular-nums] sm:text-5xl"
                        >
                            <span v-if="loading">—</span>
                            <span v-else>{{ numberFormatter.format(wallet?.available_minor ?? 0) }} FCFA</span>
                        </p>
                        <p class="text-wpx-navy-950/65 mt-1 text-xs">
                            Disponible pour financer vos prochaines publicités.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="bg-wpx-navy-950 text-wpx-gold flex min-h-12 items-center justify-center rounded-2xl px-5 py-3 text-sm font-extrabold shadow-lg"
                        @click="openRecharge"
                    >
                        + Recharger mon solde
                    </button>
                </div>
                <div
                    v-if="justCredited"
                    class="text-wpx-navy-950 absolute top-4 right-4 rounded-full bg-white/35 px-3 py-1 text-xs font-bold"
                >
                    ✓ Recharge confirmée
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-bold tracking-wide uppercase">
                        Dépensé en publicité
                    </p>
                    <p class="text-wpx-white-soft mt-1.5 text-xl font-extrabold">
                        {{ numberFormatter.format(totalSpentMinor) }} FCFA
                    </p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-bold tracking-wide uppercase">Publicités actives</p>
                    <p class="text-wpx-white-soft mt-1.5 text-xl font-extrabold">{{ activeCampaignsCount }}</p>
                </div>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-2xl border">
                <div class="border-wpx-border-dark flex items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                    <div>
                        <h2 class="text-wpx-white-soft text-sm font-bold">Mes recharges</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            Suivez simplement l’argent ajouté à votre solde.
                        </p>
                    </div>
                    <button type="button" class="text-wpx-cyan text-xs font-bold" @click="openRecharge">
                        Recharger
                    </button>
                </div>

                <div v-if="loading" class="text-wpx-muted-dark p-5 text-sm">Chargement…</div>
                <div v-else-if="deposits.length === 0" class="px-5 py-9 text-center">
                    <span class="bg-wpx-blue/12 mx-auto flex h-12 w-12 items-center justify-center rounded-2xl">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect x="3" y="6" width="18" height="13" rx="3" stroke="#4FA3FF" stroke-width="1.7" />
                            <path d="M3 10h18" stroke="#4FA3FF" stroke-width="1.7" />
                        </svg>
                    </span>
                    <p class="text-wpx-white-soft mt-3 text-sm font-bold">Aucune recharge pour le moment</p>
                    <p class="text-wpx-muted-dark mx-auto mt-1 max-w-sm text-xs leading-relaxed">
                        Ajoutez un montant lorsque vous êtes prêt à financer votre première publicité.
                    </p>
                    <button type="button" class="text-wpx-cyan mt-3 text-xs font-bold" @click="openRecharge">
                        Faire ma première recharge →
                    </button>
                </div>

                <div v-else class="divide-wpx-border-dark divide-y">
                    <article
                        v-for="deposit in deposits"
                        :key="deposit.id"
                        class="flex items-center gap-3 px-4 py-4 sm:px-5"
                    >
                        <span class="bg-wpx-navy-750 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M12 4v12M7.5 8.5L12 4l4.5 4.5"
                                    stroke="#2BC4DE"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M4 15v3a2 2 0 002 2h12a2 2 0 002-2v-3"
                                    stroke="#2BC4DE"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-wpx-white-soft text-sm font-extrabold">
                                + {{ numberFormatter.format(deposit.amount_minor) }} FCFA
                            </p>
                            <p class="text-wpx-muted-dark mt-0.5 truncate text-[11px]">
                                {{ formatDate(deposit.created_at) }}
                                <template v-if="deposit.provider_reference">
                                    · {{ deposit.provider_reference }}</template
                                >
                            </p>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                            :class="statusClasses(deposit.status)"
                        >
                            {{ statusLabel(deposit.status) }}
                        </span>
                    </article>
                </div>
            </section>
        </template>

        <div
            v-if="showRecharge"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 p-0 sm:items-center sm:p-4"
            @click.self="closeRecharge"
        >
            <section
                class="border-wpx-border-dark bg-wpx-navy-850 w-full max-w-lg rounded-t-3xl border p-5 shadow-2xl sm:rounded-3xl sm:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">
                            Recharger mon solde
                        </p>
                        <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">
                            Quel montant voulez-vous ajouter ?
                        </h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Le montant sera disponible pour financer vos publicités.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-lg"
                        aria-label="Fermer"
                        @click="closeRecharge"
                    >
                        ×
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    <button
                        v-for="amount in QUICK_AMOUNTS"
                        :key="amount"
                        type="button"
                        class="rounded-2xl border px-3 py-3 text-sm font-extrabold"
                        :class="
                            effectiveAmount === amount && customAmount === ''
                                ? 'border-wpx-gold bg-wpx-gold/12 text-wpx-gold'
                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                        "
                        @click="pickQuickAmount(amount)"
                    >
                        {{ numberFormatter.format(amount) }} FCFA
                    </button>
                </div>

                <label class="mt-4 block">
                    <span class="text-wpx-muted-dark text-xs font-semibold">Autre montant</span>
                    <div
                        class="border-wpx-border-dark bg-wpx-navy-750 mt-1.5 flex items-center rounded-2xl border px-4"
                    >
                        <input
                            v-model="customAmount"
                            type="number"
                            min="200"
                            placeholder="Ex. 15 000"
                            class="text-wpx-white-soft placeholder:text-wpx-muted-dark min-w-0 flex-1 bg-transparent py-3 text-base font-bold outline-none"
                        />
                        <span class="text-wpx-muted-dark text-xs font-bold">FCFA</span>
                    </div>
                </label>

                <div class="bg-wpx-gold/8 border-wpx-gold/20 mt-4 rounded-2xl border p-3">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-wpx-muted-dark text-xs">Montant à ajouter</span>
                        <strong class="text-wpx-gold text-base"
                            >{{ numberFormatter.format(effectiveAmount) }} FCFA</strong
                        >
                    </div>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">GeniusPay · environnement de test actuel</p>
                </div>

                <p v-if="error" class="text-wpx-danger-light mt-3 text-xs">{{ error }}</p>
                <div
                    v-if="pendingDeposit?.status === 'awaiting_payment'"
                    class="text-wpx-muted-dark mt-3 flex items-center gap-2 text-xs"
                >
                    <span
                        class="border-wpx-border-dark h-3.5 w-3.5 animate-spin rounded-full border-2 border-t-transparent"
                    />
                    Vérification du paiement en cours…
                </div>

                <button
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-2xl bg-gradient-to-r px-5 py-3.5 text-sm font-extrabold disabled:opacity-50"
                    :disabled="creatingDeposit || effectiveAmount < 200"
                    @click="startRecharge"
                >
                    {{
                        creatingDeposit
                            ? 'Préparation…'
                            : `Continuer avec ${numberFormatter.format(effectiveAmount)} FCFA`
                    }}
                </button>
            </section>
        </div>
    </div>
</template>
