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
    eligible: 'Diffusions autorisées',
    ineligible: 'Diffusions écartées',
    withheld: 'Décisions retenues par confidentialité',
};

const DECISION_COLORS: Record<string, string> = {
    eligible: 'text-wpx-success',
    ineligible: 'text-wpx-muted-dark',
    withheld: 'text-wpx-gold',
};

const currentlyPublished = computed(
    () => configurations.value.find((configuration) => configuration.status === 'published') ?? null,
);

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
    <div class="text-wpx-white-soft mx-auto flex max-w-4xl flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">Diffusion & ciblage</p>
            <h2 class="mt-1 text-xl font-extrabold">Protéger les utilisateurs contre trop de publicités.</h2>
            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                Tu règles ici la fréquence maximale. Aucun annonceur ne voit l’identité d’une personne et les critères
                de profil restent déclarés volontairement par les membres.
            </p>
            <div class="bg-wpx-warning/10 text-wpx-gold rounded-wpx-md mt-4 p-3 text-xs">
                Ces paramètres sont versionnés : une modification ne devient la règle active qu’après publication.
            </div>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <section
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5 md:p-6"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-extrabold">Rythme des publicités</h3>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Trois réglages simples pour éviter la fatigue publicitaire.
                        </p>
                    </div>
                    <span
                        class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                        :class="
                            currentlyPublished
                                ? 'bg-wpx-success/15 text-wpx-success'
                                : 'bg-wpx-warning/15 text-wpx-gold'
                        "
                    >
                        {{ currentlyPublished ? 'Règle publiée' : 'Aucune règle publiée' }}
                    </span>
                </div>

                <div class="mt-6 flex flex-col gap-6">
                    <div>
                        <div class="mb-2 flex items-baseline justify-between gap-4">
                            <div>
                                <p class="text-sm font-extrabold">Maximum de publicités</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-xs">
                                    Combien une même personne peut voir sur la période.
                                </p>
                            </div>
                            <span class="text-wpx-cyan text-lg font-extrabold">{{
                                settings.frequency_max_per_window
                            }}</span>
                        </div>
                        <input
                            v-model.number="settings.frequency_max_per_window"
                            type="range"
                            min="1"
                            max="10"
                            class="accent-wpx-cyan w-full"
                        />
                    </div>

                    <div>
                        <div class="mb-2 flex items-baseline justify-between gap-4">
                            <div>
                                <p class="text-sm font-extrabold">Période de contrôle</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-xs">
                                    Après cette durée, le compteur recommence.
                                </p>
                            </div>
                            <span class="text-wpx-cyan text-lg font-extrabold"
                                >{{ settings.frequency_window_hours }} h</span
                            >
                        </div>
                        <input
                            v-model.number="settings.frequency_window_hours"
                            type="range"
                            min="1"
                            max="72"
                            class="accent-wpx-cyan w-full"
                        />
                    </div>

                    <div>
                        <div class="mb-2 flex items-baseline justify-between gap-4">
                            <div>
                                <p class="text-sm font-extrabold">Seuil de lassitude</p>
                                <p class="text-wpx-muted-dark mt-0.5 text-xs">
                                    Plus ce seuil est bas, plus Wasplex espace les publicités tôt.
                                </p>
                            </div>
                            <span class="text-wpx-cyan text-lg font-extrabold">{{ settings.fatigue_threshold }}</span>
                        </div>
                        <input
                            v-model.number="settings.fatigue_threshold"
                            type="range"
                            min="1"
                            max="30"
                            class="accent-wpx-cyan w-full"
                        />
                    </div>
                </div>

                <div
                    class="border-wpx-border-dark mt-6 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-wpx-muted-dark text-xs">
                        <template v-if="currentlyPublished?.published_at">
                            Version actuelle publiée le
                            {{ new Date(currentlyPublished.published_at).toLocaleDateString('fr-FR') }}.
                        </template>
                        <template v-else
                            >Ces valeurs ne protègent pas encore le Feed tant qu’elles ne sont pas publiées.</template
                        >
                    </p>
                    <button
                        type="button"
                        class="from-wpx-blue to-wpx-cyan rounded-wpx-md text-wpx-navy-950 bg-gradient-to-br px-5 py-2.5 text-xs font-extrabold disabled:opacity-50"
                        :disabled="publishing"
                        @click="publishSettings"
                    >
                        {{ publishing ? 'Publication…' : 'Publier ces réglages' }}
                    </button>
                </div>
            </section>

            <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                <h3 class="text-base font-extrabold">Décisions récentes du moteur</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Des totaux uniquement, jamais l’identité d’un utilisateur.
                </p>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div
                        v-for="(label, key) in DECISION_LABELS"
                        :key="key"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4 text-center"
                    >
                        <p class="text-2xl font-extrabold" :class="DECISION_COLORS[key]">
                            {{ decisionCounts[key] ?? 0 }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ label }}</p>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
