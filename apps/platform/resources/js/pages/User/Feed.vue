<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import UserLayout from '@/layouts/UserLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Wallet = {
    unit: string;
    currency: string;
    balances: {
        available: number;
        reserved: number;
        pending: number;
        total: number;
    };
};

type FeedSession = {
    feed_session_id: string;
    status: 'active' | 'expired' | 'closed';
    market: string;
    currency: string;
    territory: string | null;
    expires_at: string;
};

type ValuePreview = {
    gross_amount_minor: number;
    user_amount_minor: number;
    wasplex_amount_minor: number;
    unit: string;
    currency: string;
    attempt_started: false;
};

type Delivery = {
    delivery_id: string;
    feed_session_id: string;
    match_id: string;
    campaign_id: string;
    campaign_version_id: string;
    creative_id: string;
    creative_type: 'image' | 'video';
    media_reference: string;
    cta_reference: string | null;
    explanation_reference: string;
    quota_remaining: number;
    value_preview: ValuePreview | null;
    status: 'prepared' | 'delivered' | 'invalidated' | 'superseded';
    expires_at: string;
    delivered_at: string | null;
};

type FeedState =
    | 'booting'
    | 'session_ready'
    | 'preparing'
    | 'media_loading'
    | 'confirming'
    | 'active'
    | 'paused'
    | 'offline'
    | 'empty'
    | 'quota_exhausted'
    | 'failed';

class FeedApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
    }
}

const props = defineProps<{
    account: { id: string; displayName: string | null };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    wallet: Wallet;
    feedConfig: {
        defaultMarket: string;
        mediaLoadTimeoutMs: number;
        sessionStorageKey: string;
        dataSaverStorageKey: string;
    };
    endpoints: {
        sessions: string;
        prepare: string;
        confirm: string;
        release: string;
        dismiss: string;
    };
}>();

const state = ref<FeedState>('booting');
const sessionId = ref<string | null>(null);
const delivery = ref<Delivery | null>(null);
const errorMessage = ref('');
const online = ref(true);
const muted = ref(true);
const autoplayBlocked = ref(false);
const dataSaver = ref(false);
const video = ref<HTMLVideoElement | null>(null);
let mediaTimer: number | null = null;
let confirmingDelivery = false;

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

const identifier = (prefix: string) => {
    const random = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`;

    return `${prefix}-${random}`.slice(0, 64);
};

const endpoint = (template: string, marker: string, value: string) =>
    template.replace(marker, encodeURIComponent(value));

const api = async <T,>(
    url: string,
    method: 'POST' | 'GET',
    idempotencyKey?: string,
    body: Record<string, unknown> = {},
): Promise<T> => {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (idempotencyKey) {
        headers['Idempotency-Key'] = idempotencyKey;
    }

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: method === 'POST' ? JSON.stringify(body) : undefined,
    });
    const payload = (await response.json().catch(() => ({}))) as {
        data?: T;
        message?: string;
    };

    if (!response.ok || payload.data === undefined) {
        throw new FeedApiError(
            payload.message ?? 'Le Feed est momentanément indisponible.',
            response.status,
        );
    }

    return payload.data;
};

const gainLabel = computed(() => {
    const preview = delivery.value?.value_preview;

    if (!preview) {
        return 'Gain calculé au démarrage';
    }

    return `${new Intl.NumberFormat('fr-FR').format(preview.user_amount_minor)} ${preview.unit}`;
});

const stateLabel = computed(() => {
    const labels: Record<FeedState, string> = {
        booting: 'Initialisation du Feed…',
        session_ready: 'Session prête',
        preparing: 'Recherche d’une publicité compatible…',
        media_loading: 'Chargement du média…',
        confirming: 'Confirmation de l’affichage…',
        active: 'Publicité livrée',
        paused: 'Lecture en pause',
        offline: 'Connexion interrompue',
        empty: 'Aucune publicité disponible',
        quota_exhausted: 'Quota publicitaire atteint',
        failed: 'Impossible de charger ce contenu',
    };

    return labels[state.value];
});

const clearMediaTimer = () => {
    if (mediaTimer !== null) {
        window.clearTimeout(mediaTimer);
        mediaTimer = null;
    }
};

const clearStoredSession = () => {
    sessionStorage.removeItem(props.feedConfig.sessionStorageKey);
    sessionId.value = null;
};

const ensureSession = async (force = false) => {
    if (!force) {
        const stored = sessionStorage.getItem(props.feedConfig.sessionStorageKey);

        if (stored) {
            sessionId.value = stored;
            state.value = 'session_ready';

            return;
        }
    }

    clearStoredSession();
    const session = await api<FeedSession>(
        props.endpoints.sessions,
        'POST',
        identifier('feed-session'),
        { market: props.feedConfig.defaultMarket },
    );
    sessionId.value = session.feed_session_id;
    sessionStorage.setItem(props.feedConfig.sessionStorageKey, session.feed_session_id);
    state.value = 'session_ready';
};

const releasePrepared = async () => {
    const current = delivery.value;

    if (!current || current.status !== 'prepared') {
        return;
    }

    try {
        await api<Delivery>(
            endpoint(props.endpoints.release, '__DELIVERY__', current.delivery_id),
            'POST',
            `release-${current.delivery_id}`.slice(0, 64),
        );
    } catch {
        // Le bail P009-B expirera côté serveur si la libération ne peut pas être transmise.
    }
};

const mediaFailure = async (message: string) => {
    clearMediaTimer();
    errorMessage.value = message;
    await releasePrepared();
    delivery.value = null;
    state.value = online.value ? 'failed' : 'offline';
};

const confirmCurrent = async () => {
    const current = delivery.value;

    if (!current || current.status !== 'prepared' || confirmingDelivery) {
        return;
    }

    confirmingDelivery = true;
    state.value = 'confirming';

    try {
        const confirmed = await api<Delivery>(
            endpoint(props.endpoints.confirm, '__DELIVERY__', current.delivery_id),
            'POST',
            `confirm-${current.delivery_id}`.slice(0, 64),
        );
        delivery.value = confirmed;
        state.value = 'active';
        await nextTick();

        if (confirmed.creative_type === 'video' && video.value) {
            video.value.muted = muted.value;
            const playback = video.value.play();

            if (playback) {
                await playback.catch(() => {
                    autoplayBlocked.value = true;
                });
            }
        }
    } catch (error) {
        await mediaFailure(
            error instanceof Error ? error.message : 'La livraison ne peut pas être confirmée.',
        );
    } finally {
        confirmingDelivery = false;
    }
};

const mediaReady = async () => {
    clearMediaTimer();

    if (document.hidden) {
        state.value = 'paused';
        return;
    }

    await confirmCurrent();
};

const prepareNext = async (retrySession = true) => {
    if (!online.value) {
        state.value = 'offline';
        return;
    }

    clearMediaTimer();
    errorMessage.value = '';
    autoplayBlocked.value = false;
    delivery.value = null;
    state.value = 'preparing';

    try {
        if (!sessionId.value) {
            await ensureSession();
        }

        const prepared = await api<Delivery>(
            endpoint(props.endpoints.prepare, '__SESSION__', sessionId.value as string),
            'POST',
            identifier('feed-prepare'),
        );
        delivery.value = prepared;
        state.value = 'media_loading';
        mediaTimer = window.setTimeout(() => {
            void mediaFailure(
                'Le média met trop de temps à répondre. Aucun quota n’a été consommé.',
            );
        }, props.feedConfig.mediaLoadTimeoutMs);
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Le Feed ne peut pas être chargé.';

        if (retrySession && message.includes('session Feed n’est plus active')) {
            clearStoredSession();
            await ensureSession(true);
            await prepareNext(false);
            return;
        }

        errorMessage.value = message;
        delivery.value = null;

        if (message.toLowerCase().includes('quota')) {
            state.value = 'quota_exhausted';
        } else if (message.includes('Aucune publicité livrable')) {
            state.value = 'empty';
        } else {
            state.value = online.value ? 'failed' : 'offline';
        }
    }
};

const dismissCurrent = async (reason: 'skipped' | 'not_relevant' | 'technical_issue') => {
    const current = delivery.value;

    if (!current) {
        await prepareNext();
        return;
    }

    clearMediaTimer();
    video.value?.pause();

    try {
        if (current.status === 'prepared') {
            await releasePrepared();
        } else if (current.status === 'delivered') {
            await api<Delivery>(
                endpoint(props.endpoints.dismiss, '__DELIVERY__', current.delivery_id),
                'POST',
                `dismiss-${current.delivery_id}`.slice(0, 64),
                { reason },
            );
        }
    } finally {
        delivery.value = null;
        await prepareNext();
    }
};

const retryPlayback = async () => {
    if (!video.value) {
        return;
    }

    video.value.muted = muted.value;
    await video.value.play();
    autoplayBlocked.value = false;
};

const toggleMute = () => {
    muted.value = !muted.value;

    if (video.value) {
        video.value.muted = muted.value;
    }
};

const toggleDataSaver = () => {
    dataSaver.value = !dataSaver.value;
    localStorage.setItem(props.feedConfig.dataSaverStorageKey, dataSaver.value ? '1' : '0');
};

const onVisibilityChange = () => {
    if (document.hidden) {
        video.value?.pause();
        if (delivery.value && ['prepared', 'delivered'].includes(delivery.value.status)) {
            state.value = 'paused';
        }
        return;
    }

    if (delivery.value?.status === 'prepared' && state.value === 'paused') {
        void confirmCurrent();
        return;
    }

    if (delivery.value?.creative_type === 'video' && video.value && state.value === 'paused') {
        state.value = 'active';
        void video.value.play().catch(() => {
            autoplayBlocked.value = true;
        });
    }
};

const onOnline = () => {
    online.value = true;
    if (state.value === 'offline') {
        void prepareNext();
    }
};

const onOffline = () => {
    online.value = false;
    video.value?.pause();
    state.value = 'offline';
};

onMounted(async () => {
    online.value = navigator.onLine;
    dataSaver.value = localStorage.getItem(props.feedConfig.dataSaverStorageKey) === '1';
    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);
    document.addEventListener('visibilitychange', onVisibilityChange);

    if (!online.value) {
        state.value = 'offline';
        return;
    }

    await ensureSession();
    await prepareNext();
});

onBeforeUnmount(() => {
    clearMediaTimer();
    window.removeEventListener('online', onOnline);
    window.removeEventListener('offline', onOffline);
    document.removeEventListener('visibilitychange', onVisibilityChange);
    video.value?.pause();
    void releasePrepared();
});
</script>

<template>
    <Head title="Pour toi" />
    <UserLayout
        :account="account"
        :spaces="spaces"
        :wallet="wallet"
        active="feed"
        :immersive="true"
    >
        <section
            class="grid min-h-[calc(100dvh-8.8rem)] lg:grid-cols-[16rem_minmax(22rem,30rem)_16rem] lg:justify-center lg:gap-5 lg:p-5"
        >
            <aside
                class="hidden rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 lg:block"
            >
                <p class="text-xs font-black tracking-[0.2em] text-wasplex-cyan uppercase">
                    Pour toi
                </p>
                <h1 class="mt-3 text-2xl font-black">Bonjour {{ account.displayName }}</h1>
                <p class="mt-3 text-sm leading-6 text-white/42">
                    Des publicités compatibles avec vos choix, sans transmettre votre identité aux
                    annonceurs.
                </p>
                <div
                    class="mt-6 rounded-2xl border border-wasplex-gold/15 bg-wasplex-gold/[0.055] p-4"
                >
                    <p class="text-xs font-black text-wasplex-gold">Gain potentiel</p>
                    <p class="mt-2 text-xl font-black">{{ gainLabel }}</p>
                    <p class="mt-2 text-xs leading-5 text-white/38">
                        Ce montant n’est pas acquis. La validation d’attention et le crédit Wallet
                        restent côté serveur.
                    </p>
                </div>
            </aside>

            <div
                class="relative flex min-h-0 flex-col overflow-hidden bg-black lg:rounded-[2rem] lg:border lg:border-white/10"
            >
                <div
                    class="absolute top-0 right-0 left-0 z-20 flex items-center justify-between bg-gradient-to-b from-black/75 to-transparent px-4 pt-4 pb-10"
                >
                    <div
                        class="flex gap-2 rounded-full bg-black/35 p-1 text-xs font-black backdrop-blur-lg"
                    >
                        <span class="rounded-full bg-white px-3 py-1.5 text-wasplex-night"
                            >Pour toi</span
                        >
                        <span class="px-3 py-1.5 text-white/45">Alertes</span>
                        <span class="px-3 py-1.5 text-white/45">Explorer</span>
                    </div>
                    <button
                        type="button"
                        class="rounded-full border border-white/15 bg-black/35 px-3 py-2 text-[0.68rem] font-black text-white/70 backdrop-blur-lg"
                        :aria-pressed="dataSaver"
                        @click="toggleDataSaver"
                    >
                        Données {{ dataSaver ? 'éco' : 'auto' }}
                    </button>
                </div>

                <div class="relative grid min-h-[calc(100dvh-8.8rem)] place-items-center">
                    <template v-if="delivery">
                        <img
                            v-if="delivery.creative_type === 'image'"
                            :src="delivery.media_reference"
                            class="h-full max-h-[calc(100dvh-8.8rem)] w-full object-contain"
                            alt="Création publicitaire"
                            @load="mediaReady"
                            @error="
                                mediaFailure(
                                    'Cette image ne peut pas être affichée. Aucun quota n’a été consommé.',
                                )
                            "
                        />
                        <video
                            v-else
                            ref="video"
                            :src="delivery.media_reference"
                            class="h-full max-h-[calc(100dvh-8.8rem)] w-full object-contain"
                            :muted="muted"
                            playsinline
                            :preload="dataSaver ? 'metadata' : 'auto'"
                            aria-label="Vidéo publicitaire"
                            @canplay="mediaReady"
                            @error="
                                mediaFailure(
                                    'Cette vidéo ne peut pas être lue. Aucun quota n’a été consommé.',
                                )
                            "
                        />
                    </template>

                    <div
                        v-else
                        class="mx-auto max-w-sm px-7 text-center"
                        role="status"
                        aria-live="polite"
                    >
                        <div
                            class="mx-auto grid size-16 place-items-center rounded-full border border-wasplex-cyan/20 bg-wasplex-cyan/[0.07] text-2xl text-wasplex-cyan"
                        >
                            {{
                                state === 'offline' ? '⌁' : state === 'quota_exhausted' ? '✓' : '▶'
                            }}
                        </div>
                        <h2 class="mt-5 text-xl font-black">{{ stateLabel }}</h2>
                        <p class="mt-3 text-sm leading-6 text-white/45">
                            {{
                                errorMessage ||
                                (state === 'quota_exhausted'
                                    ? 'Le Feed commercial reprendra au prochain cycle de votre abonnement.'
                                    : state === 'empty'
                                      ? 'Aucun contenu compatible n’est disponible maintenant. Revenez plus tard.'
                                      : state === 'offline'
                                        ? 'Reconnectez-vous pour reprendre sans double livraison ni faux crédit.'
                                        : 'Wasplex prépare un contenu compatible avec votre profil volontaire.')
                            }}
                        </p>
                        <button
                            v-if="['failed', 'empty', 'offline'].includes(state)"
                            type="button"
                            class="mt-6 wasplex-button-primary"
                            @click="prepareNext()"
                        >
                            Réessayer
                        </button>
                    </div>

                    <div
                        v-if="delivery && ['media_loading', 'confirming'].includes(state)"
                        class="absolute inset-0 z-10 grid place-items-center bg-black/55 backdrop-blur-sm"
                        role="status"
                    >
                        <div class="text-center">
                            <div
                                class="mx-auto size-10 animate-spin rounded-full border-2 border-white/15 border-t-wasplex-cyan motion-reduce:animate-none"
                            />
                            <p class="mt-4 text-sm font-black">{{ stateLabel }}</p>
                            <p class="mt-2 text-xs text-white/45">
                                Le préchargement seul ne consomme aucun quota.
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="autoplayBlocked && delivery?.creative_type === 'video'"
                        type="button"
                        class="absolute z-20 rounded-full bg-white px-5 py-3 text-sm font-black text-wasplex-night shadow-xl"
                        @click="retryPlayback"
                    >
                        Lire la vidéo
                    </button>
                </div>

                <div
                    v-if="delivery"
                    class="pointer-events-none absolute right-0 bottom-0 left-0 z-20 bg-gradient-to-t from-black via-black/72 to-transparent px-4 pt-28 pb-4"
                >
                    <div class="pointer-events-auto flex items-end justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full bg-wasplex-gold px-2.5 py-1 text-[0.62rem] font-black text-wasplex-night"
                                    >PUBLICITÉ</span
                                >
                                <span
                                    class="rounded-full bg-white/10 px-2.5 py-1 text-[0.62rem] font-black text-white/70"
                                    >{{ gainLabel }} potentiel</span
                                >
                            </div>
                            <p class="mt-3 text-sm font-black">
                                Quota restant : {{ delivery.quota_remaining }}
                            </p>
                            <p class="mt-1 text-xs text-white/48">
                                La preuve d’attention sera traitée dans la phase suivante.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a
                                    v-if="delivery.cta_reference"
                                    :href="delivery.cta_reference"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="rounded-2xl bg-white px-4 py-2.5 text-xs font-black text-wasplex-night"
                                >
                                    Découvrir
                                </a>
                                <Link
                                    :href="delivery.explanation_reference"
                                    class="rounded-2xl border border-white/18 bg-black/30 px-4 py-2.5 text-xs font-black text-white/80"
                                >
                                    Pourquoi cette pub ?
                                </Link>
                            </div>
                        </div>
                        <div class="pointer-events-auto flex flex-col gap-2">
                            <button
                                v-if="delivery.creative_type === 'video'"
                                type="button"
                                class="grid size-11 place-items-center rounded-full border border-white/15 bg-black/45 text-sm font-black backdrop-blur-lg"
                                :aria-label="muted ? 'Activer le son' : 'Couper le son'"
                                @click="toggleMute"
                            >
                                {{ muted ? 'Muet' : 'Son' }}
                            </button>
                            <button
                                type="button"
                                class="grid size-11 place-items-center rounded-full border border-white/15 bg-black/45 text-xs font-black backdrop-blur-lg"
                                aria-label="Passer cette publicité"
                                @click="dismissCurrent('skipped')"
                            >
                                Suiv.
                            </button>
                            <button
                                type="button"
                                class="grid size-11 place-items-center rounded-full border border-white/15 bg-black/45 text-[0.6rem] font-black backdrop-blur-lg"
                                aria-label="Voir moins de contenus similaires"
                                @click="dismissCurrent('not_relevant')"
                            >
                                Moins
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="!online"
                    class="absolute top-20 right-4 left-4 z-30 rounded-2xl border border-amber-300/20 bg-amber-950/85 px-4 py-3 text-center text-xs font-black text-amber-100 backdrop-blur-xl"
                >
                    Hors ligne — la lecture est suspendue et aucun nouveau quota ne sera consommé.
                </div>
            </div>

            <aside
                class="hidden rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 lg:block"
            >
                <p class="text-xs font-black tracking-[0.2em] text-white/35 uppercase">
                    Transparence
                </p>
                <p class="mt-4 text-sm font-black">Votre identité reste privée</p>
                <p class="mt-2 text-xs leading-5 text-white/40">
                    Le Feed reçoit une décision de Matching et une explication autorisée, jamais vos
                    réponses complètes.
                </p>
                <div class="mt-6 space-y-3 text-xs text-white/45">
                    <p class="rounded-2xl border border-white/8 p-3">Préchargement : zéro quota</p>
                    <p class="rounded-2xl border border-white/8 p-3">
                        Affichage réel : une unité maximum
                    </p>
                    <p class="rounded-2xl border border-white/8 p-3">
                        Crédit Wallet : serveur uniquement
                    </p>
                </div>
            </aside>
        </section>
    </UserLayout>
</template>
