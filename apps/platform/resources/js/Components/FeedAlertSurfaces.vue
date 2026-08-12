<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface FeedAlert {
    id: string;
    category: string;
    situation: string;
    priority: string;
    title: string;
    summary: string;
    city: string | null;
    area_label: string | null;
}

interface FeedConfiguration {
    useful_content_interval: number;
    circles_enabled: boolean;
    left_rail_enabled: boolean;
    full_screen_enabled: boolean;
}

const props = defineProps<{ showCircles: boolean }>();
const emit = defineEmits<{ closeCircles: [] }>();

const alerts = ref<FeedAlert[]>([]);
const configuration = ref<FeedConfiguration>({
    useful_content_interval: 5,
    circles_enabled: true,
    left_rail_enabled: true,
    full_screen_enabled: true,
});
const selected = ref<FeedAlert | null>(null);

const circles = computed(() => alerts.value.slice(0, 8));
const rail = computed(() => alerts.value.slice(0, 4));

function categoryIcon(category: string): string {
    return { object: '📦', document: '🪪', vehicle: '🚗', person: '🧍', sos: '🆘' }[category] ?? '🔔';
}

function priorityClass(priority: string): string {
    if (priority === 'P0') return 'border-red-400/80 ring-red-500/25';
    if (priority === 'P1') return 'border-orange-400/80 ring-orange-500/25';
    if (priority === 'P2') return 'border-amber-300/70 ring-amber-400/20';
    return 'border-cyan-300/60 ring-cyan-400/15';
}

async function load(): Promise<void> {
    try {
        const [alertsResponse, configResponse] = await Promise.all([
            http.get('/alerts/public', { params: { limit: 12 } }),
            http.get('/alerts/feed-configuration'),
        ]);
        alerts.value = alertsResponse.data.alerts;
        configuration.value = configResponse.data.configuration;
    } catch {
        alerts.value = [];
    }
}

onMounted(load);
</script>

<template>
    <div class="pointer-events-none absolute inset-0 z-20">
        <div
            v-if="props.showCircles && configuration.circles_enabled"
            class="pointer-events-auto absolute inset-x-0 top-[5.4rem] z-30 bg-gradient-to-b from-black/80 via-black/55 to-transparent px-3 pt-2 pb-5"
        >
            <div class="mb-2 flex items-center justify-between">
                <div>
                    <p class="text-[10px] font-black tracking-[0.16em] text-white/75 uppercase">Alertes en cours</p>
                    <p class="text-[9px] text-white/45">Touchez un cercle pour voir l’essentiel</p>
                </div>
                <button type="button" class="flex h-7 w-7 items-center justify-center rounded-full bg-white/10 text-sm text-white" @click="emit('closeCircles')">×</button>
            </div>

            <div v-if="circles.length" class="scrollbar-none flex gap-3 overflow-x-auto pb-1">
                <button v-for="alert in circles" :key="alert.id" type="button" class="w-14 shrink-0 text-center" @click="selected = alert">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full border-2 bg-black/55 text-xl ring-2" :class="priorityClass(alert.priority)">{{ categoryIcon(alert.category) }}</span>
                    <span class="mt-1 block truncate text-[8px] font-bold text-white/85">{{ alert.area_label || alert.city || alert.title }}</span>
                </button>
            </div>
            <div v-else class="rounded-xl bg-white/5 px-3 py-3 text-center text-[10px] text-white/55">Aucune alerte publique active.</div>
        </div>

        <div
            v-if="configuration.left_rail_enabled && rail.length"
            class="pointer-events-auto absolute bottom-28 left-2 z-20 flex flex-col items-center gap-2.5"
            aria-label="Alertes rapides"
        >
            <button
                v-for="alert in rail"
                :key="alert.id"
                type="button"
                class="relative flex h-9 w-9 items-center justify-center rounded-full border bg-black/55 text-base shadow-lg backdrop-blur-sm"
                :class="priorityClass(alert.priority)"
                :aria-label="`Alerte ${alert.title}`"
                @click.stop="selected = alert"
            >
                {{ categoryIcon(alert.category) }}
                <span v-if="alert.priority === 'P0'" class="absolute -top-0.5 -right-0.5 h-2 w-2 animate-pulse rounded-full bg-red-500" />
            </button>
        </div>

        <div v-if="selected" class="pointer-events-auto absolute inset-x-3 bottom-20 z-40 rounded-2xl border border-white/15 bg-black/85 p-3.5 shadow-2xl backdrop-blur-md">
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/8 text-xl">{{ categoryIcon(selected.category) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="text-[9px] font-black text-amber-300">{{ selected.priority }}</span>
                        <span class="text-[9px] text-white/45">{{ selected.area_label || selected.city || 'Zone protégée' }}</span>
                    </div>
                    <p class="mt-0.5 text-xs font-black text-white">{{ selected.title }}</p>
                    <p class="mt-1 line-clamp-3 text-[10px] leading-relaxed text-white/65">{{ selected.summary }}</p>
                </div>
                <button type="button" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/8 text-sm text-white/70" @click="selected = null">×</button>
            </div>
        </div>
    </div>
</template>
