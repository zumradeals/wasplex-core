<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
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

const freeReward = computed(() => {
    const free = levels.value.find((level) => level.code === 'FREE');
    return free ? (rewardForms[free.id]?.reward_per_complete_view_minor ?? 30) : 30;
});
const publishedCount = computed(() => planVersions.value.filter((version) => version.status === 'published').length);
const draftCount = computed(() => planVersions.value.filter((version) => version.status === 'draft').length);

function rewardAdvantage(level: EconomicClass): string {
    const reward = rewardForms[level.id]?.reward_per_complete_view_minor ?? freeReward.value;
    if (level.code === 'FREE' || freeReward.value <= 0) return 'Récompense de base';
    return `+${Math.round(((reward - freeReward.value) / freeReward.value) * 100)} % par rapport au Gratuit`;
}

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
            'Impossible de charger les offres.';
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
            'Modification de la récompense impossible.';
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
        Object.assign(createForm, {
            plan_code: '',
            plan_name: '',
            economic_class_id: '',
            price_minor: 0,
            duration_days: 30,
        });
        showCreate.value = false;
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Création de l’offre impossible.';
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
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? 'Modification impossible.';
    } finally {
        busy.value = null;
    }
}

async function publish(version: PlanVersion): Promise<void> {
    const confirmed = window.confirm(`Publier l’offre ${version.plan.name} ? Elle deviendra visible par les membres.`);
    if (!confirmed) return;

    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/publish`);
        await load();
    } finally {
        busy.value = null;
    }
}

async function suspend(version: PlanVersion): Promise<void> {
    if (!window.confirm(`Suspendre l’offre ${version.plan.name} ? Elle ne sera plus proposée aux membres.`)) return;
    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/suspend`);
        await load();
    } finally {
        busy.value = null;
    }
}

function statusLabel(status: string): string {
    return { draft: 'Brouillon', published: 'Publié', suspended: 'Suspendu' }[status] ?? status;
}

function statusClasses(status: string): string {
    if (status === 'published') return 'bg-wpx-success/15 text-wpx-success';
    if (status === 'suspended') return 'bg-wpx-danger/15 text-wpx-danger';
    return 'bg-wpx-warning/15 text-wpx-gold';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 text-wpx-white-soft">
        <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold uppercase tracking-[0.16em]">Offres Wasplex</p>
                    <h2 class="mt-1 text-2xl font-extrabold">Ce que les membres peuvent réellement choisir.</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                        Une offre <strong class="text-wpx-white-soft">Publié</strong> est visible par les membres. Une offre
                        <strong class="text-wpx-white-soft">Brouillon</strong> reste invisible tant que tu ne la publies pas.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan rounded-wpx-md bg-gradient-to-br px-4 py-2.5 text-xs font-extrabold text-wpx-navy-950"
                    @click="showCreate = !showCreate"
                >
                    {{ showCreate ? 'Fermer' : '+ Créer une offre' }}
                </button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="bg-wpx-success/15 text-wpx-success rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ publishedCount }} publiée{{ publishedCount > 1 ? 's' : '' }}
                </span>
                <span class="bg-wpx-warning/15 text-wpx-gold rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ draftCount }} brouillon{{ draftCount > 1 ? 's' : '' }}
                </span>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/15 text-wpx-danger rounded-wpx-md p-3 text-sm">{{ error }}</p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <form
                v-if="showCreate"
                class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark"
                @submit.prevent="createPlan"
            >
                <h3 class="text-base font-extrabold">Nouvelle offre</h3>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-5">
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Nom</span>
                        <input v-model="createForm.plan_name" required placeholder="Premium" class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Code</span>
                        <input v-model="createForm.plan_code" required placeholder="PREMIUM" class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Niveau de récompense</span>
                        <select v-model="createForm.economic_class_id" required class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm">
                            <option value="" disabled>Choisir</option>
                            <option v-for="level in levels" :key="level.id" :value="level.id">{{ level.code }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Prix (FCFA)</span>
                        <input v-model.number="createForm.price_minor" type="number" min="0" required class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm" />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Durée (jours)</span>
                        <input v-model.number="createForm.duration_days" type="number" min="1" required class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm" />
                    </label>
                </div>
                <button type="submit" :disabled="busy === 'create'" class="bg-wpx-success mt-4 rounded-wpx-md px-4 py-2.5 text-xs font-extrabold text-wpx-navy-950 disabled:opacity-50">
                    Créer le brouillon
                </button>
            </form>

            <section>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="version in planVersions"
                        :key="version.id"
                        class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-lg font-extrabold">{{ version.plan.name }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">{{ version.plan.code }}</p>
                            </div>
                            <span class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold" :class="statusClasses(version.status)">
                                {{ statusLabel(version.status) }}
                            </span>
                        </div>

                        <template v-if="editing === version.id">
                            <div class="mt-4 flex flex-col gap-3">
                                <label class="flex flex-col gap-1 text-xs">
                                    <span class="text-wpx-muted-dark">Prix (FCFA)</span>
                                    <input v-model.number="editForm.price_minor" type="number" min="0" class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2 text-sm" />
                                </label>
                                <label class="flex flex-col gap-1 text-xs">
                                    <span class="text-wpx-muted-dark">Durée (jours)</span>
                                    <input v-model.number="editForm.duration_days" type="number" min="1" class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2 text-sm" />
                                </label>
                                <div class="flex gap-2">
                                    <button type="button" :disabled="busy === version.id" class="bg-wpx-success rounded-wpx-md px-3 py-2 text-xs font-extrabold text-wpx-navy-950" @click="saveEdit(version)">Enregistrer</button>
                                    <button type="button" class="text-wpx-muted-dark px-2 text-xs font-bold" @click="editing = null">Annuler</button>
                                </div>
                            </div>
                        </template>

                        <template v-else>
                            <div class="mt-5">
                                <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase tracking-wide">Prix</p>
                                <p class="mt-1 text-2xl font-extrabold">
                                    {{ version.price_minor === 0 ? 'Gratuit' : `${numberFormatter.format(version.price_minor)} FCFA` }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 text-xs">{{ version.duration_days }} jours</p>
                            </div>
                            <div class="border-wpx-border-dark mt-4 border-t pt-4">
                                <p v-if="version.status === 'published'" class="text-wpx-success text-xs font-bold">Visible actuellement dans Mon Espace.</p>
                                <p v-else-if="version.status === 'draft'" class="text-wpx-gold text-xs font-bold">Invisible pour les membres tant qu’elle reste en brouillon.</p>
                                <p v-else class="text-wpx-danger text-xs font-bold">Cette offre n’est plus proposée.</p>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button v-if="version.status === 'draft'" type="button" class="border-wpx-border-dark text-wpx-cyan rounded-wpx-md border px-3 py-2 text-xs font-bold" @click="startEdit(version)">Modifier</button>
                                <button v-if="version.status === 'draft'" type="button" :disabled="busy === version.id" class="bg-wpx-success rounded-wpx-md px-3 py-2 text-xs font-extrabold text-wpx-navy-950 disabled:opacity-50" @click="publish(version)">Publier</button>
                                <button v-if="version.status === 'published'" type="button" :disabled="busy === version.id" class="border-wpx-danger/40 text-wpx-danger rounded-wpx-md border px-3 py-2 text-xs font-bold disabled:opacity-50" @click="suspend(version)">Suspendre</button>
                            </div>
                        </template>
                    </article>
                </div>
            </section>

            <details class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 shadow-wpx-card-dark">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
                    <div>
                        <p class="text-base font-extrabold">Récompenses & quotas</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Réglages économiques avancés associés aux niveaux Gratuit, Premium, Gold et Platine.</p>
                    </div>
                    <span class="text-wpx-cyan text-xs font-extrabold">Ouvrir</span>
                </summary>
                <div class="border-wpx-border-dark grid grid-cols-1 gap-3 border-t p-5 md:grid-cols-2 xl:grid-cols-4">
                    <div v-for="level in levels" :key="level.id" class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-950 p-4">
                        <p class="font-extrabold">{{ level.code }}</p>
                        <p class="text-wpx-cyan mt-1 text-xs font-bold">{{ rewardAdvantage(level) }}</p>
                        <label class="mt-4 block text-xs">
                            <span class="text-wpx-muted-dark">Gain par vue complète (WP)</span>
                            <input v-model.number="rewardForms[level.id].reward_per_complete_view_minor" type="number" min="1" class="border-wpx-border-dark mt-1 w-full rounded-wpx-md border bg-wpx-navy-850 px-3 py-2 text-sm" />
                        </label>
                        <label class="mt-3 block text-xs">
                            <span class="text-wpx-muted-dark">Publicités maximum / mois</span>
                            <input v-model.number="rewardForms[level.id].quota_monthly" type="number" min="1" class="border-wpx-border-dark mt-1 w-full rounded-wpx-md border bg-wpx-navy-850 px-3 py-2 text-sm" />
                        </label>
                        <button type="button" :disabled="busy === level.id" class="text-wpx-cyan mt-3 text-xs font-extrabold disabled:opacity-50" @click="saveReward(level)">Enregistrer</button>
                    </div>
                </div>
            </details>
        </template>
    </div>
</template>
