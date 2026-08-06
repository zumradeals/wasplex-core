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

const QUICK_AMOUNTS = [2500, 5000, 10000, 25000, 50000] as const;

const wallet = ref<{ available_minor: number; currency: string } | null>(null);
const deposits = ref<Deposit[]>([]);
const loading = ref(true);
const showRecharge = ref(false);
const selectedAmount = ref<number>(QUICK_AMOUNTS[1]);
const customAmount = ref<string>('');
const creatingDeposit = ref(false);
const pendingDeposit = ref<Deposit | null>(null);
const justCredited = ref(false);
const error = ref<string | null>(null);
const loadError = ref<string | null>(null);

let pollTimer: ReturnType<typeof setInterval> | null = null;

const numberFormatter = new Intl.NumberFormat('fr-FR');

const effectiveAmount = computed<number>(() => {
    const custom = Number.parseInt(customAmount.value, 10);
    return Number.isFinite(custom) && custom > 0 ? custom : selectedAmount.value;
});

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [walletRes, depositsRes] = await Promise.all([
            http.get('/advertiser/wallet'),
            http.get('/advertiser/wallet/deposits'),
        ]);
        wallet.value = walletRes.data.wallet;
        deposits.value = depositsRes.data.deposits;
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Le Wallet annonceur est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function pickQuickAmount(amount: number): void {
    selectedAmount.value = amount;
    customAmount.value = '';
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
    } catch {
        error.value = 'La création du dépôt a échoué.';
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

        const index = deposits.value.findIndex((d) => d.id === depositId);
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
            awaiting_payment: 'En attente de paiement',
            confirmed: 'Confirmé',
            credited: 'Crédité',
            rejected: 'Refusé',
            expired: 'Expiré',
        }[status] ?? status
    );
}

function statusClasses(status: string): string {
    if (status === 'credited' || status === 'confirmed') {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (status === 'rejected' || status === 'expired') {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onBeforeUnmount(stopPolling);

void load();
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <!-- Solde -->
        <div
            v-else
            class="rounded-wpx-xl from-wpx-orange to-wpx-gold ease-wpx-reward relative overflow-hidden bg-gradient-to-br p-6 transition-shadow duration-700"
            :class="justCredited ? 'shadow-wpx-reward' : 'shadow-wpx-card'"
        >
            <p class="text-wpx-navy-950/70 text-xs font-semibold tracking-wide uppercase">Solde disponible</p>
            <p class="text-wpx-navy-950 mt-1 text-4xl font-bold [font-variant-numeric:tabular-nums]">
                <span v-if="loading">—</span>
                <span v-else>{{ numberFormatter.format(wallet?.available_minor ?? 0) }} WP</span>
            </p>
            <p class="text-wpx-navy-950/70 mt-1 text-xs">
                ≈ {{ numberFormatter.format(wallet?.available_minor ?? 0) }} FCFA — 1 WP = 1 FCFA
            </p>

            <button
                type="button"
                class="bg-wpx-navy-950 text-wpx-gold rounded-wpx-md mt-4 px-4 py-2 text-sm font-semibold shadow"
                @click="showRecharge = !showRecharge"
            >
                {{ showRecharge ? 'Annuler' : 'Recharger le Wallet' }}
            </button>

            <div v-if="justCredited" class="text-wpx-navy-950 absolute top-4 right-4 text-xs font-semibold">
                ✨ Dépôt confirmé
            </div>
        </div>

        <!-- Recharge -->
        <div
            v-if="!loadError && showRecharge"
            class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex flex-col gap-4 p-5"
        >
            <h3 class="text-wpx-text text-sm font-semibold">Choisir un montant (FCFA)</h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="amount in QUICK_AMOUNTS"
                    :key="amount"
                    type="button"
                    class="rounded-wpx-full border px-4 py-1.5 text-sm font-medium"
                    :class="
                        effectiveAmount === amount && customAmount === ''
                            ? 'border-wpx-orange bg-wpx-orange/10 text-wpx-orange-light'
                            : 'border-wpx-border text-wpx-text'
                    "
                    @click="pickQuickAmount(amount)"
                >
                    {{ numberFormatter.format(amount) }}
                </button>
            </div>

            <label class="flex flex-col gap-1 text-xs">
                <span class="text-wpx-text-muted">Ou montant personnalisé</span>
                <input
                    v-model="customAmount"
                    type="number"
                    min="100"
                    placeholder="Ex. 15000"
                    class="rounded-wpx-sm border-wpx-border border px-3 py-2 text-sm"
                />
            </label>

            <button
                type="button"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-4 py-2 text-sm font-semibold disabled:opacity-50"
                :disabled="creatingDeposit"
                @click="startRecharge"
            >
                Continuer vers GeniusPay ({{ numberFormatter.format(effectiveAmount) }} FCFA)
            </button>

            <p v-if="error" class="text-wpx-danger-light text-xs">{{ error }}</p>

            <div
                v-if="pendingDeposit && pendingDeposit.status === 'awaiting_payment'"
                class="text-wpx-text-muted flex items-center gap-2 text-xs"
            >
                <span class="border-wpx-border h-3 w-3 animate-spin rounded-full border-2 border-t-transparent"></span>
                Vérification du paiement en cours…
            </div>
        </div>

        <!-- Historique -->
        <div v-if="!loadError" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-5">
            <h3 class="text-wpx-text mb-3 text-sm font-semibold">Historique des dépôts</h3>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <p v-else-if="deposits.length === 0" class="text-wpx-text-muted text-sm">Aucun dépôt pour le moment.</p>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Référence</th>
                        <th class="p-2">Montant</th>
                        <th class="p-2">Statut</th>
                        <th class="p-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="deposit in deposits" :key="deposit.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2 font-mono text-xs">{{ deposit.provider_reference ?? '—' }}</td>
                        <td class="p-2 [font-variant-numeric:tabular-nums]">
                            {{ numberFormatter.format(deposit.amount_minor) }} {{ deposit.currency }}
                        </td>
                        <td class="p-2">
                            <span
                                class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                :class="statusClasses(deposit.status)"
                            >
                                {{ statusLabel(deposit.status) }}
                            </span>
                        </td>
                        <td class="text-wpx-text-muted p-2 text-xs">{{ deposit.created_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
