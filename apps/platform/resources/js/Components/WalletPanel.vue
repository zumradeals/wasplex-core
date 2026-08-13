<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

interface HistoryEntry {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    created_at: string | null;
}

interface WalletSummary {
    today_credits_minor: number;
    month_credits_minor: number;
    month_debits_minor: number;
    pending_deposits_minor: number;
}

interface Recipient {
    account_id: string;
    display_name: string;
    identifier_hint: string;
}

const props = defineProps<{ accountLabel?: string; phoneNumber?: string | null }>();
const emit = defineEmits<{ goToSubscription: [] }>();

const balance = ref<number | null>(null);
const summary = ref<WalletSummary>({
    today_credits_minor: 0,
    month_credits_minor: 0,
    month_debits_minor: 0,
    pending_deposits_minor: 0,
});
const history = ref<HistoryEntry[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const notice = ref<string | null>(null);
const historyOpen = ref(true);

const showDeposit = ref(false);
const depositAmount = ref<number | null>(null);
const depositBusy = ref(false);
const depositError = ref<string | null>(null);

const showTransfer = ref(false);
const recipientIdentifier = ref('');
const recipient = ref<Recipient | null>(null);
const recipientBusy = ref(false);
const transferAmount = ref<number | null>(null);
const transferBusy = ref(false);
const transferError = ref<string | null>(null);
const transferIdempotency = ref('');

const showWithdrawal = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const page = usePage<{ auth: AuthShared }>();

const availableLabel = computed(() => numberFormatter.format(balance.value ?? 0));

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [walletRes, historyRes] = await Promise.all([http.get('/me/wallet'), http.get('/me/wallet/history')]);
        balance.value = walletRes.data.balance_minor;
        summary.value = walletRes.data.summary ?? summary.value;
        history.value = historyRes.data.history.data ?? historyRes.data.history;
    } catch (e) {
        loadError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le Wallet est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function describe(entry: HistoryEntry): string {
    if (entry.description) return entry.description;
    if (entry.type === 'USER_WALLET_DEPOSIT') return 'Dépôt Wallet';
    if (entry.type === 'USER_TRANSFER') return entry.direction === 'credit' ? 'Transfert reçu' : 'Transfert envoyé';

    return entry.type ?? (entry.direction === 'credit' ? 'Crédit Wallet' : 'Débit Wallet');
}

function openDepositModal(): void {
    depositAmount.value = null;
    depositError.value = null;
    showDeposit.value = true;
}

async function startDeposit(): Promise<void> {
    if (!depositAmount.value || depositAmount.value < 200) {
        depositError.value = 'Le dépôt minimum est de 200 FCFA.';
        return;
    }

    depositBusy.value = true;
    depositError.value = null;
    try {
        const { data } = await http.post('/me/wallet/deposits', { amount_minor: Math.trunc(depositAmount.value) });
        if (!data.deposit?.checkout_url) {
            throw new Error('Checkout GeniusPay indisponible');
        }

        window.location.assign(data.deposit.checkout_url);
    } catch (e) {
        depositError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de démarrer le dépôt.';
    } finally {
        depositBusy.value = false;
    }
}

function openTransferModal(): void {
    recipientIdentifier.value = '';
    recipient.value = null;
    transferAmount.value = null;
    transferError.value = null;
    transferIdempotency.value = '';
    showTransfer.value = true;
}

async function resolveRecipient(): Promise<void> {
    if (!recipientIdentifier.value.trim()) return;

    recipientBusy.value = true;
    transferError.value = null;
    recipient.value = null;
    try {
        const { data } = await http.post('/me/wallet/transfers/recipient', {
            identifier: recipientIdentifier.value.trim(),
        });
        recipient.value = data.recipient;
    } catch (e) {
        transferError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Destinataire introuvable.';
    } finally {
        recipientBusy.value = false;
    }
}

async function submitTransfer(): Promise<void> {
    if (!recipient.value || !transferAmount.value || transferAmount.value <= 0) return;
    if (transferAmount.value > (balance.value ?? 0)) {
        transferError.value = 'Votre solde disponible est insuffisant.';
        return;
    }

    transferBusy.value = true;
    transferError.value = null;
    transferIdempotency.value ||= crypto.randomUUID();
    try {
        await http.post('/me/wallet/transfers', {
            recipient_account_id: recipient.value.account_id,
            amount_minor: Math.trunc(transferAmount.value),
            idempotency_key: transferIdempotency.value,
        });
        notice.value = `${numberFormatter.format(Math.trunc(transferAmount.value))} WP transférés à ${recipient.value.display_name}.`;
        showTransfer.value = false;
        await load();
    } catch (e) {
        transferError.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Le transfert a échoué.';
    } finally {
        transferBusy.value = false;
    }
}

async function processPaymentReturn(): Promise<void> {
    const params = new URLSearchParams(window.location.search);
    const paymentState = params.get('payment');
    const depositId = params.get('deposit_id');

    if (paymentState === 'wallet-deposit-failed') {
        notice.value = 'Le paiement n’a pas été finalisé. Aucun WP n’a été ajouté.';
    } else if (paymentState === 'wallet-deposit-success' && depositId) {
        try {
            const { data } = await http.post(`/me/wallet/deposits/${depositId}/refresh`);
            notice.value =
                data.deposit?.status === 'credited'
                    ? `${numberFormatter.format(data.deposit.amount_minor)} WP ajoutés à votre Wallet.`
                    : 'Paiement reçu. La confirmation GeniusPay est encore en cours.';
        } catch (e) {
            notice.value =
                (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
                'Votre paiement est en cours de vérification.';
        }
    }

    if (paymentState?.startsWith('wallet-deposit-')) {
        params.delete('payment');
        params.delete('deposit_id');
        const query = params.toString();
        window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
    }
}

defineExpose({ load });

onMounted(async () => {
    await processPaymentReturn();
    await load();
});

useEcho(`wallet.${page.props.auth.account.id}`, '.wallet.balance.changed', load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <p
            v-if="notice"
            class="border-wpx-cyan/25 bg-wpx-cyan/10 text-wpx-cyan rounded-wpx-md border px-3.5 py-3 text-xs leading-relaxed"
        >
            {{ notice }}
        </p>
        <p v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs">
            {{ loadError }}
        </p>

        <section
            class="rounded-wpx-xl from-wpx-orange via-wpx-gold to-wpx-orange wpx-motion-safe shadow-wpx-card-dark relative overflow-hidden bg-gradient-to-br p-5"
        >
            <div
                class="wpx-motion-safe pointer-events-none absolute inset-y-0 -left-14 w-20 animate-[wpxShine_4.2s_ease-in-out_infinite] bg-gradient-to-r from-white/0 via-white/30 to-white/0"
            />
            <div class="relative">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-navy-950/60 text-[10px] font-extrabold tracking-[0.18em]">WASPLEX WALLET</p>
                        <p v-if="props.accountLabel" class="text-wpx-navy-950 mt-1 text-sm font-bold">
                            {{ props.accountLabel }}
                        </p>
                    </div>
                    <span class="bg-wpx-navy-950/10 text-wpx-navy-950 rounded-full px-2.5 py-1 text-[10px] font-bold">
                        Disponible
                    </span>
                </div>

                <p class="text-wpx-navy-950/65 mt-6 text-xs font-bold">Solde utilisable</p>
                <p class="text-wpx-navy-950 mt-0.5 text-[2.65rem] leading-none font-extrabold tabular-nums">
                    <span v-if="loading">…</span>
                    <template v-else>{{ availableLabel }} <span class="text-xl">WP</span></template>
                </p>
                <p class="text-wpx-navy-950/60 mt-1 text-xs">≈ {{ availableLabel }} FCFA · 1 WP = 1 FCFA</p>
                <p v-if="props.phoneNumber" class="text-wpx-navy-950/55 mt-4 font-mono text-[11px]">
                    {{ props.phoneNumber }}
                </p>
            </div>
        </section>

        <section class="grid grid-cols-3 gap-2.5">
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="openDepositModal"
            >
                <span
                    class="bg-wpx-success/12 text-wpx-success flex h-9 w-9 items-center justify-center rounded-full text-xl"
                    >↓</span
                >
                <span class="text-wpx-success text-xs font-bold">Déposer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="openTransferModal"
            >
                <span class="bg-wpx-blue/12 text-wpx-blue flex h-9 w-9 items-center justify-center rounded-full text-lg"
                    >⇄</span
                >
                <span class="text-wpx-blue text-xs font-bold">Transférer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex min-h-24 flex-col items-center justify-center gap-2 border px-2 py-3.5"
                @click="showWithdrawal = true"
            >
                <span
                    class="bg-wpx-orange/12 text-wpx-orange flex h-9 w-9 items-center justify-center rounded-full text-xl"
                    >↑</span
                >
                <span class="text-wpx-orange text-xs font-bold">Retirer</span>
                <span class="text-wpx-muted-dark text-[9px]">Bientôt</span>
            </button>
        </section>

        <section class="grid grid-cols-3 gap-2.5">
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-success text-base font-bold">
                    +{{ numberFormatter.format(summary.today_credits_minor) }}
                </p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Aujourd’hui</p>
            </div>
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-blue text-base font-bold">
                    +{{ numberFormatter.format(summary.month_credits_minor) }}
                </p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Ce mois</p>
            </div>
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-gold text-base font-bold">
                    {{ numberFormatter.format(summary.pending_deposits_minor) }}
                </p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">En attente</p>
            </div>
        </section>

        <button
            type="button"
            class="from-wpx-navy-750 to-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg flex items-center gap-3 border bg-gradient-to-br p-3.5 text-left"
            @click="emit('goToSubscription')"
        >
            <span class="bg-wpx-blue/18 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
                <svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2l6 6-6 14-6-14z" fill="#4FA3FF" /></svg>
            </span>
            <span class="flex-1">
                <span class="text-wpx-white-soft block text-sm font-bold">Mon abonnement</span>
                <span class="text-wpx-muted-dark mt-0.5 block text-[11px]">Gérez votre plan et vos avantages</span>
            </span>
            <span class="text-wpx-blue text-xs font-bold">Voir ›</span>
        </button>

        <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 p-3.5 text-left"
                @click="historyOpen = !historyOpen"
            >
                <span>
                    <span class="text-wpx-white-soft block text-sm font-bold">Activité du Wallet</span>
                    <span class="text-wpx-muted-dark mt-0.5 block text-[10px]"
                        >Publicités, dépôts, transferts et autres mouvements</span
                    >
                </span>
                <span class="flex items-center gap-2">
                    <span class="bg-wpx-blue/12 text-wpx-blue rounded-full px-2 py-0.5 text-[10px] font-bold">{{
                        history.length
                    }}</span>
                    <span class="text-wpx-muted-dark text-sm">{{ historyOpen ? '⌃' : '⌄' }}</span>
                </span>
            </button>

            <div v-if="historyOpen" class="border-wpx-border-dark border-t px-3.5 pb-3.5">
                <p v-if="loading" class="text-wpx-muted-dark pt-3 text-sm">Chargement…</p>
                <ul v-else class="divide-wpx-border-dark divide-y">
                    <li v-for="entry in history" :key="entry.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-wpx-white-soft truncate text-sm font-semibold">{{ describe(entry) }}</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                                {{ entry.created_at ? new Date(entry.created_at).toLocaleString('fr-FR') : '' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-bold tabular-nums"
                            :class="entry.direction === 'credit' ? 'text-wpx-success-light' : 'text-wpx-orange'"
                        >
                            {{ entry.direction === 'credit' ? '+' : '−'
                            }}{{ numberFormatter.format(entry.amount_minor) }} WP
                        </span>
                    </li>
                    <li v-if="history.length === 0" class="text-wpx-muted-dark py-5 text-center text-sm">
                        Aucun mouvement pour le moment.
                    </li>
                </ul>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="showDeposit"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showDeposit = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-wpx-white-soft text-lg font-bold">Déposer dans mon Wallet</h2>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Paiement sécurisé via GeniusPay · minimum 200 FCFA
                            </p>
                        </div>
                        <button
                            type="button"
                            class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full"
                            @click="showDeposit = false"
                        >
                            ×
                        </button>
                    </div>
                    <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase">Montant</label>
                    <div
                        class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-md mt-2 flex items-center border px-3"
                    >
                        <input
                            v-model.number="depositAmount"
                            type="number"
                            min="200"
                            inputmode="numeric"
                            class="text-wpx-white-soft min-w-0 flex-1 bg-transparent py-3.5 text-xl font-bold outline-none"
                            placeholder="5 000"
                        />
                        <span class="text-wpx-gold text-sm font-bold">FCFA</span>
                    </div>
                    <p class="text-wpx-muted-dark mt-2 text-xs">
                        Après confirmation : {{ numberFormatter.format(depositAmount ?? 0) }} WP seront crédités.
                    </p>
                    <p
                        v-if="depositError"
                        class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md mt-3 p-3 text-xs"
                    >
                        {{ depositError }}
                    </p>
                    <button
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-wpx-md mt-5 w-full bg-gradient-to-br px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                        :disabled="depositBusy"
                        @click="startDeposit"
                    >
                        {{ depositBusy ? 'Ouverture de GeniusPay…' : 'Continuer vers GeniusPay' }}
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showTransfer"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showTransfer = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[90vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-wpx-white-soft text-lg font-bold">Transférer des WP</h2>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Le transfert est immédiat et enregistré dans le Grand Livre.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full"
                            @click="showTransfer = false"
                        >
                            ×
                        </button>
                    </div>

                    <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase"
                        >Téléphone ou e-mail Wasplex</label
                    >
                    <div class="mt-2 flex gap-2">
                        <input
                            v-model="recipientIdentifier"
                            type="text"
                            class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft rounded-wpx-md min-w-0 flex-1 border px-3 py-3 text-sm outline-none"
                            placeholder="+225… ou membre@email.com"
                            @input="recipient = null"
                        />
                        <button
                            type="button"
                            class="bg-wpx-blue/15 text-wpx-blue rounded-wpx-md px-3 text-xs font-bold disabled:opacity-50"
                            :disabled="recipientBusy || !recipientIdentifier.trim()"
                            @click="resolveRecipient"
                        >
                            {{ recipientBusy ? '…' : 'Vérifier' }}
                        </button>
                    </div>

                    <div v-if="recipient" class="border-wpx-success/25 bg-wpx-success/8 rounded-wpx-md mt-3 border p-3">
                        <p class="text-wpx-success-light text-sm font-bold">{{ recipient.display_name }}</p>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">{{ recipient.identifier_hint }}</p>
                    </div>

                    <template v-if="recipient">
                        <label class="text-wpx-muted-dark mt-5 block text-[11px] font-bold uppercase">Montant</label>
                        <div
                            class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-md mt-2 flex items-center border px-3"
                        >
                            <input
                                v-model.number="transferAmount"
                                type="number"
                                min="1"
                                :max="balance ?? 0"
                                inputmode="numeric"
                                class="text-wpx-white-soft min-w-0 flex-1 bg-transparent py-3.5 text-xl font-bold outline-none"
                                placeholder="100"
                            />
                            <span class="text-wpx-blue text-sm font-bold">WP</span>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 text-xs">Disponible : {{ availableLabel }} WP</p>
                    </template>

                    <p
                        v-if="transferError"
                        class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md mt-3 p-3 text-xs"
                    >
                        {{ transferError }}
                    </p>

                    <button
                        v-if="recipient"
                        type="button"
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md mt-5 w-full bg-gradient-to-br px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                        :disabled="transferBusy || !transferAmount || transferAmount <= 0"
                        @click="submitTransfer"
                    >
                        {{
                            transferBusy ? 'Transfert…' : `Confirmer ${numberFormatter.format(transferAmount ?? 0)} WP`
                        }}
                    </button>
                </div>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showWithdrawal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 sm:items-center"
                @click.self="showWithdrawal = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark w-full max-w-md rounded-t-3xl border p-5 sm:rounded-3xl"
                >
                    <div
                        class="bg-wpx-orange/12 text-wpx-orange flex h-12 w-12 items-center justify-center rounded-full text-xl"
                    >
                        ↑
                    </div>
                    <h2 class="text-wpx-white-soft mt-4 text-lg font-bold">Retrait en préparation</h2>
                    <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                        Votre Wallet est prêt pour le parcours de retrait, mais GeniusPay ne fournit pas encore dans
                        notre documentation un endpoint de payout vérifiable. Wasplex n’affichera jamais un faux
                        retrait.
                    </p>
                    <p class="text-wpx-muted-dark mt-3 text-xs leading-relaxed">
                        Dès qu’un rail de sortie réel sera disponible, le retrait intégrera KYC, destination vérifiée,
                        frais affichés avant validation et preuve de règlement.
                    </p>
                    <button
                        type="button"
                        class="bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md mt-5 w-full px-4 py-3 text-sm font-bold"
                        @click="showWithdrawal = false"
                    >
                        Compris
                    </button>
                </div>
            </div>
        </Teleport>
    </div>
</template>
