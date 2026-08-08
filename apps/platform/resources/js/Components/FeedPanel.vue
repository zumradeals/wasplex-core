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
const balance = ref<number | null>(null);
const { notice: alertsNotice, announce: announceAlerts } = useComingSoon();

async function loadBalance(): Promise<void> {
    const { data } = await http.get('/me/wallet');
    balance.value = data.balance_minor;
}

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

    // Le gain est connu avant lecture (docs/07 §1429, docs/08 §1710) — rien
    // n'exige un geste supplémentaire pour le déclencher : la vidéo démarre
    // d'elle-même dès qu'elle est prête, le montant reste affiché pendant
    // la lecture.
    if (data.delivery !== null && data.delivery.status === 'reserved') {
        void beginPlayback();
    }
}

async function beginPlayback(): Promise<void> {
    if (delivery.value === null) {
        return;
    }

    const { data } = await http.post(`/feed/deliveries/${delivery.value.id}/start`);
    delivery.value = { ...delivery.value, ...data.delivery };
    clientStartedAt = Date.now();

    heartbeatTimer = setInterval(sendHeartbeat, 400);

    // videoRef ne pointe vers le nouvel élément qu'après le prochain rendu
    // Vue — beginPlayback() peut désormais être appelé automatiquement dès
    // loadNext(), dans le même tick que la mise à jour de `delivery`.
    await nextTick();
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
    balance.value = data.balance_minor;
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

onMounted(() => {
    void loadNext();
    void loadBalance();
});
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
        <div class="absolute inset-x-0 top-0 z-20 bg-gradient-to-b from-black/70 to-transparent px-3.5 pt-3 pb-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    <img
                        src="/brand/wasplex-logo-transparent.png"
                        alt="Wasplex"
                        class="wpx-motion-safe h-6.5 w-6.5 animate-[wpxPulseLogo_2.4s_ease-in-out_infinite] object-contain"
                    />
                    <div class="flex items-center gap-4 text-sm">
                        <span class="border-wpx-blue border-b-2 pb-0.5 font-bold text-white">Pour toi</span>
                        <span class="font-semibold text-white/70">Explorer</span>
                    </div>
                </div>
                <span
                    class="border-wpx-gold/40 flex items-center gap-1.5 rounded-full border bg-black/55 px-2.5 py-1.5"
                >
                    <span class="bg-wpx-gold h-1.5 w-1.5 rounded-full" />
                    <span class="text-wpx-gold text-xs font-bold">{{ balance ?? '…' }} WP</span>
                </span>
            </div>
            <div class="mt-3 h-0.5 overflow-hidden rounded-full bg-white/15">
                <div
                    class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                    :style="{ width: progressWidth }"
                />
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
            <!-- Gain connu avant/pendant la lecture — jamais un geste requis pour démarrer. -->
            <div
                v-if="delivery.status === 'reserved' || delivery.status === 'started'"
                class="absolute inset-x-4 top-16 z-20 flex items-center justify-center gap-2 rounded-full bg-black/60 px-3 py-1.5 text-center"
            >
                <span class="text-wpx-gold text-xs font-bold">{{ gainLabel }}</span>
                <span class="text-[11px] text-white/60">· {{ durationLabel }}</span>
            </div>

            <!-- Rail d'actions droit : Alertes (inerte, docs P015) + actions sociales réelles. -->
            <div class="absolute right-2 bottom-28 z-20 flex flex-col items-center gap-4">
                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    aria-label="Alertes"
                    @click="announceAlerts"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white/90"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 4a5 5 0 015 5v3l1.5 3h-13L7 12V9a5 5 0 015-5z"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linejoin="round"
                            />
                            <path d="M10 18a2 2 0 004 0" stroke="currentColor" stroke-width="1.7" />
                        </svg>
                    </span>
                    <span class="text-[10px] text-white/80">Alertes</span>
                </button>
                <button
                    type="button"
                    class="flex flex-col items-center gap-0.5"
                    :aria-label="delivery.interactions.liked_by_me ? 'Retirer le like' : 'Aimer'"
                    :aria-pressed="delivery.interactions.liked_by_me"
                    @click="toggleLike"
                >
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-lg"
                        :class="
                            delivery.interactions.liked_by_me ? 'bg-wpx-danger text-white' : 'bg-black/40 text-white/90'
                        "
                    >
                        ❤
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
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-lg text-white/90"
                        >💬</span
                    >
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
                <button type="button" class="flex flex-col items-center gap-0.5" aria-label="Partager" @click="share">
                    <span
                        aria-hidden="true"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-lg text-white/90"
                        >🔗</span
                    >
                    <span class="text-[10px] text-white/80">{{ delivery.interactions.shares }}</span>
                </button>
            </div>

            <div
                v-if="alertsNotice"
                class="absolute right-16 bottom-32 z-20 rounded-full bg-black/70 px-2.5 py-1 text-[10px] text-white/90"
            >
                {{ alertsNotice }}
            </div>

            <!-- Bottom brand / CTA row. -->
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
