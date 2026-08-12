<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import http from '@/lib/http';
import { useComingSoon } from '@/lib/comingSoon';

interface Interactions {
    likes: number;
    saves: number;
    shares: number;
    comments: number;
    liked_by_me: boolean;
    saved_by_me: boolean;
}

interface Creative {
    url: string;
    type: string;
    duration: number | null;
    duration_ms?: number | null;
}

interface Delivery {
    id: string;
    campaign_id: string;
    status: 'reserved' | 'started' | 'completed' | 'abandoned' | 'expired' | 'held' | 'rejected' | 'replay';
    gain_minor: number;
    required_duration_ms: number;
    quota_units?: number;
    requires_media_end?: boolean;
    media_ended_at?: string | null;
    visible_duration_ms: number;
    progress_percent: number;
    brand_name: string | null;
    objective_code: string | null;
    cta_label: string | null;
    creative: Creative | null;
    interactions: Interactions;
}

interface Comment {
    id: string;
    body: string;
    created_at: string;
}

type PlaybackIndicator = 'play' | 'pause' | null;

const SOUND_PREFERENCE_KEY = 'wpx-feed-sound';
const HEARTBEAT_INTERVAL_MS = 400;

const emit = defineEmits<{ balanceChanged: [balance: number] }>();

const loading = ref(true);
const noAdAvailable = ref(false);
const feedSessionId = ref<string | null>(null);
const delivery = ref<Delivery | null>(null);
const prefetchedDelivery = ref<Delivery | null | undefined>(undefined);
const explanation = ref<string[] | null>(null);
const showComments = ref(false);
const comments = ref<Comment[]>([]);
const newComment = ref('');
const gainToast = ref<number | null>(null);
const holdNotice = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);
const balance = ref<number | null>(null);
const buffering = ref(false);
const playerError = ref<string | null>(null);
const videoPaused = ref(false);
const isMuted = ref(true);
const soundPreferred = ref(false);
const playbackIndicator = ref<PlaybackIndicator>(null);
const { notice: feedNavNotice, announce: announceFeedNav } = useComingSoon();

let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
let heartbeatInFlight = false;
let accumulatedVisibleMs = 0;
let activeSegmentStartedAt: number | null = null;
let lastHeartbeatVisibleMs = 0;
let startingDelivery = false;
let completingDelivery = false;
let touchStartY = 0;
let lastSwipeAt = 0;
let scrollGestureLocked = false;
let playbackIndicatorTimer: ReturnType<typeof setTimeout> | null = null;
let transitionTimer: ReturnType<typeof setTimeout> | null = null;
let prefetchPromise: Promise<void> | null = null;
let prefetchedMedia: HTMLVideoElement | HTMLImageElement | null = null;
let destroyed = false;

async function loadBalance(): Promise<void> {
    const { data } = await http.get('/me/wallet');
    balance.value = data.balance_minor;
}

function loadSoundPreference(): void {
    soundPreferred.value = window.localStorage.getItem(SOUND_PREFERENCE_KEY) === 'on';
    isMuted.value = !soundPreferred.value;
}

function persistSoundPreference(enabled: boolean): void {
    soundPreferred.value = enabled;
    window.localStorage.setItem(SOUND_PREFERENCE_KEY, enabled ? 'on' : 'off');
}

function stopHeartbeat(): void {
    if (heartbeatTimer !== null) {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
    }
}

function startHeartbeat(): void {
    if (heartbeatTimer !== null || delivery.value?.status !== 'started') return;
    heartbeatTimer = setInterval(() => void sendHeartbeat(), HEARTBEAT_INTERVAL_MS);
}

function currentVisibleDurationMs(): number {
    if (activeSegmentStartedAt === null) return accumulatedVisibleMs;
    return accumulatedVisibleMs + Math.max(0, Date.now() - activeSegmentStartedAt);
}

function pauseAttentionClock(): void {
    if (activeSegmentStartedAt !== null) {
        accumulatedVisibleMs += Math.max(0, Date.now() - activeSegmentStartedAt);
        activeSegmentStartedAt = null;
    }
    stopHeartbeat();
}

function resumeAttentionClock(): void {
    if (delivery.value?.status !== 'started' || document.visibilityState !== 'visible') return;
    if (activeSegmentStartedAt === null) activeSegmentStartedAt = Date.now();
    startHeartbeat();
}

function resetAttentionClock(visibleDurationMs = 0): void {
    stopHeartbeat();
    accumulatedVisibleMs = Math.max(0, visibleDurationMs);
    activeSegmentStartedAt = null;
    lastHeartbeatVisibleMs = accumulatedVisibleMs;
    heartbeatInFlight = false;
}

function resetPlayerState(): void {
    resetAttentionClock();
    buffering.value = false;
    playerError.value = null;
    videoPaused.value = false;
    startingDelivery = false;
    completingDelivery = false;
    playbackIndicator.value = null;
    if (playbackIndicatorTimer !== null) {
        clearTimeout(playbackIndicatorTimer);
        playbackIndicatorTimer = null;
    }
}

async function startSession(): Promise<void> {
    const { data } = await http.post('/feed/sessions');
    feedSessionId.value = data.feed_session.id;
}

async function fetchNextDelivery(): Promise<Delivery | null> {
    if (feedSessionId.value === null) await startSession();
    const { data } = await http.get('/feed/next', { params: { feed_session_id: feedSessionId.value } });
    return data.delivery;
}

function releasePrefetchedMedia(): void {
    if (prefetchedMedia instanceof HTMLVideoElement) {
        prefetchedMedia.removeAttribute('src');
        prefetchedMedia.load();
    }
    prefetchedMedia = null;
}

function preloadCreative(next: Delivery | null): void {
    releasePrefetchedMedia();
    if (next?.creative?.url === undefined) return;

    if (next.creative.type === 'video') {
        const media = document.createElement('video');
        media.preload = 'auto';
        media.muted = true;
        media.playsInline = true;
        media.src = next.creative.url;
        media.load();
        prefetchedMedia = media;
        return;
    }

    if (next.creative.type === 'image') {
        const image = new Image();
        image.src = next.creative.url;
        prefetchedMedia = image;
    }
}

async function prefetchNext(): Promise<void> {
    if (prefetchPromise !== null || prefetchedDelivery.value !== undefined || destroyed) return;

    prefetchPromise = (async () => {
        const next = await fetchNextDelivery();
        if (destroyed) {
            if (next !== null && ['reserved', 'started'].includes(next.status)) {
                void http.post(`/feed/deliveries/${next.id}/abandon`).catch(() => undefined);
            }
            return;
        }
        prefetchedDelivery.value = next;
        preloadCreative(next);
    })()
        .catch(() => {
            prefetchedDelivery.value = undefined;
        })
        .finally(() => {
            prefetchPromise = null;
        });

    await prefetchPromise;
}

async function activateDelivery(next: Delivery | null): Promise<void> {
    videoRef.value?.pause();
    resetPlayerState();
    delivery.value = next;
    noAdAvailable.value = next === null;
    loading.value = false;

    if (next === null) return;

    await nextTick();

    if (next.creative?.type === 'video') {
        void attemptAutoplay();
        return;
    }

    if (next.status === 'reserved') {
        await ensureDeliveryStarted();
        if (delivery.value?.status === 'started') resumeAttentionClock();
    }
}

async function loadNext(): Promise<void> {
    pauseAttentionClock();
    loading.value = true;
    explanation.value = null;
    playerError.value = null;

    try {
        const next = await fetchNextDelivery();
        await activateDelivery(next);
    } catch {
        loading.value = false;
        playerError.value = 'Connexion interrompue. Touchez pour réessayer.';
    }
}

async function activatePrefetchedOrLoadNext(): Promise<void> {
    explanation.value = null;
    if (prefetchPromise !== null) await prefetchPromise;

    if (prefetchedDelivery.value !== undefined) {
        const next = prefetchedDelivery.value;
        prefetchedDelivery.value = undefined;
        releasePrefetchedMedia();
        await activateDelivery(next);
        return;
    }

    await loadNext();
}

async function ensureDeliveryStarted(): Promise<void> {
    const current = delivery.value;
    if (current === null || current.status !== 'reserved' || startingDelivery) return;

    startingDelivery = true;
    try {
        const { data } = await http.post(`/feed/deliveries/${current.id}/start`);
        if (delivery.value?.id !== current.id) return;
        delivery.value = { ...delivery.value, ...data.delivery };
        resetAttentionClock(data.delivery.visible_duration_ms ?? 0);
    } catch {
        playerError.value = 'Impossible de démarrer la lecture. Vérifiez votre connexion puis réessayez.';
        videoRef.value?.pause();
    } finally {
        startingDelivery = false;
    }
}

async function attemptAutoplay(): Promise<void> {
    const video = videoRef.value;
    if (video === null || playerError.value !== null) return;

    isMuted.value = !soundPreferred.value;
    video.muted = isMuted.value;
    buffering.value = true;

    try {
        await video.play();
    } catch {
        if (!video.muted) {
            video.muted = true;
            isMuted.value = true;
            try {
                await video.play();
            } catch {
                buffering.value = false;
                videoPaused.value = true;
            }
        } else {
            buffering.value = false;
            videoPaused.value = true;
        }
    }
}

async function onVideoPlaying(): Promise<void> {
    buffering.value = false;
    playerError.value = null;
    videoPaused.value = false;

    if (delivery.value?.status === 'reserved') await ensureDeliveryStarted();

    if (delivery.value?.status === 'started' && videoRef.value !== null && !videoRef.value.paused) {
        resumeAttentionClock();
    }
}

function onVideoPause(): void {
    videoPaused.value = true;
    pauseAttentionClock();
}

function onVideoWaiting(): void {
    buffering.value = true;
    pauseAttentionClock();
}

function onVideoCanPlay(): void {
    buffering.value = false;
}

function onVideoError(): void {
    buffering.value = false;
    pauseAttentionClock();
    playerError.value = 'La vidéo ne peut pas être chargée pour le moment.';
}

function showPlaybackState(state: Exclude<PlaybackIndicator, null>): void {
    playbackIndicator.value = state;
    if (playbackIndicatorTimer !== null) clearTimeout(playbackIndicatorTimer);
    playbackIndicatorTimer = setTimeout(() => {
        playbackIndicator.value = null;
        playbackIndicatorTimer = null;
    }, 650);
}

async function togglePlayback(): Promise<void> {
    if (Date.now() - lastSwipeAt < 400 || playerError.value !== null) return;
    const video = videoRef.value;
    if (video === null) return;

    if (video.paused) {
        try {
            await video.play();
            showPlaybackState('pause');
        } catch {
            playerError.value = 'Impossible de reprendre la vidéo. Réessayez.';
        }
        return;
    }

    video.pause();
    showPlaybackState('play');
}

async function toggleSound(): Promise<void> {
    const video = videoRef.value;
    const enableSound = isMuted.value;
    isMuted.value = !enableSound;
    persistSoundPreference(enableSound);

    if (video !== null) {
        video.muted = isMuted.value;
        if (enableSound && video.paused) {
            try {
                await video.play();
            } catch {
                isMuted.value = true;
            }
        }
    }
}

async function retryPlayback(): Promise<void> {
    playerError.value = null;
    buffering.value = true;

    const current = delivery.value;
    if (current === null) {
        await loadNext();
        return;
    }

    if (current.creative?.type !== 'video') {
        if (current.status === 'reserved') await ensureDeliveryStarted();
        if (delivery.value?.status === 'started') resumeAttentionClock();
        return;
    }

    const video = videoRef.value;
    if (video === null) return;
    if (video.ended && current.status === 'started') {
        await onVideoEnded();
        return;
    }
    if (video.error !== null) video.load();
    await attemptAutoplay();
}

async function sendHeartbeat(): Promise<void> {
    const current = delivery.value;
    if (current === null || current.status !== 'started' || heartbeatInFlight) return;

    const visibleMs = currentVisibleDurationMs();
    if (visibleMs <= lastHeartbeatVisibleMs) return;

    heartbeatInFlight = true;
    try {
        const { data } = await http.post(`/feed/deliveries/${current.id}/heartbeat`, {
            visible_duration_ms: visibleMs,
        });
        if (delivery.value?.id !== current.id) return;

        lastHeartbeatVisibleMs = Math.max(lastHeartbeatVisibleMs, data.delivery.visible_duration_ms ?? visibleMs);
        const updated = { ...delivery.value, ...data.delivery };
        delivery.value = updated;

        if (updated.progress_percent >= 100 && !updated.requires_media_end) {
            pauseAttentionClock();
            await completeDelivery();
        }
    } catch {
        pauseAttentionClock();
        videoRef.value?.pause();
        playerError.value = 'Connexion interrompue. La progression est en pause. Réessayez.';
    } finally {
        heartbeatInFlight = false;
    }
}

async function onVideoEnded(): Promise<void> {
    pauseAttentionClock();

    const current = delivery.value;
    if (current === null) return;

    if (current.status === 'replay') {
        void prefetchNext();
        scheduleNext(650);
        return;
    }
    if (current.status !== 'started') return;

    try {
        const { data } = await http.post(`/feed/deliveries/${current.id}/ended`, {
            visible_duration_ms: currentVisibleDurationMs(),
        });
        if (delivery.value?.id !== current.id) return;
        delivery.value = { ...delivery.value, ...data.delivery };
        await completeDelivery();
    } catch {
        playerError.value = 'La fin réelle de la vidéo n’a pas pu être validée. Réessayez.';
    }
}

function scheduleNext(delayMs: number): void {
    if (transitionTimer !== null) clearTimeout(transitionTimer);
    transitionTimer = setTimeout(() => {
        transitionTimer = null;
        gainToast.value = null;
        holdNotice.value = false;
        void activatePrefetchedOrLoadNext();
    }, delayMs);
}

async function completeDelivery(): Promise<void> {
    const current = delivery.value;
    if (current === null || completingDelivery) return;

    completingDelivery = true;
    videoRef.value?.pause();

    try {
        const { data } = await http.post(`/feed/deliveries/${current.id}/complete`);
        if (delivery.value?.id !== current.id) return;

        const updated = { ...delivery.value, ...data.delivery };
        delivery.value = updated;
        void prefetchNext();

        if (updated.status === 'held') {
            holdNotice.value = true;
            scheduleNext(1600);
            return;
        }

        balance.value = data.balance_minor;
        emit('balanceChanged', data.balance_minor);

        if (data.gain_minor > 0) {
            gainToast.value = data.gain_minor;
            scheduleNext(1600);
        } else {
            scheduleNext(650);
        }
    } catch {
        playerError.value = 'La validation de cette vue a échoué. Réessayez.';
    } finally {
        completingDelivery = false;
    }
}

async function skip(): Promise<void> {
    pauseAttentionClock();
    videoRef.value?.pause();

    if (
        delivery.value !== null &&
        !['completed', 'held', 'rejected', 'expired', 'abandoned'].includes(delivery.value.status)
    ) {
        await http.post(`/feed/deliveries/${delivery.value.id}/abandon`);
    }

    await loadNext();
}

function triggerScrollGesture(): void {
    if (scrollGestureLocked || showComments.value || loading.value || delivery.value === null) return;

    lastSwipeAt = Date.now();
    scrollGestureLocked = true;
    skip().finally(() => {
        scrollGestureLocked = false;
    });
}

function onTouchStart(event: TouchEvent): void {
    touchStartY = event.touches[0]?.clientY ?? 0;
}

function onTouchEnd(event: TouchEvent): void {
    const endY = event.changedTouches[0]?.clientY ?? touchStartY;
    if (touchStartY - endY > 60) triggerScrollGesture();
}

function onWheel(event: WheelEvent): void {
    if (event.deltaY > 40) triggerScrollGesture();
}

function onVisibilityChange(): void {
    if (document.visibilityState !== 'visible') {
        pauseAttentionClock();
        return;
    }

    if (delivery.value?.status !== 'started') return;

    if (delivery.value.creative?.type === 'video') {
        const video = videoRef.value;
        if (video !== null && !video.paused && !buffering.value && playerError.value === null) resumeAttentionClock();
        return;
    }

    resumeAttentionClock();
}

async function toggleLike(): Promise<void> {
    if (delivery.value === null) return;
    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/like`);
    delivery.value.interactions.liked_by_me = data.active;
    delivery.value.interactions.likes = data.count;
}

async function toggleSave(): Promise<void> {
    if (delivery.value === null) return;
    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/save`);
    delivery.value.interactions.saved_by_me = data.active;
    delivery.value.interactions.saves = data.count;
}

async function share(): Promise<void> {
    if (delivery.value === null) return;
    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/share`);
    delivery.value.interactions.shares = data.count;
}

async function openComments(): Promise<void> {
    if (delivery.value === null) return;
    showComments.value = true;
    const { data } = await http.get(`/feed/campaigns/${delivery.value.campaign_id}/comments`);
    comments.value = data.comments;
}

async function postComment(): Promise<void> {
    if (delivery.value === null || newComment.value.trim() === '') return;
    await http.post(`/feed/campaigns/${delivery.value.campaign_id}/comments`, { body: newComment.value });
    newComment.value = '';
    delivery.value.interactions.comments += 1;
    await openComments();
}

async function toggleWhy(): Promise<void> {
    if (delivery.value === null) return;
    if (explanation.value !== null) {
        explanation.value = null;
        return;
    }
    const { data } = await http.get(`/feed/deliveries/${delivery.value.id}/why`);
    explanation.value = data.explanation;
}

const progressWidth = computed(() => `${delivery.value?.progress_percent ?? 0}%`);
const gainLabel = computed(() => (delivery.value ? `+${delivery.value.gain_minor} WP` : ''));
function formatDuration(ms: number): string {
    const totalSeconds = Math.max(1, Math.ceil(ms / 1000));
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return minutes > 0 ? `${minutes} min ${seconds.toString().padStart(2, '0')} s` : `${seconds} s`;
}

const durationLabel = computed(() => (delivery.value ? formatDuration(delivery.value.required_duration_ms) : ''));

onMounted(() => {
    loadSoundPreference();
    document.addEventListener('visibilitychange', onVisibilityChange);
    void loadNext();
    void loadBalance();
});

onBeforeUnmount(() => {
    destroyed = true;
    pauseAttentionClock();
    document.removeEventListener('visibilitychange', onVisibilityChange);
    if (playbackIndicatorTimer !== null) clearTimeout(playbackIndicatorTimer);
    if (transitionTimer !== null) clearTimeout(transitionTimer);

    const pending = prefetchedDelivery.value;
    if (pending !== undefined && pending !== null && ['reserved', 'started'].includes(pending.status)) {
        void http.post(`/feed/deliveries/${pending.id}/abandon`).catch(() => undefined);
    }
    releasePrefetchedMedia();
});
</script>

<template>
    <div
        class="rounded-wpx-lg shadow-wpx-card-dark relative aspect-[9/16] w-full overflow-hidden bg-black"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
        @wheel.passive="onWheel"
    >
        <div
            class="absolute inset-x-0 top-0 z-20 bg-gradient-to-b from-black/80 via-black/45 to-transparent px-3.5 pt-3 pb-7"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <img
                        src="/brand/wasplex-logo-transparent.png"
                        alt="Wasplex"
                        class="wpx-motion-safe h-6.5 w-6.5 animate-[wpxPulseLogo_2.4s_ease-in-out_infinite] object-contain"
                    />
                    <span class="text-sm font-black tracking-tight text-white">Wasplex</span>
                </div>
                <span
                    class="border-wpx-gold/40 flex items-center gap-1.5 rounded-full border bg-black/55 px-2.5 py-1.5"
                >
                    <span class="bg-wpx-gold h-1.5 w-1.5 rounded-full" />
                    <span class="text-wpx-gold text-xs font-bold">{{ balance ?? '…' }} WP</span>
                </span>
            </div>

            <nav class="mt-2.5 flex items-center justify-center gap-4 text-[12px]" aria-label="Navigation du Feed">
                <span aria-current="page" class="border-wpx-blue border-b-2 pb-1 font-bold text-white">Pour toi</span>
                <button type="button" class="pb-1 font-semibold text-white/70" @click.stop="announceFeedNav">
                    Explorer
                </button>
                <button type="button" class="pb-1 font-semibold text-white/70" @click.stop="announceFeedNav">
                    Alertes
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1 pb-1 font-semibold text-white/70"
                    @click.stop="announceFeedNav"
                >
                    <span class="bg-wpx-danger h-1.5 w-1.5 rounded-full" aria-hidden="true" />
                    Live
                </button>
            </nav>

            <div v-if="feedNavNotice" class="mt-1.5 text-center text-[10px] font-medium text-white/70">
                {{ feedNavNotice }}
            </div>

            <div class="mt-2.5 h-0.5 overflow-hidden rounded-full bg-white/15">
                <div
                    class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                    :style="{ width: progressWidth }"
                />
            </div>
        </div>

        <video
            v-if="delivery?.creative?.type === 'video'"
            ref="videoRef"
            :key="delivery.id"
            :src="delivery.creative.url"
            class="absolute inset-0 h-full w-full cursor-pointer object-cover"
            :muted="isMuted"
            playsinline
            preload="auto"
            @click.stop="togglePlayback"
            @playing="onVideoPlaying"
            @pause="onVideoPause"
            @waiting="onVideoWaiting"
            @stalled="onVideoWaiting"
            @canplay="onVideoCanPlay"
            @ended="onVideoEnded"
            @error="onVideoError"
        />
        <img
            v-else-if="delivery?.creative?.type === 'image'"
            :src="delivery.creative.url"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
        />
        <div
            v-else
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 absolute inset-0 flex items-center justify-center bg-gradient-to-br"
        >
            <p v-if="loading" class="text-wpx-muted-dark text-xs">Chargement…</p>
            <div v-else-if="noAdAvailable" class="max-w-[14rem] px-4 text-center">
                <p class="text-wpx-white-soft text-sm font-semibold">Aucune publicité pour le moment</p>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Complétez votre profil intelligent et vos consentements dans Mon Espace pour en recevoir, ou revenez
                    plus tard.
                </p>
            </div>
            <p v-else class="px-6 text-center text-xs text-white/30">Aperçu du média — pas la publicité réelle</p>
        </div>

        <div
            v-if="buffering && !playerError && delivery?.creative?.type === 'video'"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center"
        >
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-black/55">
                <span class="h-6 w-6 animate-spin rounded-full border-2 border-white/25 border-t-white" />
            </div>
        </div>

        <div
            v-if="playbackIndicator"
            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center"
        >
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-black/55 text-2xl text-white">
                {{ playbackIndicator === 'play' ? '▶' : '❚❚' }}
            </div>
        </div>

        <div v-if="playerError" class="absolute inset-0 z-30 flex items-center justify-center bg-black/65 px-6">
            <div class="max-w-xs text-center">
                <p class="text-sm font-semibold text-white">{{ playerError }}</p>
                <button
                    type="button"
                    class="bg-wpx-gold text-wpx-navy-950 mt-3 rounded-full px-5 py-2 text-xs font-black"
                    @click.stop="retryPlayback"
                >
                    Réessayer
                </button>
            </div>
        </div>

        <template v-if="delivery && !noAdAvailable">
            <div
                v-if="delivery.status === 'reserved' || delivery.status === 'started'"
                class="absolute inset-x-4 top-16 z-20 flex items-center justify-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-center"
            >
                <span class="text-wpx-gold text-xs font-bold">{{ gainLabel }}</span>
                <span class="text-[11px] text-white/60">· {{ durationLabel }}</span>
            </div>

            <div class="absolute right-2 bottom-28 z-20 flex flex-col items-center gap-3.5">
                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    :aria-label="delivery.interactions.liked_by_me ? 'Retirer des favoris' : 'Ajouter aux favoris'"
                    :aria-pressed="delivery.interactions.liked_by_me"
                    @click="toggleLike"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full"
                        :class="
                            delivery.interactions.liked_by_me ? 'bg-wpx-danger text-white' : 'bg-black/40 text-white/90'
                        "
                    >
                        <svg
                            width="21"
                            height="21"
                            viewBox="0 0 24 24"
                            :fill="delivery.interactions.liked_by_me ? 'currentColor' : 'none'"
                        >
                            <path
                                d="M20.8 4.6a5.5 5.5 0 00-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 00-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 00-.1-7.8z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.likes }}</span>
                </button>

                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    aria-label="Voir les commentaires"
                    @click="openComments"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white/90"
                    >
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M21 11.5a8.4 8.4 0 01-9 8.5 9.3 9.3 0 01-3.8-.8L3 21l1.7-4.7A8.2 8.2 0 013 11.5 8.5 8.5 0 0112 3a8.5 8.5 0 019 8.5z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.comments }}</span>
                </button>

                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    :aria-label="delivery.interactions.saved_by_me ? 'Retirer des enregistrements' : 'Enregistrer'"
                    :aria-pressed="delivery.interactions.saved_by_me"
                    @click="toggleSave"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full"
                        :class="
                            delivery.interactions.saved_by_me
                                ? 'bg-wpx-gold text-wpx-navy-950'
                                : 'bg-black/40 text-white/90'
                        "
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            :fill="delivery.interactions.saved_by_me ? 'currentColor' : 'none'"
                        >
                            <path
                                d="M6 4.8A1.8 1.8 0 017.8 3h8.4A1.8 1.8 0 0118 4.8V21l-6-3.8L6 21V4.8z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.saves }}</span>
                </button>

                <button type="button" class="flex flex-col items-center gap-0.5" aria-label="Partager" @click="share">
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white/90"
                    >
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 16V4m0 0L7.5 8.5M12 4l4.5 4.5"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M5 12.5v5A2.5 2.5 0 007.5 20h9a2.5 2.5 0 002.5-2.5v-5"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.shares }}</span>
                </button>

                <button
                    v-if="delivery.creative?.type === 'video'"
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    :aria-label="isMuted ? 'Activer le son' : 'Couper le son'"
                    :aria-pressed="!isMuted"
                    @click.stop="toggleSound"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white/90"
                    >
                        <svg width="21" height="21" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M4 10v4h4l5 4V6L8 10H4z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                            <template v-if="isMuted">
                                <path
                                    d="M17 10l4 4m0-4l-4 4"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                            </template>
                            <template v-else>
                                <path
                                    d="M16 9a4 4 0 010 6M18.5 6.5a7.5 7.5 0 010 11"
                                    stroke="currentColor"
                                    stroke-width="1.7"
                                    stroke-linecap="round"
                                />
                            </template>
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">Son</span>
                </button>
            </div>

            <div class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/85 to-transparent p-3 pr-16">
                <div class="flex items-center gap-2.5">
                    <span class="from-wpx-blue to-wpx-cyan rounded-wpx-sm h-8.5 w-8.5 shrink-0 bg-gradient-to-br" />
                    <p class="text-sm font-semibold text-white">{{ delivery.brand_name ?? 'Annonceur' }}</p>
                </div>
                <div class="mt-1.5 flex items-center gap-2">
                    <button
                        v-if="delivery.cta_label"
                        type="button"
                        class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 px-3 py-1 text-xs font-semibold"
                    >
                        {{ delivery.cta_label }}
                    </button>
                    <button type="button" class="text-[11px] text-white/70 underline" @click="toggleWhy">
                        Pourquoi cette publicité ?
                    </button>
                    <button
                        v-if="!['completed', 'held'].includes(delivery.status)"
                        type="button"
                        class="ml-auto text-[11px] text-white/50"
                        @click="skip"
                    >
                        Passer ▸
                    </button>
                </div>
                <ul v-if="explanation" class="text-wpx-muted-dark mt-2 list-disc pl-4 text-[11px]">
                    <li v-for="(reason, index) in explanation" :key="index">{{ reason }}</li>
                </ul>
            </div>
        </template>

        <div v-if="gainToast !== null" class="absolute inset-0 z-30 flex items-center justify-center bg-black/30">
            <div
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 animate-bounce rounded-full bg-gradient-to-br px-6 py-3 text-lg font-bold shadow-xl"
            >
                +{{ gainToast }} WP
            </div>
        </div>

        <div v-if="holdNotice" class="absolute inset-0 z-30 flex items-center justify-center bg-black/30">
            <div class="bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md px-5 py-3 text-center text-sm shadow-xl">
                Vérification en cours<br /><span class="text-wpx-muted-dark text-xs"
                    >Le gain sera confirmé après contrôle.</span
                >
            </div>
        </div>

        <div
            v-if="showComments"
            class="absolute inset-0 z-40 flex flex-col justify-end bg-black/60"
            @click.self="showComments = false"
        >
            <div class="bg-wpx-navy-850 rounded-t-wpx-lg max-h-[70%] p-4">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-wpx-white-soft text-sm font-semibold">Commentaires</p>
                    <button type="button" class="text-wpx-muted-dark text-xs" @click="showComments = false">
                        Fermer
                    </button>
                </div>
                <div class="mb-3 flex max-h-48 flex-col gap-2 overflow-y-auto">
                    <p v-for="comment in comments" :key="comment.id" class="text-wpx-muted-dark text-xs">
                        {{ comment.body }}
                    </p>
                    <p v-if="comments.length === 0" class="text-wpx-muted-dark text-xs italic">
                        Aucun commentaire pour le moment.
                    </p>
                </div>
                <form class="flex gap-2" @submit.prevent="postComment">
                    <input
                        v-model="newComment"
                        type="text"
                        maxlength="500"
                        placeholder="Ajouter un commentaire…"
                        class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft rounded-wpx-sm flex-1 border px-2 py-1.5 text-xs"
                    />
                    <button type="submit" class="text-wpx-gold text-xs font-semibold">Envoyer</button>
                </form>
            </div>
        </div>
    </div>
</template>
