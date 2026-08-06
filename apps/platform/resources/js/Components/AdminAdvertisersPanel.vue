<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Advertiser {
    id: string;
    organization_id: string;
    advertiser_type: string | null;
    status: string;
}

interface Brand {
    id: string;
    name: string;
    status: string;
}

interface Asset {
    id: string;
    filename: string;
    type: string;
    moderation_status: string;
}

const advertisers = ref<Advertiser[]>([]);
const brands = ref<Brand[]>([]);
const pendingAssets = ref<Asset[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);

const depositForm = ref({ organization_id: '', amount_minor: 5000, currency: 'XOF', proof_reference: '', reason: '' });
const depositMessage = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [advertisersRes, brandsRes, assetsRes] = await Promise.all([
            http.get('/admin/advertisers'),
            http.get('/admin/brands'),
            http.get('/admin/creative-assets'),
        ]);
        advertisers.value = advertisersRes.data.advertisers;
        brands.value = brandsRes.data.brands;
        pendingAssets.value = assetsRes.data.assets;
    } finally {
        loading.value = false;
    }
}

async function verifyAdvertiser(advertiser: Advertiser): Promise<void> {
    busy.value = advertiser.id;
    try {
        await http.post(`/admin/advertisers/${advertiser.id}/verify`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function restrictAdvertiser(advertiser: Advertiser): Promise<void> {
    busy.value = advertiser.id;
    try {
        await http.post(`/admin/advertisers/${advertiser.id}/restrict`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function verifyBrand(brand: Brand): Promise<void> {
    busy.value = brand.id;
    try {
        await http.post(`/admin/brands/${brand.id}/verify`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function decideAsset(asset: Asset, decision: string): Promise<void> {
    busy.value = asset.id;
    try {
        await http.post(`/admin/creative-assets/${asset.id}/decide`, { decision });
        await load();
    } finally {
        busy.value = null;
    }
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
    } catch {
        depositMessage.value = 'La création du dépôt supervisé a échoué.';
    }
}

function statusClasses(status: string): string {
    if (['verified', 'active', 'ready', 'approved'].includes(status)) {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (['rejected', 'restricted', 'suspended', 'needs_changes'].includes(status)) {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Annonceurs</h2>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Organisation</th>
                        <th class="p-2">Type</th>
                        <th class="p-2">Statut</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in advertisers" :key="a.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2 font-mono text-xs">{{ a.organization_id }}</td>
                        <td class="p-2">{{ a.advertiser_type ?? '—' }}</td>
                        <td class="p-2">
                            <span
                                class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                :class="statusClasses(a.status)"
                            >
                                {{ a.status }}
                            </span>
                        </td>
                        <td class="p-2 text-right">
                            <button
                                v-if="a.status !== 'verified' && a.status !== 'active'"
                                class="text-wpx-success-light mr-2 text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === a.id"
                                @click="verifyAdvertiser(a)"
                            >
                                Vérifier
                            </button>
                            <button
                                v-if="a.status !== 'restricted'"
                                class="text-wpx-danger-light text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === a.id"
                                @click="restrictAdvertiser(a)"
                            >
                                Restreindre
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Marques à vérifier</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="b in brands"
                    :key="b.id"
                    class="border-wpx-border flex items-center justify-between border-b py-1.5 text-sm"
                >
                    <span>{{ b.name }}</span>
                    <div class="flex items-center gap-2">
                        <span
                            class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                            :class="statusClasses(b.status)"
                            >{{ b.status }}</span
                        >
                        <button
                            v-if="b.status !== 'verified'"
                            class="text-wpx-success-light text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === b.id"
                            @click="verifyBrand(b)"
                        >
                            Vérifier
                        </button>
                    </div>
                </li>
                <li v-if="brands.length === 0" class="text-wpx-text-muted text-sm">Aucune marque.</li>
            </ul>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Médias en attente de modération</h2>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="asset in pendingAssets"
                    :key="asset.id"
                    class="border-wpx-border flex items-center justify-between border-b py-1.5 text-sm"
                >
                    <span>{{ asset.filename }} ({{ asset.type }})</span>
                    <div class="flex gap-2">
                        <button
                            class="text-wpx-success-light text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === asset.id"
                            @click="decideAsset(asset, 'approve')"
                        >
                            Approuver
                        </button>
                        <button
                            class="text-wpx-warning-light text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === asset.id"
                            @click="decideAsset(asset, 'request_changes')"
                        >
                            Corrections
                        </button>
                        <button
                            class="text-wpx-danger-light text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === asset.id"
                            @click="decideAsset(asset, 'reject')"
                        >
                            Rejeter
                        </button>
                    </div>
                </li>
                <li v-if="pendingAssets.length === 0" class="text-wpx-text-muted text-sm">Rien en attente.</li>
            </ul>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Dépôt supervisé (docs/13 §28)</h2>
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
                L'approbation finale doit être faite par un second administrateur, différent de celui qui l'a créé.
            </p>
        </div>
    </div>
</template>
