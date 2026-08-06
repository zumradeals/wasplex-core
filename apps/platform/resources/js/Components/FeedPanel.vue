<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import http from '@/lib/http';

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
}

interface Delivery {
    id: string;
    campaign_id: string;
    status: 'reserved' | 'started' | 'completed' | 'abandoned' | 'expired' | 'held' | 'rejected';
    gain_minor: number;
    required_duration_ms: number;
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

const emit = defineEmits<{ balanceChanged: [balance: number] }>();

const loading = ref(true);
const noAdAvailable = ref(false);
const feedSessionId = ref<string | null>(null);
const delivery = ref<Delivery | null>(null);
const explanation = ref<string[] | null>(null);
const showComments = ref(false);
const comments = ref<Comment[]>([]);
const newComment = ref('');
const gainToast = ref<number | null>(null);
const holdNotice = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);

let heartbeatTimer: ReturnType<typeof setInterval> | null = null;
let clientStartedAt = 0;
let touchStartY = 0;
let scrollGestureLocked = false;

function stopHeartbeat(): void {
    if (heartbeatTimer !== null) {
        clearInterval(heartbeatTimer);
        heartbeatTimer = null;
    }
}

async function startSession(): Promise<void> {
    const { data } = await http.post('/feed/sessions');
    feedSessionId.value = data.feed_session.id;
}

async function loadNext(): Promise<void> {
    stopHeartbeat();
    loading.value = true;
    explanation.value = null;

    if (feedSessionId.value === null) {
        await startSession();
    }

    const { data } = await http.get('/feed/next', { params: { feed_session_id: feedSessionId.value } });
    delivery.value = data.delivery;
    noAdAvailable.value = data.delivery === null;
    loading.value = false;
}

async function beginPlayback(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/deliveries/${delivery.value.id}/start`);
    delivery.value = { ...delivery.value, ...data.delivery };
    clientStartedAt = Date.now();

    heartbeatTimer = setInterval(sendHeartbeat, 400);
    void videoRef.value?.play();
}

async function sendHeartbeat(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const visibleMs = Date.now() - clientStartedAt;
    const { data } = await http.post(`/feed/deliveries/${delivery.value.id}/heartbeat`, {
        visible_duration_ms: visibleMs,
    });
    const updated = { ...delivery.value, ...data.delivery };
    delivery.value = updated;

    if (updated.progress_percent >= 100) {
        stopHeartbeat();
        await completeDelivery();
    }
}

async function completeDelivery(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/deliveries/${delivery.value.id}/complete`);
    const updated = { ...delivery.value, ...data.delivery };
    delivery.value = updated;

    // Une livraison mise en attente (docs/chantiers/P010-CHANTIER.md §5)
    // n'a reçu aucun gain — jamais présentée comme un crédit qui n'a pas eu
    // lieu.
    if (updated.status === 'held') {
        holdNotice.value = true;
        setTimeout(() => {
            holdNotice.value = false;
            void loadNext();
        }, 1600);

        return;
    }

    gainToast.value = data.gain_minor;
    emit('balanceChanged', data.balance_minor);

    setTimeout(() => {
        gainToast.value = null;
        void loadNext();
    }, 1600);
}

async function skip(): Promise<void> {
    stopHeartbeat();

    if (delivery.value !== null && delivery.value.status !== 'completed') {
        await http.post(`/feed/deliveries/${delivery.value.id}/abandon`);
    }

    await loadNext();
}

/**
 * docs/08-feed-principal-wasplex.md §27/§83 : le défilement vertical
 * abandonne sans confirmation, exactement comme le bouton "Passer" — aucune
 * nouvelle règle serveur, juste un geste plus naturel pour déclencher le
 * même flux.
 */
function triggerScrollGesture(): void {
    if (scrollGestureLocked || showComments.value || loading.value || delivery.value === null) {
        return;
    }

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

    if (touchStartY - endY > 60) {
        triggerScrollGesture();
    }
}

function onWheel(event: WheelEvent): void {
    if (event.deltaY > 40) {
        triggerScrollGesture();
    }
}

async function toggleLike(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/like`);
    delivery.value.interactions.liked_by_me = data.active;
    delivery.value.interactions.likes = data.count;
}

async function toggleSave(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/save`);
    delivery.value.interactions.saved_by_me = data.active;
    delivery.value.interactions.saves = data.count;
}

async function share(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/campaigns/${delivery.value.campaign_id}/share`);
    delivery.value.interactions.shares = data.count;
}

async function openComments(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    showComments.value = true;
    const { data } = await http.get(`/feed/campaigns/${delivery.value.campaign_id}/comments`);
    comments.value = data.comments;
}

async function postComment(): Promise<void> {
    if (delivery.value === null || newComment.value.trim() === '') {
        return;
    }

    await http.post(`/feed/campaigns/${delivery.value.campaign_id}/comments`, { body: newComment.value });
    newComment.value = '';
    delivery.value.interactions.comments += 1;
    await openComments();
}

async function toggleWhy(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    if (explanation.value !== null) {
        explanation.value = null;

        return;
    }

    const { data } = await http.get(`/feed/deliveries/${delivery.value.id}/why`);
    explanation.value = data.explanation;
}

const progressWidth = computed(() => `${delivery.value?.progress_percent ?? 0}%`);
const gainLabel = computed(() => (delivery.value ? `+${delivery.value.gain_minor} WP` : ''));
const durationLabel = computed(() =>
    delivery.value ? `${Math.round(delivery.value.required_duration_ms / 1000)} s` : '',
);

onMounted(loadNext);
onBeforeUnmount(stopHeartbeat);
</script>

<template>
    <div
        class="rounded-wpx-lg shadow-wpx-card-dark relative aspect-[9/16] w-full overflow-hidden bg-black"
        @touchstart="onTouchStart"
        @touchend="onTouchEnd"
        @wheel.passive="onWheel"
    >
        <!-- Immersive top header, overlaid on the content itself. -->
        <div class="absolute inset-x-0 top-0 z-20 bg-gradient-to-b from-black/70 to-transparent px-3 pt-3 pb-8">
            <div class="flex items-center justify-between">
                <div class="rounded-wpx-sm bg-white/90 p-0.5">
                    <img src="/brand/wasplex-logo-full.png" alt="Wasplex" class="h-5 w-5 object-contain" />
                </div>
                <div class="flex items-center gap-3 text-[11px] font-semibold text-white/90">
                    <span class="text-wpx-gold border-wpx-gold border-b-2 pb-0.5">Pour toi</span>
                    <span class="text-white/40">Alertes</span>
                    <span class="text-white/40">Explorer</span>
                </div>
                <span class="rounded-full bg-black/50 px-2 py-1 text-[10px] font-semibold text-white/90">🕊️ WP</span>
            </div>
        </div>

        <!-- Content area: real creative when attached, decorative fallback otherwise. -->
        <video
            v-if="delivery?.creative?.type === 'video'"
            ref="videoRef"
            :key="delivery.id"
            :src="delivery.creative.url"
            class="absolute inset-0 h-full w-full object-cover"
            :muted="true"
            loop
            playsinline
            autoplay
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

        <template v-if="delivery && !noAdAvailable">
            <!-- Progress bar, real (server-clamped) attention. -->
            <div
                v-if="delivery.status === 'started'"
                class="absolute inset-x-3 top-14 z-20 h-1 rounded-full bg-white/20"
            >
                <div
                    class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                    :style="{ width: progressWidth }"
                />
            </div>

            <!-- Gain banner before playback. -->
            <div
                v-if="delivery.status === 'reserved'"
                class="rounded-wpx-lg absolute inset-x-4 top-1/3 z-20 bg-black/70 p-4 text-center"
            >
                <p class="text-wpx-white-soft text-sm font-semibold">Regardez jusqu'à la fin</p>
                <p class="mt-1 text-xs text-white/70">
                    Gain : <span class="text-wpx-gold font-bold">{{ gainLabel }}</span> · Durée : {{ durationLabel }}
                </p>
                <button
                    type="button"
                    class="rounded-wpx-md from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full bg-gradient-to-br py-2 text-sm font-semibold"
                    @click="beginPlayback"
                >
                    Démarrer
                </button>
            </div>

            <!-- Left rail — reserved for future Alertes (P015), inert. -->
            <div class="absolute bottom-28 left-2 z-20 flex flex-col items-center gap-3 opacity-40">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-white/40 bg-black/30 text-sm"
                >
                    🔔
                </div>
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-white/40 bg-black/30 text-sm"
                >
                    📍
                </div>
                <span class="text-center text-[9px] leading-tight text-white/50">Alertes<br />bientôt</span>
            </div>

            <!-- Right rail — real social actions. -->
            <div class="absolute right-2 bottom-28 z-20 flex flex-col items-center gap-4">
                <button type="button" class="flex flex-col items-center gap-0.5" @click="toggleLike">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                        :class="
                            delivery.interactions.liked_by_me ? 'bg-wpx-danger text-white' : 'bg-black/40 text-white/90'
                        "
                    >
                        ❤
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.likes }}</span>
                </button>
                <button type="button" class="flex flex-col items-center gap-0.5" @click="openComments">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-lg text-white/90"
                        >💬</span
                    >
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.comments }}</span>
                </button>
                <button type="button" class="flex flex-col items-center gap-0.5" @click="toggleSave">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                        :class="
                            delivery.interactions.saved_by_me
                                ? 'bg-wpx-gold text-wpx-navy-950'
                                : 'bg-black/40 text-white/90'
                        "
                    >
                        ⭐
                    </span>
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.saves }}</span>
                </button>
                <button type="button" class="flex flex-col items-center gap-0.5" @click="share">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-lg text-white/90"
                        >🔗</span
                    >
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.shares }}</span>
                </button>
            </div>

            <!-- Bottom brand / CTA row. -->
            <div class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/85 to-transparent p-3 pr-16">
                <p class="text-sm font-semibold text-white">{{ delivery.brand_name ?? 'Annonceur' }}</p>
                <div class="mt-1 flex items-center gap-2">
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
                        v-if="delivery.status !== 'completed'"
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

        <!-- Gain animation. -->
        <div v-if="gainToast !== null" class="absolute inset-0 z-30 flex items-center justify-center bg-black/30">
            <div
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 animate-bounce rounded-full bg-gradient-to-br px-6 py-3 text-lg font-bold shadow-xl"
            >
                +{{ gainToast }} WP
            </div>
        </div>

        <!-- Attention jugée douteuse : aucun gain décidé, en attente de vérification (docs/16 §20). -->
        <div v-if="holdNotice" class="absolute inset-0 z-30 flex items-center justify-center bg-black/30">
            <div class="bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md px-5 py-3 text-center text-sm shadow-xl">
                Vérification en cours<br /><span class="text-wpx-muted-dark text-xs"
                    >Le gain sera confirmé après contrôle.</span
                >
            </div>
        </div>

        <!-- Comments bottom sheet. -->
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
