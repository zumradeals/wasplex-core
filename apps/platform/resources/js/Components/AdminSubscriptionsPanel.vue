<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface EconomicClass {
    id: string;
    code: string;
    versions: Array<{ quota_monthly: number; reward_per_complete_view_minor: number; currency: string }>;
}

interface PlanVersion {
    id: string;
    status: string;
    price_minor: number;
    currency: string;
    duration_days: number;
    plan: { code: string; name: string };
    economic_class_link: { economic_class: { id: string; code: string } } | null;
}

const levels = ref<EconomicClass[]>([]);
const planVersions = ref<PlanVersion[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);
const error = ref<string | null>(null);
const showCreate = ref(false);
const editing = ref<string | null>(null);

const createForm = reactive({
    plan_code: '',
    plan_name: '',
    economic_class_id: '',
    price_minor: 0,
    duration_days: 30,
});
const editForm = reactive({ price_minor: 0, duration_days: 30 });
const rewardForms = reactive<Record<string, { quota_monthly: number; reward_per_complete_view_minor: number }>>({});
const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const [levelsRes, plansRes] = await Promise.all([
            http.get('/admin/economic-classes'),
            http.get('/admin/subscriptions/plans'),
        ]);
        levels.value = levelsRes.data.economic_classes;
        for (const level of levels.value) {
            rewardForms[level.id] = {
                quota_monthly: level.versions[0]?.quota_monthly ?? 1,
                reward_per_complete_view_minor: level.versions[0]?.reward_per_complete_view_minor ?? 30,
            };
        }
        planVersions.value = plansRes.data.plan_versions;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de charger les abonnements.';
    } finally {
        loading.value = false;
    }
}

async function saveReward(level: EconomicClass): Promise<void> {
    busy.value = level.id;
    error.value = null;
    try {
        await http.patch(`/admin/economic-classes/${level.id}`, {
            ...rewardForms[level.id],
            country_code: null,
            currency: 'XOF',
        });
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Modification du gain impossible.';
    } finally {
        busy.value = null;
    }
}

async function createPlan(): Promise<void> {
    busy.value = 'create';
    error.value = null;
    try {
        await http.post('/admin/subscriptions/plans', {
            ...createForm,
            plan_code: createForm.plan_code.trim().toUpperCase(),
            plan_name: createForm.plan_name.trim(),
            currency: 'XOF',
        });
        Object.assign(createForm, { plan_code: '', plan_name: '', economic_class_id: '', price_minor: 0, duration_days: 30 });
        showCreate.value = false;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Création du plan impossible.';
    } finally {
        busy.value = null;
    }
}

function startEdit(version: PlanVersion): void {
    editing.value = version.id;
    editForm.price_minor = version.price_minor;
    editForm.duration_days = version.duration_days;
}

async function saveEdit(version: PlanVersion): Promise<void> {
    busy.value = version.id;
    error.value = null;
    try {
        await http.patch(`/admin/subscriptions/plans/${version.id}`, {
            price_minor: editForm.price_minor,
            duration_days: editForm.duration_days,
        });
        editing.value = null;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Modification impossible.';
    } finally {
        busy.value = null;
    }
}

async function publish(version: PlanVersion): Promise<void> {
    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/publish`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function suspend(version: PlanVersion): Promise<void> {
    if (!window.confirm(`Suspendre le plan ${version.plan.name} ?`)) return;
    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/suspend`);
        await load();
    } finally {
        busy.value = null;
    }
}

function statusLabel(status: string): string {
    return { draft: 'Brouillon', published: 'Actif', suspended: 'Suspendu' }[status] ?? status;
}

function statusClasses(status: string): string {
    if (status === 'published') return 'bg-wpx-success/10 text-wpx-success-light';
    if (status === 'suspended') return 'bg-wpx-danger/10 text-wpx-danger-light';
    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-5">
            <h2 class="text-wpx-text text-base font-bold">Récompenses par vidéo entièrement vue</h2>
            <p class="text-wpx-text-muted mt-1 mb-4 text-sm">
                Le gain dépend directement de l’abonnement. Aucun poids ni coefficient n’est utilisé.
            </p>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <div v-for="level in levels" :key="level.id" class="border-wpx-border rounded-xl border p-4">
                    <p class="text-wpx-text mb-3 font-bold">{{ level.code }}</p>
                    <label class="text-wpx-text-muted block text-xs">Gain par vue complète (WP)
                        <input v-model.number="rewardForms[level.id].reward_per_complete_view_minor" type="number" min="1" class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" />
                    </label>
                    <label class="text-wpx-text-muted mt-3 block text-xs">Maximum de publicités par mois
                        <input v-model.number="rewardForms[level.id].quota_monthly" type="number" min="1" class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" />
                    </label>
                    <button type="button" :disabled="busy === level.id" class="text-wpx-blue-light mt-3 text-sm font-bold disabled:opacity-50" @click="saveReward(level)">
                        Enregistrer
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-5">
            <div class="mb-4 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-wpx-text text-base font-bold">Abonnements Wasplex</h2>
                    <p class="text-wpx-text-muted mt-1 text-sm">
                        Configurez directement le prix et la durée de chaque abonnement.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-wpx-md bg-wpx-blue-light px-4 py-2 text-sm font-bold text-white"
                    @click="showCreate = !showCreate"
                >
                    {{ showCreate ? 'Annuler' : 'Créer un plan' }}
                </button>
            </div>

            <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light mb-4 rounded p-3 text-sm">{{ error }}</p>

            <form v-if="showCreate" class="bg-wpx-canvas mb-5 grid gap-3 rounded-xl p-4 md:grid-cols-5" @submit.prevent="createPlan">
                <label class="text-wpx-text text-xs font-semibold">Nom
                    <input v-model="createForm.plan_name" required class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" placeholder="Ex. Premium" />
                </label>
                <label class="text-wpx-text text-xs font-semibold">Code
                    <input v-model="createForm.plan_code" required class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" placeholder="PREMIUM" />
                </label>
                <label class="text-wpx-text text-xs font-semibold">Niveau de gain
                    <select v-model="createForm.economic_class_id" required class="border-wpx-border mt-1 w-full rounded border p-2 text-sm">
                        <option value="" disabled>Choisir</option>
                        <option v-for="level in levels" :key="level.id" :value="level.id">{{ level.code }}</option>
                    </select>
                </label>
                <label class="text-wpx-text text-xs font-semibold">Prix (FCFA)
                    <input v-model.number="createForm.price_minor" type="number" min="0" required class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" />
                </label>
                <label class="text-wpx-text text-xs font-semibold">Durée (jours)
                    <input v-model.number="createForm.duration_days" type="number" min="1" required class="border-wpx-border mt-1 w-full rounded border p-2 text-sm" />
                </label>
                <div class="md:col-span-5">
                    <button type="submit" :disabled="busy === 'create'" class="rounded-wpx-md bg-wpx-success px-4 py-2 text-sm font-bold text-white disabled:opacity-50">
                        Enregistrer le nouveau plan
                    </button>
                </div>
            </form>

            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                            <th class="p-2">Plan</th>
                            <th class="p-2">Prix</th>
                            <th class="p-2">Durée</th>
                            <th class="p-2">Statut</th>
                            <th class="p-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="v in planVersions" :key="v.id" class="border-wpx-border text-wpx-text border-b">
                            <td class="p-2 font-semibold">{{ v.plan.name }}</td>
                            <td class="p-2">
                                <input v-if="editing === v.id" v-model.number="editForm.price_minor" type="number" min="0" class="border-wpx-border w-32 rounded border p-1.5" />
                                <template v-else>{{ v.price_minor === 0 ? 'Gratuit' : `${numberFormatter.format(v.price_minor)} FCFA` }}</template>
                            </td>
                            <td class="p-2">
                                <input v-if="editing === v.id" v-model.number="editForm.duration_days" type="number" min="1" class="border-wpx-border w-20 rounded border p-1.5" />
                                <template v-else>{{ v.duration_days }} jours</template>
                            </td>
                            <td class="p-2"><span class="rounded px-2 py-1 text-xs font-semibold" :class="statusClasses(v.status)">{{ statusLabel(v.status) }}</span></td>
                            <td class="p-2 text-right whitespace-nowrap">
                                <template v-if="editing === v.id">
                                    <button class="text-wpx-success-light mr-3 font-semibold" :disabled="busy === v.id" @click="saveEdit(v)">Enregistrer</button>
                                    <button class="text-wpx-text-muted" @click="editing = null">Annuler</button>
                                </template>
                                <template v-else>
                                    <button v-if="v.status === 'draft'" class="text-wpx-blue-light mr-3 font-semibold" @click="startEdit(v)">Modifier</button>
                                    <button v-if="v.status === 'draft'" class="text-wpx-success-light mr-3 font-semibold" :disabled="busy === v.id" @click="publish(v)">Activer</button>
                                    <button v-if="v.status === 'published'" class="text-wpx-danger-light font-semibold" :disabled="busy === v.id" @click="suspend(v)">Suspendre</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-wpx-text-muted mt-4 text-xs">
                Les paramètres internes de calcul ne sont plus affichés ici. Les gains WP par vue complète feront l’objet d’un réglage simple et séparé.
            </p>
        </div>
    </div>
</template>
