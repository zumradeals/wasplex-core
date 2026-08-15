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
const myLives = ref<LiveSummary[]>([]);
const selected = ref<LiveSummary | null>(null);
const viewerSessionId = ref<string | null>(null);
const showCreate = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const form = ref({
    title: '',
    description: '',
    category: 'general',
    language: 'fr',
    visibility: 'public' as 'public' | 'unlisted',
    scheduled_at: '',
    planned_duration_minutes: '60',
});

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
    if (!value) return 'Maintenant';

    return new Intl.DateTimeFormat('fr-FR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

async function load(): Promise<void> {
    error.value = null;
    try {
        const [publicResponse, mineResponse] = await Promise.all([http.get('/lives'), http.get('/creator/lives')]);
        publicLives.value = publicResponse.data.lives ?? [];
        myLives.value = mineResponse.data.lives ?? [];

        if (selected.value) {
            const refreshed = [...publicLives.value, ...myLives.value].find((live) => live.id === selected.value?.id);
            if (refreshed) selected.value = refreshed;
        }
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function createLive(): Promise<void> {
    if (form.value.title.trim() === '') return;
    busy.value = true;
    error.value = null;
    try {
        const payload = {
            title: form.value.title.trim(),
            description: form.value.description.trim() || null,
            category: form.value.category.trim() || 'general',
            language: form.value.language.trim() || 'fr',
            visibility: form.value.visibility,
            scheduled_at: form.value.scheduled_at || null,
            planned_duration_minutes: form.value.planned_duration_minutes
                ? Number(form.value.planned_duration_minutes)
                : null,
            replay_policy: 'disabled',
        };
        const { data } = await http.post('/creator/lives', payload);
        selected.value = data.live;
        showCreate.value = false;
        form.value = {
            title: '',
            description: '',
            category: 'general',
            language: 'fr',
            visibility: 'public',
            scheduled_at: '',
            planned_duration_minutes: '60',
        };
        await load();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function creatorAction(live: LiveSummary, action: 'start' | 'pause' | 'resume' | 'end'): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/creator/lives/${live.id}/${action}`);
        selected.value = data.live;
        await load();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
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

onMounted(load);
</script>

<template>
    <div class="mx-auto min-h-screen w-full max-w-md px-4 py-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-gold text-[10px] font-bold tracking-[0.18em] uppercase">P018-A · Live Wasplex</p>
                <h1 class="text-wpx-white-soft mt-1 text-2xl font-extrabold">Live standard</h1>
                <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                    Créez, programmez et ouvrez une salle Live. Aucun WP n’est distribué dans cette phase.
                </p>
            </div>
            <button
                type="button"
                aria-label="Fermer"
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
                    <button type="button" class="text-wpx-muted-dark text-xs font-semibold" @click="selected = null">
                        Fermer
                    </button>
                </div>

                <div
                    class="bg-wpx-navy-950 mt-4 flex aspect-video items-center justify-center rounded-2xl px-5 text-center"
                >
                    <div>
                        <span class="text-3xl">◉</span>
                        <p class="text-wpx-white-soft mt-2 text-sm font-bold">
                            {{ selected.status === 'paused' ? 'Live en pause' : 'Salle Live ouverte' }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                            La salle, les présences et le cycle de diffusion sont opérationnels. Le transport vidéo
                            externe sera branché dans le lot média suivant.
                        </p>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Spectateurs actifs</p>
                        <p class="text-wpx-white-soft mt-1 text-xl font-extrabold">{{ selected.viewer_count }}</p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Durée prévue</p>
                        <p class="text-wpx-white-soft mt-1 text-xl font-extrabold">
                            {{ selected.planned_duration_minutes ?? '—'
                            }}<span v-if="selected.planned_duration_minutes" class="text-xs"> min</span>
                        </p>
                    </div>
                </div>

                <p v-if="selected.description" class="text-wpx-muted-dark mt-4 text-xs leading-relaxed">
                    {{ selected.description }}
                </p>

                <div v-if="selected.is_owner" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="selected.status === 'draft' || selected.status === 'scheduled'"
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 flex-1 rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold"
                        :disabled="busy"
                        @click="creatorAction(selected, 'start')"
                    >
                        Démarrer le Live
                    </button>
                    <button
                        v-if="selected.status === 'live'"
                        type="button"
                        class="border-wpx-border-dark text-wpx-white-soft flex-1 rounded-xl border px-4 py-3 text-sm font-bold"
                        :disabled="busy"
                        @click="creatorAction(selected, 'pause')"
                    >
                        Mettre en pause
                    </button>
                    <button
                        v-if="selected.status === 'paused'"
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 flex-1 rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold"
                        :disabled="busy"
                        @click="creatorAction(selected, 'resume')"
                    >
                        Reprendre
                    </button>
                    <button
                        v-if="selected.status === 'live' || selected.status === 'paused'"
                        type="button"
                        class="border-wpx-danger/35 text-wpx-danger flex-1 rounded-xl border px-4 py-3 text-sm font-bold"
                        :disabled="busy"
                        @click="creatorAction(selected, 'end')"
                    >
                        Terminer
                    </button>
                </div>

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
            <button
                type="button"
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-2xl bg-gradient-to-r px-4 py-3.5 text-sm font-extrabold"
                @click="showCreate = !showCreate"
            >
                {{ showCreate ? 'Fermer la création' : '+ Créer un Live' }}
            </button>

            <section v-if="showCreate" class="border-wpx-border-dark bg-wpx-navy-850 mt-3 rounded-2xl border p-4">
                <h2 class="text-wpx-white-soft text-base font-bold">Nouveau Live standard</h2>
                <p class="text-wpx-muted-dark mt-1 text-[11px]">
                    Pas de sponsorisation ni de rémunération dans P018-A.
                </p>

                <label class="text-wpx-muted-dark mt-4 block text-[11px]">Titre</label>
                <input
                    v-model="form.title"
                    maxlength="140"
                    placeholder="Ex. Questions-réponses avec la communauté"
                    class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none"
                />

                <label class="text-wpx-muted-dark mt-3 block text-[11px]">Description · facultative</label>
                <textarea
                    v-model="form.description"
                    rows="3"
                    maxlength="2000"
                    class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full resize-none rounded-xl border px-3 py-3 text-sm outline-none"
                ></textarea>

                <div class="mt-3 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-wpx-muted-dark block text-[11px]">Date · facultative</label>
                        <input
                            v-model="form.scheduled_at"
                            type="datetime-local"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-xs outline-none"
                        />
                    </div>
                    <div>
                        <label class="text-wpx-muted-dark block text-[11px]">Durée prévue</label>
                        <input
                            v-model="form.planned_duration_minutes"
                            type="number"
                            min="5"
                            max="720"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none"
                        />
                    </div>
                </div>

                <button
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                    :disabled="busy || form.title.trim() === ''"
                    @click="createLive"
                >
                    {{ busy ? 'Création…' : 'Créer le Live' }}
                </button>
            </section>

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
                                :disabled="busy"
                                @click="live.is_owner ? (selected = live) : joinLive(live)"
                            >
                                {{ live.is_owner ? 'Piloter' : 'Entrer' }}
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

            <section class="mt-6 pb-8">
                <h2 class="text-wpx-white-soft text-base font-extrabold">Mes Lives</h2>
                <div v-if="myLives.length" class="mt-2 space-y-2">
                    <button
                        v-for="live in myLives"
                        :key="live.id"
                        type="button"
                        class="border-wpx-border-dark bg-wpx-navy-850 flex w-full items-center justify-between gap-3 rounded-2xl border p-4 text-left"
                        @click="selected = live"
                    >
                        <span>
                            <span class="text-wpx-white-soft block text-sm font-bold">{{ live.title }}</span>
                            <span class="text-wpx-muted-dark mt-1 block text-[11px]">
                                {{ statusLabel(live.status) }} ·
                                {{ live.scheduled_at ? formatDate(live.scheduled_at) : 'sans programmation' }}
                            </span>
                        </span>
                        <span class="text-wpx-gold text-lg">›</span>
                    </button>
                </div>
                <p v-else class="text-wpx-muted-dark mt-3 text-xs">Aucun Live créé pour le moment.</p>
            </section>
        </template>
    </div>
</template>
