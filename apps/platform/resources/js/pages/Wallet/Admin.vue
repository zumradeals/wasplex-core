<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

type Deposit = {
    id: string;
    walletKind: string;
    walletId: string;
    reference: string | null;
    amount: number;
    fee: number | null;
    status: string;
    providerStatus: string | null;
    createdAt: string | null;
};

defineProps<{
    metrics: { wallets: number; deposits: number; credited: number; anomalies: number };
    deposits: Deposit[];
    sandbox: boolean;
}>();

const money = (value: number) => new Intl.NumberFormat('fr-FR').format(value);
const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(
              new Date(value),
          )
        : '—';
</script>

<template>
    <Head title="Supervision Wallet" />
    <main class="min-h-screen bg-[#030a12] text-white">
        <section class="mx-auto max-w-7xl px-6 py-8 lg:px-10 lg:py-12">
            <header
                class="flex flex-wrap items-end justify-between gap-6 border-b border-white/8 pb-7"
            >
                <div>
                    <WasplexMark />
                    <p
                        class="mt-8 text-xs font-black tracking-[0.22em] text-wasplex-orange uppercase"
                    >
                        Console fondateur
                    </p>
                    <h1 class="mt-2 text-4xl font-black">Supervision Wallet</h1>
                    <p class="mt-2 text-white/40">
                        Observer, rapprocher et expliquer — sans modifier un solde.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        class="rounded-full border border-wasplex-gold/20 bg-wasplex-gold/10 px-3 py-1 text-xs font-black text-wasplex-gold"
                        >SANDBOX</span
                    ><Link
                        href="/administration"
                        class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold"
                        >Retour</Link
                    >
                </div>
            </header>

            <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="metric in [
                        { label: 'Wallets', value: metrics.wallets },
                        { label: 'Dépôts', value: metrics.deposits },
                        { label: 'Crédité', value: `${money(metrics.credited)} WP` },
                        { label: 'À vérifier', value: metrics.anomalies },
                    ]"
                    :key="metric.label"
                    class="rounded-2xl border border-white/8 bg-white/[0.035] p-5"
                >
                    <p class="text-xs font-semibold text-white/35">{{ metric.label }}</p>
                    <p class="mt-4 text-3xl font-black">{{ metric.value }}</p>
                </article>
            </div>

            <section class="mt-7 overflow-hidden rounded-3xl border border-white/8 bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-white/8 p-6">
                    <div>
                        <h2 class="text-xl font-black">Dépôts GeniusPay</h2>
                        <p class="mt-1 text-sm text-white/35">
                            Le parcours normal est automatique. Une anomalie appelle une
                            investigation, pas un crédit manuel.
                        </p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-black/15 text-xs text-white/35 uppercase">
                            <tr>
                                <th class="px-6 py-4">Référence</th>
                                <th class="px-4 py-4">Wallet</th>
                                <th class="px-4 py-4">Montant</th>
                                <th class="px-4 py-4">Statut</th>
                                <th class="px-6 py-4">Créé</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/8">
                            <tr v-for="deposit in deposits" :key="deposit.id">
                                <td class="px-6 py-4 font-mono text-xs">
                                    {{ deposit.reference ?? deposit.id }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="font-bold">{{ deposit.walletKind }}</span>
                                    <p class="mt-1 text-xs text-white/30">{{ deposit.walletId }}</p>
                                </td>
                                <td class="px-4 py-4 font-black">
                                    {{ money(deposit.amount) }} FCFA
                                </td>
                                <td class="px-4 py-4">
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
                                        >{{ deposit.status }}</span
                                    >
                                </td>
                                <td class="px-6 py-4 text-white/40">
                                    {{ date(deposit.createdAt) }}
                                </td>
                            </tr>
                            <tr v-if="!deposits.length">
                                <td colspan="5" class="px-6 py-12 text-center text-white/35">
                                    Aucun dépôt enregistré.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>
</template>
