<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Grant {
    id: string;
    account_id: string;
}

interface LedgerTransaction {
    id: string;
    status: string;
}

const healthy = ref<boolean | null>(null);
const ledgerBalanced = ref<boolean | null>(null);
const grants = ref<Grant[]>([]);
const transactions = ref<LedgerTransaction[]>([]);
const loading = ref(true);

const adminAccountsCount = computed(() => new Set(grants.value.map((grant) => grant.account_id)).size);
const pendingApprovals = computed(
    () => transactions.value.filter((transaction) => transaction.status === 'pending_approval').length,
);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [summaryRes, grantsRes, transactionsRes] = await Promise.allSettled([
            http.get('/admin/dashboard/summary'),
            http.get('/admin/capabilities'),
            http.get('/admin/ledger/transactions'),
        ]);

        ledgerBalanced.value =
            summaryRes.status === 'fulfilled' ? Boolean(summaryRes.value.data.ledger.is_balanced) : null;
        grants.value = grantsRes.status === 'fulfilled' ? grantsRes.value.data.grants : [];
        transactions.value = transactionsRes.status === 'fulfilled' ? transactionsRes.value.data.transactions : [];
    } finally {
        loading.value = false;
    }

    try {
        const { data } = await http.get('/health');
        healthy.value = data.status === 'ok';
    } catch {
        healthy.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="text-wpx-white-soft mx-auto flex max-w-5xl flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">Audit & sécurité</p>
            <h2 class="mt-1 text-xl font-extrabold">Savoir si Wasplex est protégé.</h2>
            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                Cette vue résume les protections importantes. Les journaux d’audit détaillés seront ajoutés
                progressivement, sans supprimer les traces déjà conservées par les modules financiers et d’autorisation.
            </p>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <section class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-4">
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Infrastructure</p>
                    <p class="mt-2 text-lg font-extrabold" :class="healthy ? 'text-wpx-success' : 'text-wpx-danger'">
                        {{ healthy ? 'Opérationnelle' : 'À vérifier' }}
                    </p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Grand Livre</p>
                    <p
                        class="mt-2 text-lg font-extrabold"
                        :class="ledgerBalanced ? 'text-wpx-success' : 'text-wpx-danger'"
                    >
                        {{ ledgerBalanced ? 'Équilibré' : 'À vérifier' }}
                    </p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        Accès administration
                    </p>
                    <p class="mt-2 text-lg font-extrabold">{{ adminAccountsCount }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">comptes distincts</p>
                </div>
                <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-850 shadow-wpx-card-dark border p-4">
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                        Double validation
                    </p>
                    <p class="text-wpx-gold mt-2 text-lg font-extrabold">{{ pendingApprovals }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">opérations en attente</p>
                </div>
            </section>

            <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                <h3 class="text-base font-extrabold">Protections actives</h3>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4">
                        <p class="text-wpx-success text-sm font-extrabold">✓ Double validation financière</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Une opération sensible peut exiger un deuxième administrateur différent de son créateur.
                        </p>
                    </div>
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4">
                        <p class="text-wpx-success text-sm font-extrabold">✓ Corrections sans effacement</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Les corrections comptables passent par de nouvelles écritures plutôt que par la modification
                            du passé.
                        </p>
                    </div>
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4">
                        <p class="text-wpx-success text-sm font-extrabold">✓ Accès par rôle</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Chaque administrateur utilise son propre compte et reçoit seulement les permissions
                            nécessaires.
                        </p>
                    </div>
                    <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4">
                        <p class="text-wpx-success text-sm font-extrabold">✓ Antifraude avant récompense</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Une récompense suspecte peut être retenue avant tout versement automatique.
                        </p>
                    </div>
                </div>
            </section>

            <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                <div class="flex items-start gap-3">
                    <span
                        class="bg-wpx-blue/12 text-wpx-blue rounded-wpx-md flex h-9 w-9 shrink-0 items-center justify-center"
                        >i</span
                    >
                    <div>
                        <h3 class="text-sm font-extrabold">Journal d’audit détaillé</h3>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            L’écran de recherche chronologique complet est encore en préparation. En attendant, les
                            modules Finance, Publicité et Permissions conservent leurs propres références et traces
                            d’action.
                        </p>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
