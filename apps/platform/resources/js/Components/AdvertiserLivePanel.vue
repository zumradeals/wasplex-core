<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import LiveRealtimeRoom from '@/Components/LiveRealtimeRoom.vue';
import LiveSponsorshipPanel from '@/Components/LiveSponsorshipPanel.vue';
import http from '@/lib/http';

type LiveStatus = 'draft' | 'scheduled' | 'live' | 'paused' | 'ended';

interface SponsorshipSummary {
    status: 'draft' | 'quoted' | 'funds_reserved' | 'cancelled';
    segment: { country_code: string | null; economic_classes: string[] } | null;
    latest_quote: {
        id: string;
        status: 'active' | 'consumed' | 'superseded';
        currency: string;
        budget_amount_minor: number;
        wasplex_share_minor: number;
        spectator_envelope_minor: number;
        estimated_reach_min: number;
        estimated_reach_max: number;
        planned_duration_minutes: number;
        block_duration_seconds: number;
        rewarded_seat_capacity: number;
        max_blocks_per_viewer: number;
        funded_blocks: number;
        reward_per_block_minor: number;
        max_reward_per_viewer_minor: number;
        spectator_envelope_remainder_minor: number;
        quoted_at: string | null;
    } | null;
    reservation: {
        status: 'reserved' | 'released';
        amount_minor: number;
        reserved_at: string | null;
        released_at: string | null;
    } | null;
    can_schedule: boolean;
    seat_runtime: {
        active: number;
        offered: number;
        waiting: number;
        available: number;
    } | null;
}

interface LiveSummary {
    id: string;
    type: 'standard' | 'sponsored';
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
    sponsorship: SponsorshipSummary | null;
    owner: { display_name: string };
    viewer_count: number;
    is_owner: boolean;
    can_join: boolean;
    stream: { status: string | null; provider: string | null; room: string | null; media_ready: boolean };
}

interface ApiError {
    response?: { data?: { message?: string } };
}

const myLives = ref<LiveSummary[]>([]);
const selected = ref<LiveSummary | null>(null);
const showCreate = ref(false);
const busy = ref(false);
const loading = ref(true);
const error = ref<string | null>(null);
const previewVideo = ref<HTMLVideoElement | null>(null);
const previewActive = ref(false);
let previewStream: MediaStream | null = null;
const form = ref({
    title: '',
    description: '',
    category: 'general',
    language: 'fr',
    visibility: 'public' as 'public' | 'unlisted',
    scheduled_at: '',
    planned_duration_minutes: '60',
});

const activeCount = computed(
    () => myLives.value.filter((live) => live.status === 'live' || live.status === 'paused').length,
);
const scheduledCount = computed(() => myLives.value.filter((live) => live.status === 'scheduled').length);

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
    if (!value) return 'Sans programmation';

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
        const { data } = await http.get('/advertiser/lives');
        myLives.value = data.lives ?? [];

        if (selected.value) {
            const refreshed = myLives.value.find((live) => live.id === selected.value?.id);
            if (refreshed) selected.value = refreshed;
        }
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        loading.value = false;
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
        const { data } = await http.post('/advertiser/lives', payload);
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

async function startPreview(): Promise<void> {
    error.value = null;
    stopPreview();
    try {
        previewStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user' },
            audio: true,
        });
        if (previewVideo.value) previewVideo.value.srcObject = previewStream;
        previewActive.value = true;
    } catch {
        error.value = 'Autorisez la caméra et le microphone pour préparer votre direct.';
    }
}

function stopPreview(): void {
    previewStream?.getTracks().forEach((track) => track.stop());
    previewStream = null;
    previewActive.value = false;
    if (previewVideo.value) previewVideo.value.srcObject = null;
}

async function creatorAction(live: LiveSummary, action: 'start' | 'pause' | 'resume' | 'end'): Promise<void> {
    busy.value = true;
    error.value = null;
    if (action === 'start') stopPreview();
    try {
        const { data } = await http.post(`/advertiser/lives/${live.id}/${action}`);
        selected.value = data.live;
        await load();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

function selectLive(live: LiveSummary): void {
    stopPreview();
    selected.value = live;
}

function sponsorshipUpdated(live: unknown): void {
    const updated = live as LiveSummary;
    selected.value = updated;
    const index = myLives.value.findIndex((item) => item.id === updated.id);
    if (index !== -1) myLives.value[index] = updated;
}

function closeSelected(): void {
    stopPreview();
    selected.value = null;
}

onMounted(load);
onBeforeUnmount(stopPreview);
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 lg:gap-5">
        <section
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-border-dark overflow-hidden rounded-3xl border bg-gradient-to-br p-5 sm:p-6"
        >
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-wpx-danger text-[10px] font-bold tracking-[0.18em] uppercase">
                        Studio annonceur · Live temps réel
                    </p>
                    <h1 class="text-wpx-white-soft mt-1 text-2xl font-extrabold">Mes Lives</h1>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm leading-relaxed">
                        Direct standard ou sponsorisé, caméra LiveKit, interactions temps réel et financement protégé.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-2xl bg-gradient-to-r px-5 py-3.5 text-sm font-extrabold"
                    @click="showCreate = !showCreate"
                >
                    {{ showCreate ? 'Fermer' : '+ Créer un Live' }}
                </button>
            </div>

            <div class="mt-5 grid grid-cols-3 gap-2.5">
                <div class="bg-wpx-navy-950 rounded-2xl p-3.5">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">Total</p>
                    <p class="text-wpx-white-soft mt-1 text-xl font-extrabold">{{ myLives.length }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-2xl p-3.5">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">En direct</p>
                    <p class="text-wpx-danger mt-1 text-xl font-extrabold">{{ activeCount }}</p>
                </div>
                <div class="bg-wpx-navy-950 rounded-2xl p-3.5">
                    <p class="text-wpx-muted-dark text-[10px] uppercase">À venir</p>
                    <p class="text-wpx-gold mt-1 text-xl font-extrabold">{{ scheduledCount }}</p>
                </div>
            </div>
        </section>

        <p v-if="error" class="bg-wpx-danger/12 text-wpx-danger rounded-xl px-3 py-2.5 text-xs" aria-live="polite">
            {{ error }}
        </p>

        <section v-if="showCreate" class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
            <h2 class="text-wpx-white-soft text-base font-bold">Nouveau Live</h2>
            <p class="text-wpx-muted-dark mt-1 text-[11px]">
                Créez d’abord le direct. Vous pourrez ensuite le garder standard ou activer un financement sponsorisé.
            </p>

            <label class="text-wpx-muted-dark mt-4 block text-[11px]">Titre</label>
            <input
                v-model="form.title"
                maxlength="140"
                placeholder="Ex. Présentation de notre nouveau service"
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

        <section v-if="selected" class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-3xl border">
            <div class="p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
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
                            <span
                                v-if="selected.type === 'sponsored'"
                                class="bg-wpx-gold/10 text-wpx-gold rounded-full px-2.5 py-1 text-[10px] font-bold"
                            >
                                Sponsorisé
                            </span>
                        </div>
                        <h2 class="text-wpx-white-soft mt-3 text-xl font-extrabold">{{ selected.title }}</h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">Publié par {{ selected.owner.display_name }}</p>
                    </div>
                    <button type="button" class="text-wpx-muted-dark text-xs font-semibold" @click="closeSelected">
                        Fermer
                    </button>
                </div>

                <LiveSponsorshipPanel
                    v-if="selected.status === 'draft' || selected.status === 'scheduled'"
                    :live="selected"
                    class="mt-4"
                    @updated="sponsorshipUpdated"
                />

                <LiveRealtimeRoom
                    v-if="selected.status === 'live'"
                    :live-id="selected.id"
                    mode="host"
                    :viewer-count="selected.viewer_count"
                    class="mt-4"
                />

                <div
                    v-else-if="selected.status === 'paused'"
                    class="bg-wpx-navy-950 mt-4 rounded-3xl px-5 py-12 text-center"
                >
                    <p class="text-wpx-white-soft text-sm font-bold">⏸ Live en pause</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                        La connexion média reprendra quand vous relancerez le direct.
                    </p>
                </div>

                <div v-else-if="selected.status === 'draft' || selected.status === 'scheduled'" class="mt-4">
                    <div
                        class="bg-wpx-navy-950 relative aspect-[9/16] max-h-[560px] overflow-hidden rounded-3xl sm:aspect-video"
                    >
                        <video ref="previewVideo" autoplay muted playsinline class="h-full w-full object-cover"></video>
                        <div
                            v-if="!previewActive"
                            class="absolute inset-0 flex flex-col items-center justify-center px-6 text-center"
                        >
                            <p class="text-wpx-white-soft text-sm font-bold">Préparez votre caméra avant le direct</p>
                            <button
                                type="button"
                                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 rounded-full bg-gradient-to-r px-4 py-2.5 text-xs font-black"
                                @click="startPreview"
                            >
                                📹 Tester caméra + micro
                            </button>
                        </div>
                    </div>
                    <button
                        v-if="previewActive"
                        type="button"
                        class="text-wpx-blue mt-2 text-xs font-bold"
                        @click="startPreview"
                    >
                        Changer / relancer l’aperçu
                    </button>
                </div>

                <div v-else class="bg-wpx-navy-950 mt-4 rounded-3xl px-5 py-10 text-center">
                    <p class="text-wpx-white-soft text-sm font-bold">Live terminé</p>
                </div>

                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Spectateurs actifs</p>
                        <p class="text-wpx-white-soft mt-1 text-xl font-extrabold">{{ selected.viewer_count }}</p>
                    </div>
                    <div class="bg-wpx-navy-950 rounded-2xl p-3">
                        <p class="text-wpx-muted-dark text-[10px] uppercase">Transport</p>
                        <p class="text-wpx-white-soft mt-1 text-xs font-extrabold">
                            {{
                                selected.stream.provider === 'livekit'
                                    ? 'LiveKit · WebRTC'
                                    : formatDate(selected.scheduled_at)
                            }}
                        </p>
                    </div>
                </div>

                <p v-if="selected.description" class="text-wpx-muted-dark mt-4 text-xs leading-relaxed">
                    {{ selected.description }}
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-if="selected.status === 'draft' || selected.status === 'scheduled'"
                        type="button"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 flex-1 rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-50"
                        :disabled="busy"
                        @click="creatorAction(selected, 'start')"
                    >
                        🔴 Lancer le Live
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
            </div>
        </section>

        <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-wpx-white-soft text-base font-extrabold">Historique Live</h2>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">
                        Uniquement les Lives de l’espace annonceur actif.
                    </p>
                </div>
                <button type="button" class="text-wpx-blue text-xs font-semibold" @click="load">Actualiser</button>
            </div>

            <div v-if="loading" class="text-wpx-muted-dark py-8 text-center text-xs">Chargement…</div>
            <div v-else-if="myLives.length" class="mt-3 space-y-2">
                <button
                    v-for="live in myLives"
                    :key="live.id"
                    type="button"
                    class="border-wpx-border-dark bg-wpx-navy-950 flex w-full items-center justify-between gap-3 rounded-2xl border p-4 text-left"
                    @click="selectLive(live)"
                >
                    <span>
                        <span class="text-wpx-white-soft block text-sm font-bold">{{ live.title }}</span>
                        <span class="text-wpx-muted-dark mt-1 block text-[11px]">
                            {{ statusLabel(live.status) }} ·
                            {{ live.type === 'sponsored' ? 'sponsorisé' : 'standard' }} ·
                            {{ live.scheduled_at ? formatDate(live.scheduled_at) : 'sans programmation' }}
                        </span>
                    </span>
                    <span class="text-wpx-gold text-lg">›</span>
                </button>
            </div>
            <p v-else class="text-wpx-muted-dark mt-3 text-xs">Aucun Live créé pour cette activité.</p>
        </section>
    </div>
</template>
