<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import { useComingSoon } from '@/lib/comingSoon';
import type { AuthShared } from '@/types/identity';

interface HistoryEntry {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    created_at: string | null;
}

const props = defineProps<{ accountLabel?: string; phoneNumber?: string | null }>();
const emit = defineEmits<{ goToSubscription: [] }>();

const balance = ref<number | null>(null);
const history = ref<HistoryEntry[]>([]);
const loading = ref(true);
const historyOpen = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const { notice, announce } = useComingSoon();

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [balanceRes, historyRes] = await Promise.all([http.get('/me/wallet'), http.get('/me/wallet/history')]);
        balance.value = balanceRes.data.balance_minor;
        history.value = historyRes.data.history.data ?? historyRes.data.history;
    } finally {
        loading.value = false;
    }
}

function describe(entry: HistoryEntry): string {
    if (entry.description) {
        return entry.description;
    }

    return entry.type ?? (entry.direction === 'credit' ? 'Crédit' : 'Débit');
}

function sumCreditsSince(cutoff: Date): number {
    return history.value
        .filter(
            (entry) =>
                entry.direction === 'credit' && entry.created_at !== null && new Date(entry.created_at) >= cutoff,
        )
        .reduce((total, entry) => total + entry.amount_minor, 0);
}

const todayTotal = computed(() => {
    const startOfDay = new Date();
    startOfDay.setHours(0, 0, 0, 0);

    return sumCreditsSince(startOfDay);
});

const monthTotal = computed(() => {
    const startOfMonth = new Date();
    startOfMonth.setDate(1);
    startOfMonth.setHours(0, 0, 0, 0);

    return sumCreditsSince(startOfMonth);
});

defineExpose({ load });

onMounted(load);

const page = usePage<{ auth: AuthShared }>();

// docs/07 §23 / docs/chantiers/P011-CHANTIER.md : le solde se met à jour en
// direct (déblocage antifraude, autre appareil) sans attendre un rechargement
// déclenché par la réponse HTTP de l'action qui vient de créditer.
useEcho(`wallet.${page.props.auth.account.id}`, '.wallet.balance.changed', load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <!-- Carte de solde animée (docs/00 §15) -->
        <div
            class="rounded-wpx-xl from-wpx-orange to-wpx-gold wpx-motion-safe relative animate-[wpxGlow_3.2s_ease-in-out_infinite] overflow-hidden bg-gradient-to-br p-5"
        >
            <div
                class="wpx-motion-safe pointer-events-none absolute inset-y-0 w-16 animate-[wpxShine_3.6s_ease-in-out_infinite] bg-gradient-to-r from-white/0 via-white/35 to-white/0"
            />
            <p class="text-wpx-navy-950/65 text-[11px] font-extrabold tracking-widest">WASPLEX</p>
            <p v-if="props.accountLabel" class="text-wpx-navy-950 mt-0.5 text-sm font-bold">{{ props.accountLabel }}</p>
            <p class="text-wpx-navy-950/65 mt-4 text-xs font-bold">Solde disponible</p>
            <p class="text-wpx-navy-950 mt-0.5 text-4xl font-extrabold tabular-nums">
                <span v-if="loading">…</span>
                <span v-else>{{ numberFormatter.format(balance ?? 0) }} <span class="text-lg">WP</span></span>
            </p>
            <p class="text-wpx-navy-950/60 mt-0.5 text-xs">≈ {{ numberFormatter.format(balance ?? 0) }} FCFA</p>
            <p v-if="props.phoneNumber" class="text-wpx-navy-950/60 mt-3 font-mono text-xs">{{ props.phoneNumber }}</p>
        </div>

        <!-- Actions (sans backend self-service aujourd'hui — inertes) -->
        <div class="grid grid-cols-3 gap-2.5">
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex flex-col items-center gap-2 border py-3.5"
                @click="announce"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M12 19V5M6 11l6-6 6 6"
                        stroke="#FF9A3D"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <span class="text-wpx-orange text-xs font-bold">Retirer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex flex-col items-center gap-2 border py-3.5"
                @click="announce"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M12 5v14M6 13l6 6 6-6"
                        stroke="#42D392"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <span class="text-wpx-success text-xs font-bold">Déposer</span>
            </button>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex flex-col items-center gap-2 border py-3.5"
                @click="announce"
            >
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M4 8h13M13 4l4 4-4 4"
                        stroke="#4FA3FF"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M20 16H7M11 12l-4 4 4 4"
                        stroke="#4FA3FF"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <span class="text-wpx-blue text-xs font-bold">Transférer</span>
            </button>
        </div>
        <p v-if="notice" class="text-wpx-muted-dark -mt-2 text-center text-xs">{{ notice }}</p>

        <!-- Statistiques (réelles, dérivées de l'historique chargé) -->
        <div class="grid grid-cols-3 gap-2.5">
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-success text-base font-bold">{{ numberFormatter.format(todayTotal) }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Aujourd'hui</p>
            </div>
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center">
                <p class="text-wpx-blue text-base font-bold">{{ numberFormatter.format(monthTotal) }}</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Ce mois</p>
            </div>
            <button
                type="button"
                class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-3 text-center"
                @click="announce"
            >
                <p class="text-wpx-gold text-base font-bold">—</p>
                <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Carte Wasplex</p>
            </button>
        </div>

        <p class="text-wpx-muted-dark text-[11px] font-bold tracking-wide uppercase">Nos offres</p>

        <button
            type="button"
            class="bg-wpx-navy-750 border-wpx-border-dark rounded-wpx-md flex items-center gap-3 border p-3.5 text-left"
            @click="emit('goToSubscription')"
        >
            <span class="bg-wpx-blue/18 rounded-wpx-sm flex h-9.5 w-9.5 shrink-0 items-center justify-center">
                <svg width="18" height="18" viewBox="0 0 24 24"><path d="M12 2l6 6-6 14-6-14z" fill="#4FA3FF" /></svg>
            </span>
            <span class="flex-1">
                <span class="text-wpx-white-soft block text-sm font-bold">Mon abonnement</span>
                <span class="text-wpx-muted-dark block text-[11px]">Plus de quota et de gains</span>
            </span>
            <span class="bg-wpx-navy-850 rounded-wpx-sm text-wpx-blue px-3 py-1.5 text-xs font-bold whitespace-nowrap">
                Voir
            </span>
        </button>

        <button
            type="button"
            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border p-4 text-left"
            @click="announce"
        >
            <div class="flex items-center justify-between">
                <span class="text-wpx-orange text-sm font-bold">Carte Wasplex</span>
                <span class="bg-wpx-orange/15 text-wpx-orange rounded-wpx-sm px-3 py-1.5 text-xs font-bold"
                    >+ Acquérir</span
                >
            </div>
            <div class="border-wpx-border-dark rounded-wpx-md mt-3 border border-dashed p-4 text-center">
                <p class="text-wpx-white-soft text-sm font-bold">Cashback et avantages chaque mois</p>
            </div>
        </button>

        <p class="text-wpx-muted-dark text-[11px] font-bold tracking-wide uppercase">Historique</p>

        <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md border">
            <button
                type="button"
                class="flex w-full items-center justify-between p-3.5"
                @click="historyOpen = !historyOpen"
            >
                <span class="flex items-center gap-2.5">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path
                            d="M6 18L18 6M6 6h12v12"
                            stroke="#42D392"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span class="text-wpx-white-soft text-sm font-bold">Historique publicités</span>
                </span>
                <span class="flex items-center gap-2">
                    <span class="bg-wpx-success/15 text-wpx-success rounded-full px-2 py-0.5 text-[11px] font-bold">
                        {{ history.length }}
                    </span>
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 24 24"
                        fill="none"
                        :class="historyOpen ? 'rotate-180' : ''"
                    >
                        <path
                            d="M6 9l6 6 6-6"
                            stroke="#A9B7C8"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                </span>
            </button>
            <div v-if="historyOpen" class="border-wpx-navy-750 border-t px-3.5 pb-3.5">
                <p v-if="loading" class="text-wpx-muted-dark pt-3 text-sm">Chargement…</p>
                <ul v-else class="flex flex-col gap-2 pt-3">
                    <li
                        v-for="entry in history"
                        :key="entry.id"
                        class="border-wpx-border-dark flex items-center justify-between border-b pb-2 last:border-0"
                    >
                        <div>
                            <p class="text-wpx-white-soft text-sm">{{ describe(entry) }}</p>
                            <p class="text-wpx-muted-dark text-[11px]">
                                {{ entry.created_at ? new Date(entry.created_at).toLocaleString('fr-FR') : '' }}
                            </p>
                        </div>
                        <span
                            class="text-sm font-semibold"
                            :class="entry.direction === 'credit' ? 'text-wpx-success-light' : 'text-wpx-danger-light'"
                        >
                            {{ entry.direction === 'credit' ? '+' : '−'
                            }}{{ numberFormatter.format(entry.amount_minor) }} WP
                        </span>
                    </li>
                    <li v-if="history.length === 0" class="text-wpx-muted-dark text-sm">
                        Aucun mouvement pour le moment.
                    </li>
                </ul>
            </div>
        </div>

        <button
            type="button"
            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-md flex w-full items-center justify-between border p-3.5"
            @click="announce"
        >
            <span class="flex items-center gap-2.5">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M4 16l5-5 4 4 7-7"
                        stroke="#FF9A3D"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
                <span class="text-wpx-white-soft text-sm font-bold">Historique carte Wasplex</span>
            </span>
            <span class="flex items-center gap-2">
                <span class="bg-wpx-navy-750 text-wpx-muted-dark rounded-full px-2 py-0.5 text-[11px] font-bold"
                    >—</span
                >
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                    <path
                        d="M6 9l6 6 6-6"
                        stroke="#A9B7C8"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </span>
        </button>
    </div>
</template>
