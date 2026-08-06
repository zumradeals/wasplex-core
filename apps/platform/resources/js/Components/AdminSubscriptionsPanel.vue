<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

interface EconomicClassVersion {
    quota_monthly: number;
    weight_percent: string;
    coefficient: string;
}

interface EconomicClass {
    id: string;
    code: string;
    status: string;
    versions: EconomicClassVersion[];
}

interface PlanVersion {
    id: string;
    status: string;
    price_minor: number;
    currency: string;
    duration_days: number;
    plan: { code: string; name: string };
    economic_class_link: { economic_class: { code: string } } | null;
}

const classes = ref<EconomicClass[]>([]);
const planVersions = ref<PlanVersion[]>([]);
const weightsValid = ref<boolean | null>(null);
const weightsTotal = ref<number | null>(null);
const loading = ref(true);
const busy = ref<string | null>(null);

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [classesRes, plansRes, weightsRes] = await Promise.all([
            http.get('/admin/economic-classes'),
            http.get('/admin/subscriptions/plans'),
            http.post('/admin/economic-classes/validate-weights'),
        ]);
        classes.value = classesRes.data.economic_classes;
        planVersions.value = plansRes.data.plan_versions;
        weightsValid.value = weightsRes.data.valid;
        weightsTotal.value = weightsRes.data.total_weight_percent;
    } finally {
        loading.value = false;
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
    busy.value = version.id;
    try {
        await http.post(`/admin/subscriptions/plans/${version.id}/suspend`);
        await load();
    } finally {
        busy.value = null;
    }
}

function statusClasses(status: string): string {
    if (status === 'published') {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (status === 'suspended') {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Classes économiques</h2>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Code</th>
                        <th class="p-2">Quota mensuel</th>
                        <th class="p-2">Poids</th>
                        <th class="p-2">Coefficient</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in classes" :key="c.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2 font-semibold">{{ c.code }}</td>
                        <td class="p-2">{{ numberFormatter.format(c.versions[0]?.quota_monthly ?? 0) }}</td>
                        <td class="p-2">{{ c.versions[0]?.weight_percent ?? '—' }} %</td>
                        <td class="p-2">{{ c.versions[0]?.coefficient ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
            <p
                v-if="weightsValid !== null"
                class="mt-3 text-xs"
                :class="weightsValid ? 'text-wpx-success-light' : 'text-wpx-danger-light'"
            >
                Somme des poids : {{ weightsTotal }} % — {{ weightsValid ? 'valide (100 %)' : 'invalide' }}
            </p>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-3 text-sm font-semibold">Plans commerciaux</h2>
            <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Plan</th>
                        <th class="p-2">Classe</th>
                        <th class="p-2">Prix</th>
                        <th class="p-2">Durée</th>
                        <th class="p-2">Statut</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="v in planVersions" :key="v.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2">{{ v.plan.name }}</td>
                        <td class="p-2">{{ v.economic_class_link?.economic_class?.code ?? '—' }}</td>
                        <td class="p-2">
                            {{
                                v.price_minor === 0
                                    ? 'Gratuit'
                                    : `${numberFormatter.format(v.price_minor)} ${v.currency}`
                            }}
                        </td>
                        <td class="p-2">{{ v.duration_days }} j</td>
                        <td class="p-2">
                            <span
                                class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                :class="statusClasses(v.status)"
                            >
                                {{ v.status }}
                            </span>
                        </td>
                        <td class="p-2 text-right">
                            <button
                                v-if="v.status !== 'published'"
                                class="text-wpx-success-light mr-2 text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === v.id"
                                @click="publish(v)"
                            >
                                Publier
                            </button>
                            <button
                                v-if="v.status === 'published'"
                                class="text-wpx-danger-light text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === v.id"
                                @click="suspend(v)"
                            >
                                Suspendre
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="text-wpx-text-muted mt-3 text-xs">
                Premium/Gold/Platine restent en brouillon tant que leur prix réel n'a pas été défini
                (docs/chantiers/P004-RAPPORT.md) — aucun prix n'a été inventé par défaut.
            </p>
        </div>
    </div>
</template>
