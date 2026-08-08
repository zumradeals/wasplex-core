<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
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
const loading = ref(true);
const publishing = ref(false);

const settings = reactive({
    frequency_max_per_window: 3,
    frequency_window_hours: 24,
    fatigue_threshold: 10,
});

const DECISION_LABELS: Record<string, string> = {
    eligible: 'Personnes éligibles',
    ineligible: 'Non éligibles',
    withheld: 'En attente (doute de confidentialité)',
};

const DECISION_COLORS: Record<string, string> = {
    eligible: 'text-wpx-success-light',
    ineligible: 'text-wpx-text-muted',
    withheld: 'text-wpx-warning-light',
};

const currentlyPublished = computed(() => configurations.value.find((c) => c.status === 'published') ?? null);

async function loadConfigurations(): Promise<void> {
    const { data } = await http.get('/admin/matching/configuration');
    configurations.value = data.configurations;

    const latest = [...data.configurations].sort(
        (a: Configuration, b: Configuration) =>
            new Date(b.published_at ?? 0).getTime() - new Date(a.published_at ?? 0).getTime(),
    )[0];
    if (latest) {
        settings.frequency_max_per_window = latest.frequency_max_per_window;
        settings.frequency_window_hours = latest.frequency_window_hours;
        settings.fatigue_threshold = latest.fatigue_threshold;
    }
}

async function loadAudit(): Promise<void> {
    const { data } = await http.get('/admin/matching/audit');
    decisionCounts.value = data.decision_counts;
}

async function publishSettings(): Promise<void> {
    publishing.value = true;
    try {
        const { data } = await http.post('/admin/matching/configuration', settings);
        await http.post(`/admin/matching/configuration/${data.configuration.id}/publish`);
        await loadConfigurations();
    } finally {
        publishing.value = false;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([loadConfigurations(), loadAudit()]);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto flex max-w-3xl flex-col gap-5">
        <div class="border-wpx-blue-light/25 rounded-wpx-md flex gap-3 border bg-white p-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="mt-0.5 shrink-0">
                <circle cx="12" cy="12" r="9" stroke="#075CCF" stroke-width="1.6" />
                <path d="M12 8v.5M12 11v5" stroke="#075CCF" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <p class="text-wpx-text text-xs leading-relaxed">
                Ces réglages protègent les utilisateurs contre trop de publicités. Ils n'affectent pas encore de vraies
                publicités — le Feed n'est pas encore branché dessus.
            </p>
        </div>

        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>

        <template v-else>
            <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-5.5">
                <p class="text-wpx-text text-[15px] font-bold">Combien de publicités un utilisateur peut voir</p>
                <p class="text-wpx-text-muted mb-5 text-xs">Pour éviter de le fatiguer ou de l'agacer.</p>

                <div class="flex flex-col gap-5">
                    <div>
                        <div class="mb-2 flex items-baseline justify-between">
                            <span class="text-wpx-text text-[13px] font-bold">Nombre maximum de publicités</span>
                            <span class="text-wpx-blue-light text-[15px] font-extrabold">
                                {{ settings.frequency_max_per_window }}
                                <span class="text-wpx-text-muted text-xs font-semibold">par période</span>
                            </span>
                        </div>
                        <input
                            v-model.number="settings.frequency_max_per_window"
                            type="range"
                            min="1"
                            max="10"
                            class="accent-wpx-blue-light w-full"
                        />
                    </div>
                    <div>
                        <div class="mb-2 flex items-baseline justify-between">
                            <span class="text-wpx-text text-[13px] font-bold">Sur quelle durée</span>
                            <span class="text-wpx-blue-light text-[15px] font-extrabold">
                                {{ settings.frequency_window_hours }}
                                <span class="text-wpx-text-muted text-xs font-semibold">heures</span>
                            </span>
                        </div>
                        <input
                            v-model.number="settings.frequency_window_hours"
                            type="range"
                            min="1"
                            max="72"
                            class="accent-wpx-blue-light w-full"
                        />
                    </div>
                    <div>
                        <div class="mb-2 flex items-baseline justify-between">
                            <span class="text-wpx-text text-[13px] font-bold">Seuil de lassitude</span>
                            <span class="text-wpx-blue-light text-[15px] font-extrabold">{{
                                settings.fatigue_threshold
                            }}</span>
                        </div>
                        <p class="text-wpx-text-muted mb-2 text-[11px]">
                            Au-delà, on espace davantage les publicités montrées à cette personne.
                        </p>
                        <input
                            v-model.number="settings.fatigue_threshold"
                            type="range"
                            min="1"
                            max="30"
                            class="accent-wpx-blue-light w-full"
                        />
                    </div>
                </div>

                <div class="border-wpx-border/60 mt-6 flex items-center gap-3 border-t pt-4.5">
                    <button
                        type="button"
                        class="rounded-wpx-md bg-wpx-blue-light px-5.5 py-2.5 text-[13px] font-bold text-white disabled:opacity-50"
                        :disabled="publishing"
                        @click="publishSettings"
                    >
                        Publier ces réglages
                    </button>
                    <span class="text-wpx-text-muted text-xs">
                        <template v-if="currentlyPublished?.published_at">
                            Version actuelle publiée le
                            {{ new Date(currentlyPublished.published_at).toLocaleDateString('fr-FR') }}.
                        </template>
                        <template v-else>Aucune version publiée pour le moment.</template>
                    </span>
                </div>
            </div>

            <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface border p-5.5">
                <p class="text-wpx-text text-[15px] font-bold">Ce qui s'est passé récemment</p>
                <p class="text-wpx-text-muted mb-4 text-xs">
                    Uniquement des totaux — jamais l'identité d'une personne.
                </p>
                <div class="grid grid-cols-3 gap-3.5">
                    <div
                        v-for="(label, key) in DECISION_LABELS"
                        :key="key"
                        class="bg-wpx-canvas rounded-wpx-sm p-4 text-center"
                    >
                        <p class="text-2xl font-extrabold" :class="DECISION_COLORS[key]">
                            {{ decisionCounts[key] ?? 0 }}
                        </p>
                        <p class="text-wpx-text-muted mt-1 text-[11px]">{{ label }}</p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
