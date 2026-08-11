<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

interface TaxonomyEntry {
    id: string;
    code: string;
    label: string;
    declared: boolean;
    answer: boolean | null;
    declared_at: string | null;
}

const CATEGORY_META: Record<string, { title: string; icon: string }> = {
    demographic: { title: 'À propos de moi', icon: '👤' },
    possession: { title: 'Ce que je possède', icon: '📦' },
    usage: { title: "Ce que j'utilise", icon: '📶' },
    interest: { title: "Ce qui m'intéresse", icon: '✨' },
    project: { title: 'Mes projets', icon: '🎯' },
    situation: { title: 'Ma situation', icon: '🧭' },
    territory: { title: 'Ma zone approximative', icon: '📍' },
};

const props = withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const categories = ref<Record<string, TaxonomyEntry[]>>({});
const loading = ref(true);
const busy = ref<string | null>(null);
const currentIndex = ref(0);

const orderedCategories = computed(() =>
    Object.keys(categories.value).sort(
        (a, b) => Object.keys(CATEGORY_META).indexOf(a) - Object.keys(CATEGORY_META).indexOf(b),
    ),
);

const entries = computed(() => orderedCategories.value.flatMap((category) => categories.value[category] ?? []));
const currentEntry = computed(() => entries.value[currentIndex.value] ?? null);
const answeredCount = computed(
    () =>
        Object.values(categories.value)
            .flat()
            .filter((entry) => entry.answer !== null).length,
);

const totalCount = computed(() => Object.values(categories.value).flat().length);

const percent = computed(() =>
    totalCount.value === 0 ? 0 : Math.round((answeredCount.value / totalCount.value) * 100),
);

const nextSuggestions = computed(() =>
    orderedCategories.value
        .flatMap((category) => categories.value[category] ?? [])
        .filter((entry) => entry.answer === null)
        .slice(0, 2)
        .map((entry) => entry.label),
);

defineExpose({ percent, nextSuggestions });

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/me/smart-profile');
        categories.value = data.categories;
    } finally {
        loading.value = false;
    }
}

function questionFor(entry: TaxonomyEntry): string {
    const label = entry.label.replace(/^Genre déclaré : /, 'Vous identifiez-vous comme ');
    if (entry.code.startsWith('demographic.gender.')) return `${label} ?`;
    if (entry.label.startsWith('Possède ')) return `Possédez-vous ${entry.label.slice(8).toLowerCase()} ?`;
    if (entry.label.startsWith('Utilise ')) return `Utilisez-vous ${entry.label.slice(8).toLowerCase()} ?`;
    if (entry.label.startsWith('Intéressé par '))
        return `Êtes-vous intéressé(e) par ${entry.label.slice(14).toLowerCase()} ?`;
    if (entry.label.startsWith('Projette de '))
        return `Avez-vous le projet de ${entry.label.slice(11).toLowerCase()} ?`;
    return `${entry.label} vous correspond-il ?`;
}

function move(offset: number): void {
    currentIndex.value = Math.min(Math.max(currentIndex.value + offset, 0), Math.max(entries.value.length - 1, 0));
}

async function answer(entry: TaxonomyEntry, value: boolean): Promise<void> {
    busy.value = entry.id;
    try {
        await http.post(`/me/smart-profile/${entry.id}`, { answer: value });
        await load();
        move(1);
    } finally {
        busy.value = null;
    }
}

async function postpone(entry: TaxonomyEntry): Promise<void> {
    if (entry.answer !== null) {
        busy.value = entry.id;
        try {
            await http.delete(`/me/smart-profile/${entry.id}`);
            await load();
        } finally {
            busy.value = null;
        }
    }
    move(1);
}

void load();
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-4 p-4">
        <div v-if="!props.compact">
            <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Profil intelligent</p>
            <p v-if="!loading" class="text-wpx-white-soft mt-1 text-sm">
                {{ answeredCount }} réponse{{ answeredCount > 1 ? 's' : '' }} sur {{ totalCount }} questions
            </p>
            <p class="text-wpx-muted-dark mt-1 text-xs">
                Facultatif et corrigible à tout moment. Ces informations restent internes à Wasplex — jamais partagées
                avec un annonceur.
            </p>
            <div v-if="!loading" class="bg-wpx-navy-750 mt-3 h-1.5 overflow-hidden rounded-full">
                <div
                    class="from-wpx-blue to-wpx-gold h-full bg-gradient-to-r transition-[width] duration-300"
                    :style="{ width: `${percent}%` }"
                />
            </div>
        </div>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <div v-else-if="currentEntry" class="rounded-wpx-lg bg-wpx-navy-750 border-wpx-border-dark border p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-wpx-cyan text-xs font-semibold">
                    {{ CATEGORY_META[currentEntry.code.split('.')[0]]?.icon ?? '•' }}
                    {{ CATEGORY_META[currentEntry.code.split('.')[0]]?.title ?? 'Votre profil' }}
                </p>
                <span class="text-wpx-muted-dark text-[11px]">{{ currentIndex + 1 }} / {{ totalCount }}</span>
            </div>

            <p class="text-wpx-white-soft mt-3 text-base font-semibold">{{ questionFor(currentEntry) }}</p>
            <p class="text-wpx-muted-dark mt-1 text-xs">
                Votre réponse reste modifiable et n’est jamais montrée à l’annonceur.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="rounded-wpx-md border px-3 py-2.5 text-sm font-bold disabled:opacity-50"
                    :class="
                        currentEntry.answer === true
                            ? 'border-wpx-cyan bg-wpx-cyan text-wpx-navy-950'
                            : 'border-wpx-border-dark text-wpx-white-soft'
                    "
                    :disabled="busy === currentEntry.id"
                    @click="answer(currentEntry, true)"
                >
                    Oui
                </button>
                <button
                    type="button"
                    class="rounded-wpx-md border px-3 py-2.5 text-sm font-bold disabled:opacity-50"
                    :class="
                        currentEntry.answer === false
                            ? 'border-wpx-gold bg-wpx-gold text-wpx-navy-950'
                            : 'border-wpx-border-dark text-wpx-white-soft'
                    "
                    :disabled="busy === currentEntry.id"
                    @click="answer(currentEntry, false)"
                >
                    Non
                </button>
            </div>
            <button
                type="button"
                class="text-wpx-muted-dark mt-3 w-full text-xs underline disabled:opacity-50"
                :disabled="busy === currentEntry.id"
                @click="postpone(currentEntry)"
            >
                {{ currentEntry.answer === null ? 'Je préfère ne pas répondre' : 'Effacer ma réponse' }}
            </button>

            <div class="mt-4 flex items-center justify-between">
                <button
                    type="button"
                    class="text-wpx-muted-dark text-xs disabled:opacity-30"
                    :disabled="currentIndex === 0"
                    @click="move(-1)"
                >
                    ← Précédente
                </button>
                <div class="flex max-w-[65%] gap-1 overflow-hidden">
                    <span
                        v-for="(entry, index) in entries"
                        :key="entry.id"
                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                        :class="
                            index === currentIndex
                                ? 'bg-wpx-cyan'
                                : entry.answer !== null
                                  ? 'bg-wpx-success'
                                  : 'bg-wpx-border-dark'
                        "
                    />
                </div>
                <button
                    type="button"
                    class="text-wpx-muted-dark text-xs disabled:opacity-30"
                    :disabled="currentIndex >= totalCount - 1"
                    @click="move(1)"
                >
                    Suivante →
                </button>
            </div>
        </div>

        <p v-if="!loading && totalCount === 0" class="text-wpx-muted-dark text-sm">
            Aucune information de profil disponible pour le moment.
        </p>
    </div>
</template>
