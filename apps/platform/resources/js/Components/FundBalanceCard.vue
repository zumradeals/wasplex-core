<script setup lang="ts">
import { ref } from 'vue';
import http from '@/lib/http';

type Balances = {
    wallet_available_minor: number;
    fund_balance_minor: number;
    unit: string;
    wp_to_xof: number;
};

defineProps<{ balances: Balances }>();
const emit = defineEmits<{ refresh: [] }>();

const open = ref(false);
const amount = ref<number | null>(null);
const busy = ref(false);
const error = ref('');

function format(value: number): string {
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);
}

async function fund(): Promise<void> {
    if (!amount.value || amount.value <= 0) return;
    busy.value = true;
    error.value = '';
    try {
        await http.post('/funds/balance/fund', {
            amount_minor: amount.value,
            idempotency_key: crypto.randomUUID(),
        });
        amount.value = null;
        open.value = false;
        emit('refresh');
    } catch {
        error.value = 'Le transfert n’a pas pu être effectué. Vérifie ton solde disponible.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl overflow-hidden border">
        <div class="from-wpx-blue/12 via-transparent to-wpx-gold/8 bg-gradient-to-br p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-extrabold tracking-[0.16em] uppercase">Mon Solde Fonds</p>
                    <div class="mt-1 flex items-baseline gap-2">
                        <strong class="text-wpx-white-soft text-2xl font-black">{{ format(balances.fund_balance_minor) }}</strong>
                        <span class="text-wpx-gold text-xs font-extrabold">WP</span>
                    </div>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">≈ {{ format(balances.fund_balance_minor) }} FCFA · 1 WP = 1 FCFA</p>
                </div>
                <div class="bg-wpx-navy-950/80 border-wpx-border-dark rounded-xl border px-3 py-2 text-right">
                    <p class="text-wpx-muted-dark text-[9px] uppercase">Wallet disponible</p>
                    <p class="text-wpx-white-soft mt-0.5 text-xs font-bold">{{ format(balances.wallet_available_minor) }} WP</p>
                </div>
            </div>

            <p class="text-wpx-muted-dark mt-3 text-xs leading-relaxed">
                Ce solde est réservé à Fonds. Tu gardes une vue claire de ce qui reste disponible dans ton Wallet et de ce qui est préparé pour tes engagements solidaires.
            </p>

            <button
                type="button"
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-40"
                :disabled="balances.wallet_available_minor <= 0"
                @click="open = !open"
            >
                {{ open ? 'Fermer' : 'Alimenter mon Solde Fonds' }}
            </button>

            <div v-if="open" class="bg-wpx-navy-950 border-wpx-border-dark mt-3 rounded-xl border p-3">
                <label class="text-wpx-muted-dark text-[11px] font-bold">Montant à transférer</label>
                <div class="mt-1.5 flex gap-2">
                    <input
                        v-model.number="amount"
                        type="number"
                        min="1"
                        :max="balances.wallet_available_minor"
                        placeholder="Ex. 5 000"
                        class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft min-w-0 flex-1 rounded-xl border px-3 py-2.5 text-sm"
                    />
                    <button
                        type="button"
                        class="bg-wpx-blue text-wpx-white-soft rounded-xl px-4 text-xs font-extrabold disabled:opacity-40"
                        :disabled="busy || !amount || amount <= 0 || amount > balances.wallet_available_minor"
                        @click="fund"
                    >
                        {{ busy ? '…' : 'Transférer' }}
                    </button>
                </div>
                <button
                    type="button"
                    class="text-wpx-cyan mt-2 text-[11px] font-bold"
                    @click="amount = balances.wallet_available_minor"
                >
                    Utiliser tout le disponible
                </button>
                <p v-if="error" class="text-wpx-danger mt-2 text-[11px]">{{ error }}</p>
            </div>
        </div>
    </section>
</template>
