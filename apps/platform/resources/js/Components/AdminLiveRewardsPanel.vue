<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Dashboard {
    rewards_enabled: boolean;
    antifraud_mode: 'observe' | 'enforce';
    active_sponsored_lives: number;
    active_rewarded_seats: number;
    captured_blocks: number;
    held_blocks: number;
    rejected_blocks: number;
    member_rewards_minor: number;
    wasplex_revenue_minor: number;
    gross_consumed_minor: number;
    pending_review_minor: number;
    pending_review_gross_minor: number;
    pending_holds: number;
    risk_events_24h: number;
    critical_risk_events_24h: number;
}

interface Hold {
    id: string;
    live_id: string;
    live_title: string | null;
    account_id: string;
    block_index: number;
    reward_minor: number;
    gross_amount_minor: number;
    status: string;
    risk_codes: string[];
    evidence: Record<string, number>;
    opened_at: string;
}

const dashboard = ref<Dashboard | null>(null);
const holds = ref<Hold[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const numberFormatter = new Intl.NumberFormat('fr-FR');

const reasonLabels: Record<string, string> = {
    heartbeat_rate_abuse: 'Rythme de présence inhabituel',
    heartbeat_gap: 'Rupture de continuité',
    session_changed: 'Session authentifiée changée',
    device_changed: 'Appareil de session changé',
    concurrent_rewarded_live: 'Présence rémunérée concurrente',
    advertiser_self_reward: 'Auto-rémunération annonceur',
    risk_signal_threshold: 'Plusieurs signaux à vérifier',
};

function money(value: number): string {
    return `${numberFormatter.format(value)} WP`;
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [{ data: dashboardData }, { data: holdData }] = await Promise.all([
            http.get('/admin/live/dashboard'),
            http.get('/admin/live/holds'),
        ]);
        dashboard.value = dashboardData.dashboard;
        holds.value = holdData.holds ?? [];
    } catch (cause) {
        error.value =
            (cause as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger le pilotage du Live rémunéré.';
    } finally {
        loading.value = false;
    }
}

async function resolve(hold: Hold, action: 'release' | 'reject'): Promise<void> {
    const promptText = action === 'release'
        ? 'Note de vérification avant versement (optionnelle) :'
        : 'Motif du refus (optionnel) :';
    const note = window.prompt(promptText) ?? undefined;
    busy.value = hold.id;
    error.value = null;
    try {
        await http.post(`/admin/live/holds/${hold.id}/${action}`, { note });
        await load();
    } catch (cause) {
        error.value =
            (cause as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'La décision n’a pas pu être enregistrée.';
    } finally {
        busy.value = null;
    }
}

onMounted(load);
</script>

<template>
    <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border">
        <div class="border-wpx-border-dark flex flex-col gap-3 border-b px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-wpx-white-soft text-base font-extrabold">Live rémunéré · Production</h2>
                <p class="text-wpx-muted-dark mt-1 text-xs">Santé économique, retenues antifraude et décisions humaines.</p>
            </div>
            <button type="button" class="border-wpx-border-dark text-wpx-white-soft rounded-wpx-md border px-3 py-2 text-xs font-bold" @click="load">Actualiser</button>
        </div>

        <p v-if="error" class="bg-wpx-danger/15 text-wpx-danger m-4 rounded-xl p-3 text-xs">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark p-5 text-sm">Chargement…</p>

        <template v-else-if="dashboard">
            <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Récompenses</p>
                    <p class="mt-1 text-sm font-extrabold" :class="dashboard.rewards_enabled ? 'text-emerald-300' : 'text-wpx-danger'">{{ dashboard.rewards_enabled ? 'Actives' : 'Suspendues' }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[10px]">Antifraude : {{ dashboard.antifraud_mode }}</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">En direct</p>
                    <p class="text-wpx-white-soft mt-1 text-lg font-extrabold">{{ dashboard.active_sponsored_lives }}</p>
                    <p class="text-wpx-muted-dark text-[10px]">{{ dashboard.active_rewarded_seats }} places rémunérées actives</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Versé</p>
                    <p class="text-wpx-gold mt-1 text-lg font-extrabold">{{ money(dashboard.member_rewards_minor) }}</p>
                    <p class="text-wpx-muted-dark text-[10px]">membres · Wasplex {{ money(dashboard.wasplex_revenue_minor) }}</p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-3">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">À examiner</p>
                    <p class="mt-1 text-lg font-extrabold" :class="dashboard.pending_holds ? 'text-orange-300' : 'text-emerald-300'">{{ dashboard.pending_holds }}</p>
                    <p class="text-wpx-muted-dark text-[10px]">{{ money(dashboard.pending_review_minor) }} membres en attente</p>
                </div>
            </div>

            <div class="border-wpx-border-dark grid gap-2 border-y p-4 text-xs sm:grid-cols-3">
                <p class="text-wpx-muted-dark">Blocs : <strong class="text-wpx-white-soft">{{ dashboard.captured_blocks }} payés</strong> · {{ dashboard.held_blocks }} retenus · {{ dashboard.rejected_blocks }} refusés</p>
                <p class="text-wpx-muted-dark">Signaux 24 h : <strong class="text-wpx-white-soft">{{ dashboard.risk_events_24h }}</strong> · {{ dashboard.critical_risk_events_24h }} critiques</p>
                <p class="text-wpx-muted-dark">Brut en revue : <strong class="text-wpx-white-soft">{{ money(dashboard.pending_review_gross_minor) }}</strong></p>
            </div>

            <div class="p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-wpx-white-soft text-sm font-extrabold">Gains en vérification</h3>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">Le membre n’est pas payé tant que la décision n’est pas prise.</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-bold" :class="holds.length ? 'bg-orange-400/15 text-orange-200' : 'bg-emerald-400/15 text-emerald-200'">{{ holds.length ? `${holds.length} dossier(s)` : 'Rien à examiner' }}</span>
                </div>

                <div v-if="holds.length" class="space-y-3">
                    <article v-for="hold in holds" :key="hold.id" class="border-wpx-border-dark bg-wpx-navy-950 rounded-xl border p-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft text-sm font-extrabold">{{ hold.live_title ?? 'Live rémunéré' }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-[11px]">Bloc {{ hold.block_index }} · {{ money(hold.reward_minor) }} membre · {{ money(hold.gross_amount_minor) }} brut réservé</p>
                                <p class="mt-2 text-xs font-bold text-orange-200">{{ reasonLabels[hold.risk_codes?.[0]] ?? 'Contrôle de sécurité requis' }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                                <button type="button" class="rounded-lg bg-emerald-400 px-3 py-2 text-xs font-extrabold text-slate-950 disabled:opacity-50" :disabled="busy === hold.id" @click="resolve(hold, 'release')">Verser</button>
                                <button type="button" class="border-wpx-danger/50 text-wpx-danger rounded-lg border px-3 py-2 text-xs font-extrabold disabled:opacity-50" :disabled="busy === hold.id" @click="resolve(hold, 'reject')">Refuser</button>
                            </div>
                        </div>
                        <details class="border-wpx-border-dark text-wpx-muted-dark mt-3 rounded-lg border p-3 text-[10px]">
                            <summary class="cursor-pointer font-bold">Détails techniques réservés à l’administration</summary>
                            <div class="mt-2 space-y-1 font-mono">
                                <p>signaux : {{ hold.risk_codes.join(', ') || '—' }}</p>
                                <p>compte : {{ hold.account_id }}</p>
                                <p>ouvert : {{ new Date(hold.opened_at).toLocaleString('fr-FR') }}</p>
                            </div>
                        </details>
                    </article>
                </div>
                <p v-else class="text-wpx-muted-dark py-6 text-center text-xs">Aucun gain Live n’est actuellement retenu.</p>
            </div>
        </template>
    </section>
</template>
