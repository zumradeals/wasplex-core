<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

type Balances = {
    wallet_available_minor: number;
    fund_balance_minor: number;
};

type Contribution = {
    id: string;
    source: 'wallet' | 'fund_balance';
    amount_minor: number;
    created_at: string;
};

type Wish = {
    id: string;
    required_personal_contribution_minor: number | null;
    personal_contribution_minor: number;
    personal_contribution_remaining_minor: number | null;
    personal_contribution_progress_percent: number;
    personal_contribution_completed_at: string | null;
    personal_contributions: Contribution[];
};

const props = defineProps<{ wish: Wish; balances: Balances }>();
const emit = defineEmits<{ refresh: [] }>();

const open = ref(false);
const source = ref<'fund_balance' | 'wallet'>('fund_balance');
const amount = ref<number | null>(null);
const busy = ref(false);
const error = ref('');

const remaining = computed(() => props.wish.personal_contribution_remaining_minor ?? 0);
const sourceBalance = computed(() =>
    source.value === 'fund_balance' ? props.balances.fund_balance_minor : props.balances.wallet_available_minor,
);
const complete = computed(() => remaining.value === 0 && (props.wish.required_personal_contribution_minor ?? 0) > 0);

function format(value: number | null): string {
    if (value === null) return 'À définir';
    return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(value);
}

function useRemaining(): void {
    amount.value = Math.min(remaining.value, sourceBalance.value);
}

async function contribute(): Promise<void> {
    if (!amount.value || amount.value <= 0) return;
    busy.value = true;
    error.value = '';
    try {
        await http.post(`/funds/wishes/${props.wish.id}/contributions`, {
            amount_minor: amount.value,
            source: source.value,
            idempotency_key: crypto.randomUUID(),
        });
        amount.value = null;
        open.value = false;
        emit('refresh');
    } catch {
        error.value = 'Impossible d’enregistrer cet apport. Vérifie le montant et le solde choisi.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div v-if="wish.required_personal_contribution_minor" class="border-wpx-border-dark mt-3 border-t pt-3">
        <div class="flex items-end justify-between gap-3">
            <div>
                <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Mon apport personnel</p>
                <p class="text-wpx-white-soft mt-1 text-xs font-extrabold">
                    {{ format(wish.personal_contribution_minor) }} / {{ format(wish.required_personal_contribution_minor) }} FCFA
                </p>
            </div>
            <span v-if="complete" class="bg-wpx-success/10 text-wpx-success-light rounded-full px-2 py-1 text-[10px] font-bold">Complet ✓</span>
            <span v-else class="text-wpx-gold text-[11px] font-bold">{{ format(remaining) }} restant</span>
        </div>

        <div class="bg-wpx-navy-950 mt-2 h-2 overflow-hidden rounded-full">
            <div
                class="from-wpx-blue to-wpx-cyan h-full rounded-full bg-gradient-to-r transition-all"
                :style="{ width: `${wish.personal_contribution_progress_percent}%` }"
            ></div>
        </div>
        <div class="text-wpx-muted-dark mt-1 flex justify-between text-[9px]">
            <span>Progression</span><span>{{ wish.personal_contribution_progress_percent }}%</span>
        </div>

        <button
            v-if="!complete"
            type="button"
            class="text-wpx-cyan mt-2.5 text-xs font-extrabold"
            @click="open = !open"
        >
            {{ open ? 'Fermer' : 'Ajouter à mon apport ›' }}
        </button>

        <div v-if="open && !complete" class="bg-wpx-navy-950 border-wpx-border-dark mt-3 rounded-xl border p-3">
            <p class="text-wpx-white-soft text-xs font-bold">Choisir d’où vient mon apport</p>
            <div class="mt-2 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-xl border p-2.5 text-left"
                    :class="source === 'fund_balance' ? 'border-wpx-cyan bg-wpx-cyan/8' : 'border-wpx-border-dark bg-wpx-navy-850'"
                    @click="source = 'fund_balance'"
                >
                    <span class="text-wpx-white-soft block text-[11px] font-bold">Solde Fonds</span>
                    <span class="text-wpx-muted-dark mt-0.5 block text-[10px]">{{ format(balances.fund_balance_minor) }} WP</span>
                </button>
                <button
                    type="button"
                    class="rounded-xl border p-2.5 text-left"
                    :class="source === 'wallet' ? 'border-wpx-cyan bg-wpx-cyan/8' : 'border-wpx-border-dark bg-wpx-navy-850'"
                    @click="source = 'wallet'"
                >
                    <span class="text-wpx-white-soft block text-[11px] font-bold">Wallet</span>
                    <span class="text-wpx-muted-dark mt-0.5 block text-[10px]">{{ format(balances.wallet_available_minor) }} WP</span>
                </button>
            </div>
            <div class="mt-2 flex gap-2">
                <input
                    v-model.number="amount"
                    type="number"
                    min="1"
                    :max="Math.min(remaining, sourceBalance)"
                    placeholder="Montant"
                    class="bg-wpx-navy-850 border-wpx-border-dark text-wpx-white-soft min-w-0 flex-1 rounded-xl border px-3 py-2.5 text-sm"
                />
                <button
                    type="button"
                    class="bg-wpx-blue text-wpx-white-soft rounded-xl px-4 text-xs font-extrabold disabled:opacity-40"
                    :disabled="busy || !amount || amount <= 0 || amount > remaining || amount > sourceBalance"
                    @click="contribute"
                >
                    {{ busy ? '…' : 'Valider' }}
                </button>
            </div>
            <button type="button" class="text-wpx-cyan mt-2 text-[10px] font-bold" @click="useRemaining">
                Mettre le maximum possible
            </button>
            <p v-if="sourceBalance <= 0" class="text-wpx-gold mt-2 text-[10px]">Ce solde est vide. Choisis l’autre source ou alimente ton Solde Fonds.</p>
            <p v-if="error" class="text-wpx-danger mt-2 text-[10px]">{{ error }}</p>
        </div>

        <div v-if="wish.personal_contributions?.length" class="mt-2.5">
            <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">Derniers apports</p>
            <div class="mt-1 space-y-1">
                <div v-for="item in wish.personal_contributions.slice(0, 3)" :key="item.id" class="flex justify-between text-[10px]">
                    <span class="text-wpx-muted-dark">{{ item.source === 'fund_balance' ? 'Solde Fonds' : 'Wallet' }}</span>
                    <span class="text-wpx-white-soft font-bold">+ {{ format(item.amount_minor) }} WP</span>
                </div>
            </div>
        </div>
    </div>
</template>
