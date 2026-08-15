<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type LiveStatus = 'draft' | 'scheduled' | 'live' | 'paused' | 'ended';

interface LiveSummary {
    id: string;
    title: string;
    description: string | null;
    category: string;
    language: string;
    visibility: 'public' | 'unlisted';
    status: LiveStatus;
    scheduled_at: string | null;
    planned_duration_minutes: number | null;
    started_at: string | null;
    ended_at: string | null;
    replay_policy: 'disabled' | 'available';
    owner: { display_name: string };
    viewer_count: number;
    is_owner: boolean;
    can_join: boolean;
    stream: { status: string | null; provider: string | null; media_ready: boolean };
}

interface ApiError {
    response?: { data?: { message?: string } };
}

const emit = defineEmits<{ close: [] }>();
const publicLives = ref<LiveSummary[]>([]);
const selected = ref<LiveSummary | null>(null);
const viewerSessionId = ref<string | null>(null);
const busy = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);

const activeLives = computed(() =>
    publicLives.value.filter((live) => live.status === 'live' || live.status === 'paused'),
);
const scheduledLives = computed(() => publicLives.value.filter((live) => live.status === 'scheduled'));

function messageFrom(cause: unknown): string {
    return (cause as ApiError)?.response?.data?.message ?? 'Une erreur est survenue. Réessayez.';
}

function statusLabel(status: LiveStatus): string {
    return {
        draft: 'Brouillon',
        scheduled: 'Programmé',
        live: 'En direct',
        paused: 'En pause',
        ended: 'Terminé',
    }[status];
}

function formatDate(value: string | null): string {
    if (!value) return 'À confirmer';

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/lives');
        publicLives.value = data.lives ?? [];

        if (selected.value) {
            const refreshed = publicLives.value.find((live) => live.id === selected.value?.id);
            if (refreshed) selected.value = refreshed;
        }
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        loading.value = false;
    }
}

async function joinLive(live: LiveSummary): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/lives/${live.id}/join`);
        selected.value = data.live;
        viewerSessionId.value = data.viewer_session.id;
        await load();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function leaveLive(): Promise<void> {
    if (!selected.value) return;
    busy.value = true;
    error.value = null;
    try {
        await http.post(`/lives/${selected.value.id}/leave`);
        viewerSessionId.value = null;
        selected.value = null;
        await load();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

function closeSelected(): void {
    if (viewerSessionId.value) {
        void leaveLive();
        return;
    }

    selected.value = null;
}

onMounted(load);
</script>

<template>
    <div class="mx-auto min-h-screen w-full max-w-md px-4 py-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-danger text-[10px] font-bold tracking-[0.18em] uppercase">Live Wasplex</p>
                <h1 class="text-wpx-white-soft mt-1 text-2xl font-extrabold">En direct</h1>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Retrouvez les Lives publiés par les annonceurs Wasplex et entrez dans une salle en cours.
                </p>
            </div>
            <button
                type="button"
                aria-label="Retour au Feed"
                class="bg-wpx-navy-750 text-wpx-white-soft flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl"
                @click="emit('close')"
            >
                ×
            </button>
        </div>

        <p v-if="error" class="bg-wpx-danger/12 text-wpx-danger mt-4 rounded-xl px-3 py-2.5 text-xs" aria-live="polite">
            {{ error }}
        </p>

        <section v-if="selected" class="border-wpx-border-dark bg-wpx-navy-850 mt-5 overflow-hidden rounded-3xl border">
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                            :class="
                                selected.status === 'live'
                                    ? 'bg-wpx-danger/15 text-wpx-danger'
                                    : 'bg-wpx-gold/10 text-wpx-gold'
                            "
                        >
                            {{ statusLabel(selected.status) }}
                        </span>
                        <h2 class="text-wpx-white-soft mt-3 text-xl font-extrabold">{{ selected.title }}</h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">{{ selected.owner.display_name }}</p>
                    </div>
                    <button type="button" class="text-wpx-muted-dark text-xs font-semibold" @click="closeSelected">
                        Fermer
                    </button>
                </div>

                <div class="bg-wpx-navy-950 mt-4 flex aspect-video items-center justify-center rounded-2xl px-5 text-center">
                    <div>
                        <span class="text-3xl">◉</span>
                        <p class="text-wpx-white-soft mt-2 text-sm font-bold">
                            {{ selected.status === 'paused' ? 'Live en pause' : selected.status === 'scheduled' ? 'Live à venir' : 'Salle Live ouverte' }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                            Le cycle Live et les présences sont actifs. La diffusion vidéo réelle sera branchée dans le lot média suivant.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Spectateurs actifs</p>
                        <p class="text-wpx-white-soft mt-1 text-xl font-extrabold">{{ selected.viewer_count }}</p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Horaire</p>
                        <p class="text-wpx-white-soft mt-1 text-xs font-extrabold">
                            {{ selected.status === 'scheduled' ? formatDate(selected.scheduled_at) : 'Maintenant' }}
                        </p>
                    </div>
                </div>

                <p v-if="selected.description" class="text-wpx-muted-dark mt-4 text-xs leading-relaxed">
                    {{ selected.description }}
                </p>

                <button
                    v-if="selected.can_join && !viewerSessionId"
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                    :disabled="busy"
                    @click="joinLive(selected)"
                >
                    {{ busy ? 'Entrée…' : 'Entrer dans le Live' }}
                </button>
                <button
                    v-else-if="viewerSessionId"
                    type="button"
                    class="border-wpx-border-dark text-wpx-white-soft mt-4 w-full rounded-xl border px-4 py-3 text-sm font-bold"
                    :disabled="busy"
                    @click="leaveLive"
                >
                    Quitter le Live
                </button>
            </div>
        </section>

        <template v-else>
            <div v-if="loading" class="text-wpx-muted-dark mt-10 text-center text-sm">Chargement des Lives…</div>

            <template v-else>
                <section v-if="activeLives.length" class="mt-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-wpx-white-soft text-base font-extrabold">En direct</h2>
                        <button type="button" class="text-wpx-blue text-xs font-semibold" @click="load">Actualiser</button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <article
                            v-for="live in activeLives"
                            :key="live.id"
                            class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-wpx-danger text-[10px] font-extrabold uppercase">
                                        ● {{ statusLabel(live.status) }}
                                    </p>
                                    <h3 class="text-wpx-white-soft mt-1 text-sm font-bold">{{ live.title }}</h3>
                                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                                        {{ live.owner.display_name }} · {{ live.viewer_count }} spectateur(s)
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="bg-wpx-blue/15 text-wpx-blue rounded-xl px-3 py-2 text-xs font-bold"
                                    @click="selected = live"
                                >
                                    Voir
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section v-if="scheduledLives.length" class="mt-6">
                    <h2 class="text-wpx-white-soft text-base font-extrabold">À venir</h2>
                    <div class="mt-2 space-y-2">
                        <button
                            v-for="live in scheduledLives"
                            :key="live.id"
                            type="button"
                            class="border-wpx-border-dark bg-wpx-navy-850 w-full rounded-2xl border p-4 text-left"
                            @click="selected = live"
                        >
                            <p class="text-wpx-gold text-[10px] font-bold uppercase">{{ formatDate(live.scheduled_at) }}</p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ live.title }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ live.owner.display_name }}</p>
                        </button>
                    </div>
                </section>

                <section v-if="activeLives.length === 0 && scheduledLives.length === 0" class="mt-10 text-center">
                    <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-3xl border px-6 py-10">
                        <p class="text-wpx-white-soft text-sm font-bold">Aucun Live pour le moment</p>
                        <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                            Les Lives créés et programmés depuis le Studio annonceur apparaîtront ici.
                        </p>
                        <button type="button" class="text-wpx-blue mt-4 text-xs font-bold" @click="load">Actualiser</button>
                    </div>
                </section>
            </template>
        </template>
    </div>
</template>
