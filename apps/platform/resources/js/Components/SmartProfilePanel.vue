<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

interface TaxonomyEntry {
    id: string;
    code: string;
    label: string;
    declared: boolean;
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

const orderedCategories = computed(() =>
    Object.keys(categories.value).sort(
        (a, b) => Object.keys(CATEGORY_META).indexOf(a) - Object.keys(CATEGORY_META).indexOf(b),
    ),
);

const activeCount = computed(
    () =>
        Object.values(categories.value)
            .flat()
            .filter((entry) => entry.declared).length,
);

const totalCount = computed(() => Object.values(categories.value).flat().length);

const percent = computed(() => (totalCount.value === 0 ? 0 : Math.round((activeCount.value / totalCount.value) * 100)));

const nextSuggestions = computed(() =>
    orderedCategories.value
        .flatMap((category) => categories.value[category] ?? [])
        .filter((entry) => !entry.declared)
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

async function toggle(entry: TaxonomyEntry): Promise<void> {
    busy.value = entry.id;
    try {
        if (entry.declared) {
            await http.delete(`/me/smart-profile/${entry.id}`);
        } else {
            await http.post(`/me/smart-profile/${entry.id}`);
        }
        await load();
    } finally {
        busy.value = null;
    }
}

void load();
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-4 p-4">
        <div v-if="!props.compact">
            <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Profil intelligent</p>
            <p v-if="!loading" class="text-wpx-white-soft mt-1 text-sm">
                {{ activeCount }} information{{ activeCount > 1 ? 's' : '' }} active{{ activeCount > 1 ? 's' : '' }} sur
                {{ totalCount }} proposées
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

        <div v-for="category in orderedCategories" v-else :key="category" class="flex flex-col gap-2">
            <p class="text-wpx-white-soft text-sm font-semibold">
                {{ CATEGORY_META[category]?.icon ?? '•' }} {{ CATEGORY_META[category]?.title ?? category }}
            </p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="entry in categories[category]"
                    :key="entry.id"
                    type="button"
                    class="rounded-wpx-md border px-3 py-1.5 text-xs font-medium transition disabled:opacity-50"
                    :class="
                        entry.declared
                            ? 'from-wpx-blue to-wpx-cyan text-wpx-navy-950 border-transparent bg-gradient-to-br'
                            : 'border-wpx-border-dark text-wpx-muted-dark hover:border-wpx-blue'
                    "
                    :disabled="busy === entry.id"
                    @click="toggle(entry)"
                >
                    {{ entry.declared ? '✓ ' : '+ ' }}{{ entry.label }}
                </button>
            </div>
        </div>

        <p v-if="!loading && totalCount === 0" class="text-wpx-muted-dark text-sm">
            Aucune information de profil disponible pour le moment.
        </p>
    </div>
</template>
