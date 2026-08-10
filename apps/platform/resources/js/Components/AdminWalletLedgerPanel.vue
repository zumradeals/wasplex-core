<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface LedgerEntryRow {
    id: string;
    direction: string;
    amount_minor: number;
    currency: string;
    description: string | null;
    account: { code: string; owner_type: string; owner_id: string } | null;
}

interface LedgerTransactionRow {
    id: string;
    type: string;
    status: string;
    currency: string;
    source_module: string;
    business_reference: string | null;
    created_by: string | null;
    approved_by: string | null;
    posted_at: string | null;
    created_at: string;
    entries?: LedgerEntryRow[];
}

interface LedgerAccountRow {
    id: string;
    code: string;
    owner_type: string;
    owner_id: string;
    currency: string;
    status: string;
}

interface ReconciliationEntry {
    id: string;
    source_module: string;
    source_record_id: string;
    provider_reference: string | null;
    amount_minor: number | null;
    currency: string | null;
    internal_status: string;
    provider_status_raw: string | null;
    result_code: string;
    notes: string | null;
    created_at: string;
}

interface ReconciliationRun {
    id: string;
    triggered_by: string | null;
    started_at: string;
    completed_at: string | null;
    total_checked: number;
    summary: Record<string, number>;
}

interface GeniusPayStatus {
    configured: boolean;
    api_key_hint: string | null;
    updated_at: string | null;
    webhook_url: string;
}

const RESULT_LABELS: Record<string, string> = {
    matched: 'Rapproché',
    partially_matched: 'Partiellement rapproché',
    unmatched: 'Introuvable chez le prestataire',
    duplicate: 'Doublon',
    amount_mismatch: 'Montant différent',
    status_mismatch: 'Statut incompatible',
    manual_review: 'Revue manuelle requise',
};

const tab = ref<'pending' | 'operations' | 'ledger'>('pending');
const loading = ref(true);

const isBalanced = ref<boolean | null>(null);
const transactions = ref<LedgerTransactionRow[]>([]);
const selectedTransaction = ref<LedgerTransactionRow | null>(null);
const accounts = ref<LedgerAccountRow[]>([]);
const reconciliationEntries = ref<ReconciliationEntry[]>([]);
const reconciliationRuns = ref<ReconciliationRun[]>([]);
const runningReconciliation = ref(false);
const geniusPayStatus = ref<GeniusPayStatus | null>(null);
const geniusPayForm = reactive({ api_key: '', api_secret: '', webhook_secret: '' });
const geniusPayMessage = ref<string | null>(null);
const savingGeniusPay = ref(false);
const testingGeniusPay = ref(false);

const depositForm = ref({ organization_id: '', amount_minor: 5000, currency: 'XOF', proof_reference: '', reason: '' });
const depositMessage = ref<string | null>(null);

const correctionForm = reactive({ original_transaction_id: '', reason: '' });
const correctionActionForm = reactive({ transaction_id: '', reason: '' });
const correctionMessage = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [summaryRes, transactionsRes, accountsRes, entriesRes, runsRes, geniusPayRes] = await Promise.allSettled([
            http.get('/admin/dashboard/summary'),
            http.get('/admin/ledger/transactions'),
            http.get('/admin/ledger/accounts'),
            http.get('/admin/reconciliation/entries'),
            http.get('/admin/reconciliation/runs'),
            http.get('/admin/advertiser-wallet/geniuspay'),
        ]);
        isBalanced.value = summaryRes.status === 'fulfilled' ? summaryRes.value.data.ledger.is_balanced : null;
        transactions.value = transactionsRes.status === 'fulfilled' ? transactionsRes.value.data.transactions : [];
        accounts.value = accountsRes.status === 'fulfilled' ? accountsRes.value.data.accounts : [];
        reconciliationEntries.value = entriesRes.status === 'fulfilled' ? entriesRes.value.data.entries : [];
        reconciliationRuns.value = runsRes.status === 'fulfilled' ? runsRes.value.data.runs : [];
        geniusPayStatus.value = geniusPayRes.status === 'fulfilled' ? geniusPayRes.value.data.geniuspay : null;
    } finally {
        loading.value = false;
    }
}

async function saveGeniusPay(): Promise<void> {
    geniusPayMessage.value = null;
    savingGeniusPay.value = true;
    try {
        const { data } = await http.put('/admin/advertiser-wallet/geniuspay', geniusPayForm);
        geniusPayStatus.value = data.geniuspay;
        geniusPayForm.api_key = '';
        geniusPayForm.api_secret = '';
        geniusPayForm.webhook_secret = '';
        geniusPayMessage.value = 'Clés sandbox enregistrées et chiffrées.';
    } catch (e) {
        geniusPayMessage.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "La configuration n'a pas pu être enregistrée.";
    } finally {
        savingGeniusPay.value = false;
    }
}

async function testGeniusPay(): Promise<void> {
    geniusPayMessage.value = null;
    testingGeniusPay.value = true;
    try {
        const { data } = await http.post('/admin/advertiser-wallet/geniuspay/test');
        geniusPayMessage.value = data.message;
    } catch (e) {
        geniusPayMessage.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Test de connexion échoué.';
    } finally {
        testingGeniusPay.value = false;
    }
}

async function viewTransaction(id: string): Promise<void> {
    const { data } = await http.get(`/admin/ledger/transactions/${id}`);
    selectedTransaction.value = data.transaction;
}

async function triggerReconciliation(): Promise<void> {
    runningReconciliation.value = true;
    try {
        await http.post('/admin/reconciliation/runs');
        await load();
    } finally {
        runningReconciliation.value = false;
    }
}

function reconciliationExportUrl(): string {
    return '/api/admin/reconciliation/entries/export';
}

async function createSupervisedDeposit(): Promise<void> {
    depositMessage.value = null;
    try {
        await http.post('/admin/advertiser-wallet/deposits', depositForm.value);
        depositMessage.value = "Dépôt supervisé créé — en attente d'approbation par un second administrateur.";
        depositForm.value = {
            organization_id: '',
            amount_minor: 5000,
            currency: 'XOF',
            proof_reference: '',
            reason: '',
        };
        await load();
    } catch {
        depositMessage.value = 'La création du dépôt supervisé a échoué.';
    }
}

async function proposeCorrection(): Promise<void> {
    correctionMessage.value = null;
    try {
        await http.post('/admin/ledger/corrections', correctionForm);
        correctionMessage.value = 'Correction proposée — en attente d’approbation par un second administrateur.';
        correctionForm.original_transaction_id = '';
        correctionForm.reason = '';
        await load();
    } catch {
        correctionMessage.value = 'La proposition de correction a échoué.';
    }
}

async function approveCorrection(): Promise<void> {
    correctionMessage.value = null;
    try {
        await http.post(`/admin/ledger/corrections/${correctionActionForm.transaction_id}/approve`);
        correctionMessage.value = 'Correction approuvée et comptabilisée.';
        await load();
    } catch {
        correctionMessage.value = "L'approbation a échoué (le même compte ne peut pas proposer puis approuver).";
    }
}

async function rejectCorrection(): Promise<void> {
    correctionMessage.value = null;
    try {
        await http.post(`/admin/ledger/corrections/${correctionActionForm.transaction_id}/reject`, {
            reason: correctionActionForm.reason,
        });
        correctionMessage.value = 'Correction rejetée.';
        await load();
    } catch {
        correctionMessage.value = 'Le rejet a échoué.';
    }
}

const recentOperations = computed(() => {
    const ledgerOps = transactions.value.map((t) => ({
        kind: 'ledger' as const,
        id: t.id,
        label: t.business_reference ?? t.type,
        type: t.type,
        status: t.status,
        date: t.created_at,
        transaction: t,
    }));
    const reconciliationOps = reconciliationEntries.value.map((e) => ({
        kind: 'reconciliation' as const,
        id: e.id,
        label: `Rapprochement — ${e.source_module}`,
        type: 'Vérification',
        status: RESULT_LABELS[e.result_code] ?? e.result_code,
        date: e.created_at,
        entry: e,
    }));
    return [...ledgerOps, ...reconciliationOps].sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
});

function statusClasses(status: string): string {
    if (['posted', 'matched', 'Confirmé'].includes(status)) {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (['rejected', 'unmatched', 'amount_mismatch'].includes(status)) {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }
    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="flex gap-2.5">
            <button
                type="button"
                class="rounded-wpx-md px-4.5 py-2.5 text-[13px] font-bold"
                :class="
                    tab === 'pending'
                        ? 'bg-wpx-navy-950 text-white'
                        : 'border-wpx-border text-wpx-text-muted border bg-white'
                "
                @click="tab = 'pending'"
            >
                À valider (0)
            </button>
            <button
                type="button"
                class="rounded-wpx-md px-4.5 py-2.5 text-[13px] font-bold"
                :class="
                    tab === 'operations'
                        ? 'bg-wpx-navy-950 text-white'
                        : 'border-wpx-border text-wpx-text-muted border bg-white'
                "
                @click="tab = 'operations'"
            >
                Toutes les opérations
            </button>
            <button
                type="button"
                class="rounded-wpx-md px-4.5 py-2.5 text-[13px] font-bold"
                :class="
                    tab === 'ledger'
                        ? 'bg-wpx-navy-950 text-white'
                        : 'border-wpx-border text-wpx-text-muted border bg-white'
                "
                @click="tab = 'ledger'"
            >
                Grand livre
            </button>
        </div>

        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>

        <template v-else>
            <div
                v-if="tab === 'pending'"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-warning/40 bg-wpx-surface border p-6 text-center"
            >
                <p class="text-wpx-text text-sm font-bold">Aucune demande de retrait</p>
                <p class="text-wpx-text-muted mt-1 text-xs">
                    Cette fonctionnalité n'existe pas encore dans Wasplex — aucun utilisateur ne peut pour l'instant
                    demander de retrait. Cet onglet est prêt à recevoir les demandes dès que la fonctionnalité sera
                    construite.
                </p>
            </div>

            <div v-else-if="tab === 'operations'" class="flex flex-col gap-5">
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                            Dépôts aujourd'hui
                        </p>
                        <p class="text-wpx-text-muted mt-2 text-sm italic">Bientôt disponible</p>
                    </div>
                    <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                        <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                            Retraits aujourd'hui
                        </p>
                        <p class="text-wpx-text-muted mt-2 text-sm italic">Bientôt disponible</p>
                    </div>
                    <div class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-4.5">
                        <div class="flex items-center justify-between">
                            <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                Comptes équilibrés
                            </p>
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="isBalanced ? 'bg-wpx-success' : 'bg-wpx-danger'"
                            />
                        </div>
                        <p class="text-wpx-text mt-2 text-2xl font-extrabold">{{ isBalanced ? 'Oui' : 'Non' }}</p>
                    </div>
                </div>

                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface overflow-hidden border">
                    <div class="border-wpx-border flex items-center justify-between border-b px-5 py-3.5">
                        <p class="text-wpx-text text-sm font-bold">Dernières opérations</p>
                        <div class="flex items-center gap-4">
                            <button
                                type="button"
                                class="text-wpx-blue-light text-xs font-bold disabled:opacity-50"
                                :disabled="runningReconciliation"
                                @click="triggerReconciliation"
                            >
                                {{ runningReconciliation ? 'Rapprochement en cours…' : 'Lancer un rapprochement' }}
                            </button>
                            <a :href="reconciliationExportUrl()" class="text-wpx-blue-light text-xs font-bold" download>
                                Exporter le rapprochement en CSV
                            </a>
                        </div>
                    </div>
                    <div
                        class="border-wpx-border text-wpx-text-muted grid grid-cols-[2fr_1fr_1fr] border-b px-5 py-2.5 text-[11px] font-bold tracking-wide uppercase"
                    >
                        <span>Opération</span><span>Type</span><span>Statut</span>
                    </div>
                    <button
                        v-for="op in recentOperations.slice(0, 20)"
                        :key="`${op.kind}-${op.id}`"
                        type="button"
                        class="border-wpx-border/60 grid w-full grid-cols-[2fr_1fr_1fr] items-center border-b px-5 py-3 text-left last:border-0"
                        @click="op.kind === 'ledger' ? viewTransaction(op.id) : null"
                    >
                        <span>
                            <span class="text-wpx-text block text-[13px] font-bold">{{ op.label }}</span>
                            <span class="text-wpx-text-muted block text-[11px]">{{
                                new Date(op.date).toLocaleString('fr-FR')
                            }}</span>
                        </span>
                        <span class="text-wpx-text-muted text-[13px]">{{ op.type }}</span>
                        <span>
                            <span
                                class="rounded-wpx-full px-2.5 py-0.5 text-[11px] font-bold"
                                :class="statusClasses(op.status)"
                            >
                                {{ op.status }}
                            </span>
                        </span>
                    </button>
                    <p v-if="recentOperations.length === 0" class="text-wpx-text-muted p-4 text-sm">
                        Aucune opération.
                    </p>
                </div>

                <div
                    v-if="selectedTransaction"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-4"
                >
                    <h3 class="text-wpx-text mb-1 text-sm font-semibold">Écritures</h3>
                    <p class="text-wpx-text-muted mb-3 font-mono text-xs break-all">{{ selectedTransaction.id }}</p>
                    <ul class="flex flex-col gap-2 text-sm">
                        <li
                            v-for="entry in selectedTransaction.entries"
                            :key="entry.id"
                            class="border-wpx-border flex justify-between border-b pb-1"
                        >
                            <span class="text-wpx-text-muted text-xs">{{ entry.account?.code }}</span>
                            <span
                                :class="entry.direction === 'debit' ? 'text-wpx-info-light' : 'text-wpx-success-light'"
                            >
                                {{ entry.direction === 'debit' ? '−' : '+' }}{{ entry.amount_minor }}
                                {{ entry.currency }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div
                    v-if="reconciliationRuns.length > 0"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-4"
                >
                    <p class="text-wpx-text mb-2 text-sm font-bold">Historique des rapprochements</p>
                    <ul class="flex flex-col gap-1 text-xs">
                        <li v-for="run in reconciliationRuns.slice(0, 5)" :key="run.id" class="text-wpx-text-muted">
                            {{ new Date(run.started_at).toLocaleString('fr-FR') }} — {{ run.total_checked }} vérifié(s)
                            —
                            <span v-for="(count, code) in run.summary" :key="code" class="mr-2">
                                {{ RESULT_LABELS[code] ?? code }}: {{ count }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-wpx-text text-sm font-semibold">GeniusPay Sandbox</h2>
                            <p class="text-wpx-text-muted mt-1 text-xs">
                                Configure ici les paiements de test du Wallet annonceur. Les clés sont chiffrées et ne
                                seront jamais réaffichées.
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-xs font-bold"
                            :class="
                                geniusPayStatus?.configured
                                    ? 'bg-wpx-success/10 text-wpx-success-light'
                                    : 'bg-wpx-warning/10 text-wpx-warning-light'
                            "
                        >
                            {{ geniusPayStatus?.configured ? 'Configuré' : 'À configurer' }}
                        </span>
                    </div>

                    <form class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-3" @submit.prevent="saveGeniusPay">
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Clé publique sandbox</span>
                            <input
                                v-model.trim="geniusPayForm.api_key"
                                :placeholder="geniusPayStatus?.api_key_hint ?? 'pk_sandbox_...'"
                                autocomplete="off"
                                class="rounded-wpx-sm border-wpx-border border px-3 py-2 text-sm"
                                :required="!geniusPayStatus?.configured"
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Clé secrète sandbox</span>
                            <input
                                v-model.trim="geniusPayForm.api_secret"
                                type="password"
                                placeholder="sk_sandbox_..."
                                autocomplete="new-password"
                                class="rounded-wpx-sm border-wpx-border border px-3 py-2 text-sm"
                                :required="!geniusPayStatus?.configured"
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Secret du webhook</span>
                            <input
                                v-model.trim="geniusPayForm.webhook_secret"
                                type="password"
                                placeholder="whsec_..."
                                autocomplete="new-password"
                                class="rounded-wpx-sm border-wpx-border border px-3 py-2 text-sm"
                                :required="!geniusPayStatus?.configured"
                            />
                        </label>
                        <div class="flex flex-wrap items-center gap-3 lg:col-span-3">
                            <button
                                type="submit"
                                class="rounded-wpx-sm bg-wpx-navy-950 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                                :disabled="savingGeniusPay"
                            >
                                {{ savingGeniusPay ? 'Enregistrement…' : 'Enregistrer les clés' }}
                            </button>
                            <button
                                v-if="geniusPayStatus?.configured"
                                type="button"
                                class="rounded-wpx-sm border-wpx-blue-light text-wpx-blue-light border px-4 py-2 text-sm font-semibold disabled:opacity-50"
                                :disabled="testingGeniusPay"
                                @click="testGeniusPay"
                            >
                                {{ testingGeniusPay ? 'Test en cours…' : 'Tester la connexion' }}
                            </button>
                        </div>
                    </form>

                    <div class="bg-wpx-canvas mt-4 rounded-lg p-3 text-xs">
                        <p class="text-wpx-text font-bold">Adresse du webhook à copier dans GeniusPay</p>
                        <p class="text-wpx-text-muted mt-1 font-mono break-all">
                            {{ geniusPayStatus?.webhook_url ?? 'https://wasplex.com/api/webhooks/geniuspay' }}
                        </p>
                    </div>
                    <p v-if="geniusPayMessage" class="text-wpx-text-muted mt-3 text-xs">{{ geniusPayMessage }}</p>
                </div>

                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-4">
                    <h2 class="text-wpx-text mb-3 text-sm font-semibold">Créer un dépôt supervisé (annonceur)</h2>
                    <form class="grid grid-cols-1 gap-3 sm:grid-cols-2" @submit.prevent="createSupervisedDeposit">
                        <input
                            v-model="depositForm.organization_id"
                            placeholder="ID organisation"
                            class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                            required
                        />
                        <input
                            v-model.number="depositForm.amount_minor"
                            type="number"
                            min="100"
                            placeholder="Montant (FCFA)"
                            class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                            required
                        />
                        <input
                            v-model="depositForm.proof_reference"
                            placeholder="Référence de preuve"
                            class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                            required
                        />
                        <input
                            v-model="depositForm.reason"
                            placeholder="Motif"
                            class="rounded-wpx-sm border-wpx-border border px-2 py-1.5 text-sm"
                            required
                        />
                        <button
                            type="submit"
                            class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold sm:col-span-2"
                        >
                            Créer le dépôt supervisé
                        </button>
                    </form>
                    <p v-if="depositMessage" class="text-wpx-text-muted mt-2 text-xs">{{ depositMessage }}</p>
                    <p class="text-wpx-text-muted mt-2 text-xs">
                        L'approbation finale doit être faite par un second administrateur, différent de celui qui l'a
                        créé.
                    </p>
                </div>
            </div>

            <div v-else class="flex flex-col gap-5">
                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface overflow-hidden border">
                    <p class="text-wpx-text border-wpx-border border-b px-5 py-3.5 text-sm font-bold">
                        Comptes du Grand livre
                    </p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                                <th class="p-2.5">Code</th>
                                <th class="p-2.5">Propriétaire</th>
                                <th class="p-2.5">Devise</th>
                                <th class="p-2.5">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in accounts" :key="a.id" class="border-wpx-border text-wpx-text border-b">
                                <td class="p-2.5 font-mono text-xs">{{ a.code }}</td>
                                <td class="text-wpx-text-muted p-2.5 text-xs">{{ a.owner_type }} · {{ a.owner_id }}</td>
                                <td class="p-2.5">{{ a.currency }}</td>
                                <td class="p-2.5">{{ a.status }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="accounts.length === 0" class="text-wpx-text-muted p-4 text-sm">Aucun compte.</p>
                </div>

                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-4">
                    <h2 class="text-wpx-text mb-1 text-sm font-semibold">Proposer une correction</h2>
                    <p class="text-wpx-text-muted mb-3 text-xs">
                        Une correction crée une nouvelle transaction inverse — jamais de modification en place. Elle
                        doit être approuvée par un second administrateur, différent de celui qui l'a proposée.
                    </p>
                    <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="proposeCorrection">
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Transaction d'origine (id)</span>
                            <input
                                v-model="correctionForm.original_transaction_id"
                                class="rounded-wpx-sm border-wpx-border w-64 border px-2 py-1"
                                required
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Motif</span>
                            <input
                                v-model="correctionForm.reason"
                                class="rounded-wpx-sm border-wpx-border w-64 border px-2 py-1"
                                required
                            />
                        </label>
                        <button
                            type="submit"
                            class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold"
                        >
                            Proposer
                        </button>
                    </form>

                    <form class="flex flex-wrap items-end gap-3" @submit.prevent>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Transaction en attente (id)</span>
                            <input
                                v-model="correctionActionForm.transaction_id"
                                class="rounded-wpx-sm border-wpx-border w-64 border px-2 py-1"
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span>Motif (si rejet)</span>
                            <input
                                v-model="correctionActionForm.reason"
                                class="rounded-wpx-sm border-wpx-border w-64 border px-2 py-1"
                            />
                        </label>
                        <button
                            type="button"
                            class="rounded-wpx-sm bg-wpx-success text-wpx-navy-950 px-3 py-1.5 text-sm font-semibold"
                            @click="approveCorrection"
                        >
                            Approuver
                        </button>
                        <button
                            type="button"
                            class="rounded-wpx-sm bg-wpx-danger px-3 py-1.5 text-sm font-semibold text-white"
                            @click="rejectCorrection"
                        >
                            Rejeter
                        </button>
                    </form>
                    <p v-if="correctionMessage" class="text-wpx-text-muted mt-2 text-xs">{{ correctionMessage }}</p>
                </div>
            </div>
        </template>
    </div>
</template>
