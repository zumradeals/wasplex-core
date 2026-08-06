<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface Configuration {
    id: string;
    status: string;
    frequency_window_hours: number;
    frequency_max_per_window: number;
    fatigue_threshold: number;
    published_at: string | null;
}

const configurations = ref<Configuration[]>([]);
const decisionCounts = ref<Record<string, number>>({});
const busy = ref<string | null>(null);

const newConfiguration = reactive({
    frequency_window_hours: 24,
    frequency_max_per_window: 3,
    fatigue_threshold: 10,
});

const DECISION_LABELS: Record<string, string> = {
    eligible: 'Éligibles',
    ineligible: 'Non éligibles',
    withheld: 'En attente (doute de confidentialité)',
};

async function loadConfigurations(): Promise<void> {
    const { data } = await http.get('/admin/matching/configuration');
    configurations.value = data.configurations;
}

async function loadAudit(): Promise<void> {
    const { data } = await http.get('/admin/matching/audit');
    decisionCounts.value = data.decision_counts;
}

async function createDraft(): Promise<void> {
    await http.post('/admin/matching/configuration', newConfiguration);
    await loadConfigurations();
}

async function publish(configuration: Configuration): Promise<void> {
    busy.value = configuration.id;
    try {
        await http.post(`/admin/matching/configuration/${configuration.id}/publish`);
        await loadConfigurations();
    } finally {
        busy.value = null;
    }
}

function statusClasses(status: string): string {
    return status === 'published'
        ? 'bg-wpx-success/10 text-wpx-success-light'
        : 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(() => {
    void loadConfigurations();
    void loadAudit();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Réglages du Matching</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Ces seuils protègent l'utilisateur contre trop de sollicitations. Ils sont préparés dès maintenant mais
                ne s'appliquent pas encore : aucune publicité réelle n'est encore livrée (arrive avec le Feed).
            </p>

            <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="createDraft">
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Fenêtre (heures)</span>
                    <input
                        v-model.number="newConfiguration.frequency_window_hours"
                        type="number"
                        min="1"
                        class="rounded-wpx-sm border-wpx-border w-24 border px-2 py-1"
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Publicités max. par fenêtre</span>
                    <input
                        v-model.number="newConfiguration.frequency_max_per_window"
                        type="number"
                        min="1"
                        class="rounded-wpx-sm border-wpx-border w-24 border px-2 py-1"
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Seuil de lassitude</span>
                    <input
                        v-model.number="newConfiguration.fatigue_threshold"
                        type="number"
                        min="1"
                        class="rounded-wpx-sm border-wpx-border w-24 border px-2 py-1"
                    />
                </label>
                <button
                    type="submit"
                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold"
                >
                    Créer (brouillon)
                </button>
            </form>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Fenêtre</th>
                        <th class="p-2">Max / fenêtre</th>
                        <th class="p-2">Lassitude</th>
                        <th class="p-2">Statut</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in configurations" :key="c.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2">{{ c.frequency_window_hours }} h</td>
                        <td class="p-2">{{ c.frequency_max_per_window }}</td>
                        <td class="p-2">{{ c.fatigue_threshold }}</td>
                        <td class="p-2">
                            <span
                                class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                :class="statusClasses(c.status)"
                            >
                                {{ c.status }}
                            </span>
                        </td>
                        <td class="p-2 text-right">
                            <button
                                v-if="c.status === 'draft'"
                                class="text-wpx-success-light text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === c.id"
                                @click="publish(c)"
                            >
                                Publier
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Audit du Matching</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Agrégats uniquement — aucune identité de compte, aucun profil publicitaire individuel.
            </p>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="(count, decision) in decisionCounts"
                    :key="decision"
                    class="border-wpx-border text-wpx-text flex justify-between border-b py-1 last:border-0"
                >
                    <span>{{ DECISION_LABELS[decision] ?? decision }}</span>
                    <span class="font-semibold">{{ count }}</span>
                </li>
                <li v-if="Object.keys(decisionCounts).length === 0" class="text-wpx-text-muted text-xs">
                    Aucune décision de Matching pour le moment.
                </li>
            </ul>
        </div>
    </div>
</template>
