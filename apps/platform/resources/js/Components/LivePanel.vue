<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import LiveRealtimeRoom from '@/Components/LiveRealtimeRoom.vue';
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
    stream: { status: string | null; provider: string | null; room: string | null; media_ready: boolean };
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
    <div class="mx-auto min-h-screen w-full max-w-md px-3 py-4 sm:px-4 sm:py-5">
        <div class="flex items-start justify-between gap-3 px-1">
            <div>
                <p class="text-wpx-danger text-[10px] font-bold tracking-[0.18em] uppercase">Live Wasplex</p>
                <h1 class="text-wpx-white-soft mt-1 text-2xl font-extrabold">En direct</h1>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Regardez le direct et, si l’hôte vous accepte, montez vous aussi en caméra dans le Live.
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

        <section v-if="selected" class="mt-4 overflow-hidden rounded-3xl">
            <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-3">
                <div class="flex items-start justify-between gap-3 px-1 pb-3">
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
                        <h2 class="text-wpx-white-soft mt-2 text-lg font-extrabold">{{ selected.title }}</h2>
                        <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ selected.owner.display_name }}</p>
                    </div>
                    <button type="button" class="text-wpx-muted-dark text-xs font-semibold" @click="closeSelected">
                        Fermer
                    </button>
                </div>

                <LiveRealtimeRoom
                    v-if="selected.status === 'live' && viewerSessionId"
                    :live-id="selected.id"
                    mode="viewer"
                    :viewer-count="selected.viewer_count"
                />

                <div
                    v-else-if="selected.status === 'paused'"
                    class="bg-wpx-navy-950 rounded-3xl px-5 py-14 text-center"
                >
                    <p class="text-wpx-white-soft text-sm font-bold">⏸ Le Live est momentanément en pause</p>
                    <p class="text-wpx-muted-dark mt-2 text-[11px]">
                        Restez dans la salle : l’hôte peut reprendre le direct.
                    </p>
                </div>

                <div v-else class="bg-wpx-navy-950 rounded-3xl px-5 py-12 text-center">
                    <p class="text-wpx-white-soft text-sm font-bold">
                        {{ selected.status === 'scheduled' ? 'Live à venir' : 'Entrez dans la salle' }}
                    </p>
                    <p class="text-wpx-muted-dark mt-2 text-[11px]">
                        {{
                            selected.status === 'scheduled'
                                ? formatDate(selected.scheduled_at)
                                : 'La vidéo démarrera dès votre entrée.'
                        }}
                    </p>
                </div>

                <p v-if="selected.description" class="text-wpx-muted-dark px-1 pt-3 text-xs leading-relaxed">
                    {{ selected.description }}
                </p>

                <button
                    v-if="selected.can_join && !viewerSessionId"
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                    :disabled="busy"
                    @click="joinLive(selected)"
                >
                    {{ busy ? 'Entrée…' : '🔴 Entrer dans le Live' }}
                </button>
                <button
                    v-else-if="viewerSessionId"
                    type="button"
                    class="border-wpx-border-dark text-wpx-white-soft mt-3 w-full rounded-xl border px-4 py-3 text-sm font-bold"
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
                    <div class="flex items-center justify-between px-1">
                        <h2 class="text-wpx-white-soft text-base font-extrabold">En direct maintenant</h2>
                        <button type="button" class="text-wpx-blue text-xs font-semibold" @click="load">
                            Actualiser
                        </button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <button
                            v-for="live in activeLives"
                            :key="live.id"
                            type="button"
                            class="border-wpx-border-dark bg-wpx-navy-850 relative w-full overflow-hidden rounded-3xl border p-4 text-left"
                            @click="selected = live"
                        >
                            <div class="absolute inset-y-0 left-0 w-1 bg-red-600"></div>
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-wpx-danger text-[10px] font-extrabold uppercase">
                                        ● {{ statusLabel(live.status) }}
                                    </p>
                                    <h3 class="text-wpx-white-soft mt-1 text-sm font-bold">{{ live.title }}</h3>
                                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                                        {{ live.owner.display_name }} · 👁 {{ live.viewer_count }}
                                    </p>
                                </div>
                                <span
                                    class="bg-wpx-danger/15 text-wpx-danger rounded-full px-3 py-2 text-[10px] font-black"
                                    >Voir</span
                                >
                            </div>
                        </button>
                    </div>
                </section>

                <section v-if="scheduledLives.length" class="mt-6">
                    <h2 class="text-wpx-white-soft px-1 text-base font-extrabold">À venir</h2>
                    <div class="mt-2 space-y-2">
                        <button
                            v-for="live in scheduledLives"
                            :key="live.id"
                            type="button"
                            class="border-wpx-border-dark bg-wpx-navy-850 w-full rounded-2xl border p-4 text-left"
                            @click="selected = live"
                        >
                            <p class="text-wpx-gold text-[10px] font-bold uppercase">
                                {{ formatDate(live.scheduled_at) }}
                            </p>
                            <p class="text-wpx-white-soft mt-1 text-sm font-bold">{{ live.title }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ live.owner.display_name }}</p>
                        </button>
                    </div>
                </section>

                <section v-if="activeLives.length === 0 && scheduledLives.length === 0" class="mt-10 text-center">
                    <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-3xl border px-6 py-10">
                        <p class="text-wpx-white-soft text-sm font-bold">Aucun Live pour le moment</p>
                        <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                            Les directs lancés depuis le Studio annonceur apparaîtront ici.
                        </p>
                        <button type="button" class="text-wpx-blue mt-4 text-xs font-bold" @click="load">
                            Actualiser
                        </button>
                    </div>
                </section>
            </template>
        </template>
    </div>
</template>
