<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import WasplexMark from '@/components/WasplexMark.vue';

type Wallet = {
    id: string;
    kind: 'user' | 'advertiser';
    unit: string;
    currency: string;
    status: string;
    balances: {
        available: number;
        reserved: number;
        pending: number;
        total: number;
        version: number;
    };
};

type Operation = {
    id: string;
    type: string;
    direction: 'credit' | 'debit' | 'internal';
    status: string;
    amount: number;
    description: string;
    reference: string;
    occurredAt: string;
};

type Deposit = {
    id: string;
    reference: string | null;
    amount: number;
    fee: number | null;
    status: string;
    providerStatus: string | null;
    checkoutUrl: string | null;
    createdAt: string | null;
};

const props = defineProps<{
    wallet: Wallet;
    operations: Operation[];
    deposits: Deposit[];
    provider: {
        name: string;
        environment: string;
        configured: boolean;
        minimum: number;
        maximum: number;
    };
    returnState: string;
}>();

const newIdempotencyKey = () =>
    `deposit:${globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;

const form = useForm({ amount_minor: 100000, idempotency_key: newIdempotencyKey() });
const isAdvertiser = computed(() => props.wallet.kind === 'advertiser');
const backUrl = computed(() => (isAdvertiser.value ? '/studio' : '/mon-espace'));
const depositUrl = computed(() => (isAdvertiser.value ? '/studio/wallet/depot' : '/wallet/depot'));

const money = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(
              new Date(value),
          )
        : '—';

const statusLabel = (status: string) =>
    ({
        credited: 'Crédité',
        awaiting_payment: 'Paiement attendu',
        creating_payment: 'Préparation',
        payment_detected: 'Détecté',
        confirmed: 'Confirmé',
        failed: 'Échec',
        cancelled: 'Annulé',
        expired: 'Expiré',
        unknown: 'À vérifier',
    })[status] ?? status;

const submitDeposit = () => {
    form.post(depositUrl.value, {
        preserveScroll: true,
        onError: () => {
            form.idempotency_key = newIdempotencyKey();
        },
    });
};
</script>

<template>
    <Head :title="isAdvertiser ? 'Budget annonceur' : 'Mon Wallet'" />
    <main class="min-h-screen bg-[#03101e] text-white">
        <div
            class="mx-auto min-h-screen border-x border-white/5 bg-wasplex-night shadow-2xl"
            :class="isAdvertiser ? 'max-w-6xl' : 'max-w-md'"
        >
            <header class="border-b border-white/8 px-5 py-5 sm:px-7">
                <div class="flex items-center justify-between gap-4">
                    <WasplexMark />
                    <Link :href="backUrl" class="text-sm font-bold text-white/45 hover:text-white"
                        >Retour</Link
                    >
                </div>
            </header>

            <section class="px-5 py-7 sm:px-7 lg:px-10">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-xs font-black tracking-[0.22em] text-wasplex-cyan uppercase">
                            {{ isAdvertiser ? 'Budget de votre activité' : 'Valeur disponible' }}
                        </p>
                        <h1 class="mt-2 text-3xl font-black">
                            {{ isAdvertiser ? 'Wallet annonceur' : 'Mon Wallet' }}
                        </h1>
                        <p class="mt-2 text-sm text-white/40">
                            Chaque mouvement est expliqué par le Grand Livre Wasplex.
                        </p>
                    </div>
                    <span
                        class="rounded-full border border-wasplex-gold/25 bg-wasplex-gold/10 px-3 py-1 text-xs font-black text-wasplex-gold"
                    >
                        GENIUSPAY SANDBOX
                    </span>
                </div>

                <div
                    v-if="returnState"
                    class="mt-5 rounded-2xl border border-wasplex-cyan/20 bg-wasplex-cyan/[0.07] p-4 text-sm text-white/70"
                >
                    Le retour du paiement a bien été reçu. Le crédit apparaîtra uniquement après
                    confirmation sécurisée de GeniusPay.
                </div>

                <article
                    class="mt-7 overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#087cf0] via-wasplex-blue to-[#0b43a7] p-6 shadow-glow sm:p-8"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-white/65">Solde disponible</p>
                            <p class="mt-2 text-5xl font-black tracking-tight">
                                {{ money(wallet.balances.available) }}
                                <span class="text-xl text-white/65">WP</span>
                            </p>
                            <p class="mt-2 text-sm text-white/60">
                                ≈ {{ money(wallet.balances.available) }} FCFA
                            </p>
                        </div>
                        <div
                            class="grid size-12 place-items-center rounded-2xl bg-white/15 text-xl font-black"
                        >
                            W
                        </div>
                    </div>
                    <div class="mt-8 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-2xl bg-black/15 p-4">
                            <p class="text-white/50">Réservé</p>
                            <p class="mt-1 font-black">{{ money(wallet.balances.reserved) }} WP</p>
                        </div>
                        <div class="rounded-2xl bg-black/15 p-4">
                            <p class="text-white/50">En attente</p>
                            <p class="mt-1 font-black">{{ money(wallet.balances.pending) }} WP</p>
                        </div>
                    </div>
                </article>

                <div
                    class="mt-7 grid gap-6"
                    :class="isAdvertiser ? 'lg:grid-cols-[0.8fr_1.2fr]' : ''"
                >
                    <section class="rounded-3xl border border-white/8 bg-white/[0.045] p-5 sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-black">Déposer des fonds</h2>
                                <p class="mt-1 text-sm text-white/40">
                                    Mobile Money sur la page sécurisée GeniusPay.
                                </p>
                            </div>
                            <span
                                class="rounded-full bg-emerald-400/10 px-2.5 py-1 text-[0.65rem] font-black text-emerald-300"
                                >AUTOMATIQUE</span
                            >
                        </div>

                        <div
                            v-if="!provider.configured"
                            class="mt-5 rounded-2xl border border-amber-300/20 bg-amber-300/[0.06] p-4 text-sm leading-6 text-amber-100"
                        >
                            La sandbox GeniusPay doit encore être configurée sur le serveur. Aucun
                            secret n’est demandé dans cette interface.
                        </div>

                        <form class="mt-5" @submit.prevent="submitDeposit">
                            <label for="amount" class="text-sm font-bold text-white/70"
                                >Montant en FCFA</label
                            >
                            <div class="relative mt-2">
                                <input
                                    id="amount"
                                    v-model.number="form.amount_minor"
                                    class="wasplex-input pr-20 text-lg font-black"
                                    type="number"
                                    :min="provider.minimum"
                                    :max="provider.maximum"
                                    step="100"
                                    required
                                />
                                <span
                                    class="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-black text-white/35"
                                    >FCFA</span
                                >
                            </div>
                            <p v-if="form.errors.amount_minor" class="mt-2 text-sm text-red-300">
                                {{ form.errors.amount_minor }}
                            </p>
                            <button
                                class="mt-4 w-full wasplex-button-primary"
                                type="submit"
                                :disabled="form.processing || !provider.configured"
                            >
                                {{
                                    form.processing
                                        ? 'Connexion sécurisée…'
                                        : 'Continuer avec GeniusPay'
                                }}
                            </button>
                            <p class="mt-3 text-center text-xs leading-5 text-white/30">
                                Aucun crédit n’est créé par le navigateur. Wasplex attend le webhook
                                signé puis revérifie le paiement.
                            </p>
                        </form>
                    </section>

                    <section class="rounded-3xl border border-white/8 bg-white/[0.035] p-5 sm:p-6">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-black">Dépôts récents</h2>
                            <span class="text-xs text-white/30"
                                >{{ deposits.length }} affiché(s)</span
                            >
                        </div>
                        <div v-if="deposits.length" class="mt-4 divide-y divide-white/8">
                            <article
                                v-for="deposit in deposits"
                                :key="deposit.id"
                                class="flex items-center justify-between gap-4 py-4"
                            >
                                <div class="min-w-0">
                                    <p class="truncate font-bold">
                                        {{ money(deposit.amount) }} FCFA
                                    </p>
                                    <p class="mt-1 truncate text-xs text-white/35">
                                        {{ deposit.reference ?? 'Référence en préparation' }} ·
                                        {{ date(deposit.createdAt) }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-black"
                                        :class="
                                            deposit.status === 'credited'
                                                ? 'bg-emerald-400/10 text-emerald-300'
                                                : deposit.status === 'unknown' ||
                                                    deposit.status === 'failed'
                                                  ? 'bg-red-400/10 text-red-300'
                                                  : 'bg-wasplex-cyan/10 text-wasplex-cyan'
                                        "
                                        >{{ statusLabel(deposit.status) }}</span
                                    ><a
                                        v-if="deposit.checkoutUrl"
                                        :href="deposit.checkoutUrl"
                                        class="mt-2 block text-xs font-bold text-wasplex-cyan"
                                        >Reprendre</a
                                    >
                                </div>
                            </article>
                        </div>
                        <p
                            v-else
                            class="mt-6 rounded-2xl bg-black/15 p-5 text-center text-sm text-white/35"
                        >
                            Aucun dépôt pour le moment.
                        </p>
                    </section>
                </div>

                <section class="mt-6 rounded-3xl border border-white/8 bg-white/[0.035] p-5 sm:p-6">
                    <h2 class="text-lg font-black">Historique du Wallet</h2>
                    <div v-if="operations.length" class="mt-4 divide-y divide-white/8">
                        <article
                            v-for="operation in operations"
                            :key="operation.id"
                            class="flex items-center justify-between gap-4 py-4"
                        >
                            <div>
                                <p class="font-bold">{{ operation.description }}</p>
                                <p class="mt-1 text-xs text-white/35">
                                    {{ date(operation.occurredAt) }} · {{ operation.reference }}
                                </p>
                            </div>
                            <p
                                class="font-black"
                                :class="
                                    operation.direction === 'credit'
                                        ? 'text-emerald-300'
                                        : operation.direction === 'debit'
                                          ? 'text-red-300'
                                          : 'text-wasplex-gold'
                                "
                            >
                                {{
                                    operation.direction === 'credit'
                                        ? '+'
                                        : operation.direction === 'debit'
                                          ? '−'
                                          : ''
                                }}{{ money(operation.amount) }} WP
                            </p>
                        </article>
                    </div>
                    <p v-else class="mt-5 text-sm text-white/35">
                        Les prochaines opérations apparaîtront ici.
                    </p>
                </section>
            </section>
        </div>
    </main>
</template>
