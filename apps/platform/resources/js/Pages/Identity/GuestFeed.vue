<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface Creative {
    url: string;
    type: string;
    duration: number | null;
}

interface PublicCampaign {
    id: string;
    slug: string;
    title: string;
    brand_name: string;
    objective_code: string | null;
    cta_label: string | null;
    creative: Creative | null;
}

const props = defineProps<{
    campaigns: PublicCampaign[];
    simulatedRewardMinor: number;
}>();

const SIMULATED_BALANCE_KEY = 'wpx-guest-simulated-balance';
const SIMULATED_COMPLETIONS_KEY = 'wpx-guest-simulated-completions';
const VISITOR_TOKEN_KEY = 'wpx-public-visitor';

const currentIndex = ref(0);
const visitId = ref<string | null>(null);
const simulatedBalance = ref(0);
const gainToast = ref<number | null>(null);
const shared = ref(false);
const videoRef = ref<HTMLVideoElement | null>(null);

let imageCompletionTimer: ReturnType<typeof setTimeout> | null = null;
let gainToastTimer: ReturnType<typeof setTimeout> | null = null;
let touchStartY = 0;
let wheelLocked = false;

const currentCampaign = computed(() => props.campaigns[currentIndex.value] ?? null);
const simulatedReward = computed(() => Math.max(0, props.simulatedRewardMinor));

function randomToken(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

function visitorToken(): string {
    const existing = window.localStorage.getItem(VISITOR_TOKEN_KEY);
    if (existing) return existing;

    const created = randomToken();
    window.localStorage.setItem(VISITOR_TOKEN_KEY, created);

    return created;
}

function simulatedCompletions(): Set<string> {
    try {
        const parsed = JSON.parse(window.sessionStorage.getItem(SIMULATED_COMPLETIONS_KEY) ?? '[]');
        return new Set(Array.isArray(parsed) ? parsed.filter((value) => typeof value === 'string') : []);
    } catch {
        return new Set();
    }
}

function loadSimulation(): void {
    const saved = Number(window.sessionStorage.getItem(SIMULATED_BALANCE_KEY) ?? '0');
    simulatedBalance.value = Number.isFinite(saved) ? Math.max(0, saved) : 0;
}

function clearTimers(): void {
    if (imageCompletionTimer !== null) {
        clearTimeout(imageCompletionTimer);
        imageCompletionTimer = null;
    }
    if (gainToastTimer !== null) {
        clearTimeout(gainToastTimer);
        gainToastTimer = null;
    }
}

async function startTracking(): Promise<void> {
    const campaign = currentCampaign.value;
    visitId.value = null;
    if (campaign === null) return;

    try {
        const { data } = await http.post(`/public/campaigns/${campaign.slug}/visits`, {
            visitor_token: visitorToken(),
            visit_token: randomToken(),
        });
        visitId.value = data.visit_id;
    } catch {
        // Le Feed public reste visible même si la mesure de portée est indisponible.
    }

    if (campaign.creative?.type === 'image') {
        imageCompletionTimer = setTimeout(() => void markComplete(), 3000);
    }
}

function creditSimulation(campaignId: string): void {
    if (simulatedReward.value <= 0) return;

    const completed = simulatedCompletions();
    if (completed.has(campaignId)) return;

    completed.add(campaignId);
    window.sessionStorage.setItem(SIMULATED_COMPLETIONS_KEY, JSON.stringify([...completed]));

    simulatedBalance.value += simulatedReward.value;
    window.sessionStorage.setItem(SIMULATED_BALANCE_KEY, String(simulatedBalance.value));

    gainToast.value = simulatedReward.value;
    if (gainToastTimer !== null) clearTimeout(gainToastTimer);
    gainToastTimer = setTimeout(() => {
        gainToast.value = null;
        gainToastTimer = null;
    }, 2200);
}

async function markComplete(): Promise<void> {
    const campaign = currentCampaign.value;
    if (campaign === null) return;

    creditSimulation(campaign.id);

    if (visitId.value === null) return;

    try {
        await http.post(`/public/campaigns/${campaign.slug}/visits/${visitId.value}/complete`);
    } catch {
        // La simulation ne dépend jamais de la télémétrie publique.
    }
}

async function activate(index: number): Promise<void> {
    if (props.campaigns.length === 0) return;

    clearTimers();
    videoRef.value?.pause();
    visitId.value = null;
    gainToast.value = null;
    currentIndex.value = (index + props.campaigns.length) % props.campaigns.length;

    await nextTick();
    void startTracking();
}

function nextCampaign(): void {
    void activate(currentIndex.value + 1);
}

function previousCampaign(): void {
    void activate(currentIndex.value - 1);
}

function onTouchStart(event: TouchEvent): void {
    touchStartY = event.touches[0]?.clientY ?? 0;
}

function onTouchEnd(event: TouchEvent): void {
    const endY = event.changedTouches[0]?.clientY ?? touchStartY;
    const delta = touchStartY - endY;

    if (delta > 55) nextCampaign();
    if (delta < -55) previousCampaign();
}

function onWheel(event: WheelEvent): void {
    if (wheelLocked || Math.abs(event.deltaY) < 40) return;
    wheelLocked = true;

    if (event.deltaY > 0) {
        nextCampaign();
    } else {
        previousCampaign();
    }

    setTimeout(() => {
        wheelLocked = false;
    }, 500);
}

async function recordJoinAndGo(path: '/login' | '/register'): Promise<void> {
    const campaign = currentCampaign.value;

    if (campaign !== null && visitId.value !== null) {
        try {
            await http.post(`/public/campaigns/${campaign.slug}/visits/${visitId.value}/join`);
        } catch {
            // L'accès à l'inscription/connexion ne dépend jamais du compteur public.
        }
    }

    window.location.href = path;
}

async function shareCampaign(): Promise<void> {
    const campaign = currentCampaign.value;
    if (campaign === null) return;

    try {
        const url = `${window.location.origin}/c/${campaign.slug}`;
        if (navigator.share) {
            await navigator.share({
                title: campaign.title,
                text: `${campaign.brand_name} sur Wasplex`,
                url,
            });
        } else {
            await navigator.clipboard.writeText(url);
        }

        shared.value = true;
        setTimeout(() => (shared.value = false), 1800);

        if (visitId.value !== null) {
            await http.post(`/public/campaigns/${campaign.slug}/visits/${visitId.value}/share`);
        }
    } catch {
        // Annuler la feuille de partage n'est pas une erreur utilisateur.
    }
}

onMounted(() => {
    loadSimulation();
    void startTracking();
});

onBeforeUnmount(() => {
    clearTimers();
});
</script>

<template>
    <main class="bg-wpx-navy-950 text-wpx-white-soft flex min-h-screen justify-center">
        <div class="bg-wpx-navy-950 relative flex min-h-screen w-full max-w-md flex-col">
            <template v-if="currentCampaign">
                <section
                    class="relative min-h-screen overflow-hidden bg-black"
                    @touchstart="onTouchStart"
                    @touchend="onTouchEnd"
                    @wheel.passive="onWheel"
                >
                    <video
                        v-if="currentCampaign.creative?.type === 'video'"
                        ref="videoRef"
                        :key="currentCampaign.id"
                        :src="currentCampaign.creative.url"
                        class="absolute inset-0 h-full w-full object-cover"
                        autoplay
                        muted
                        playsinline
                        preload="auto"
                        @ended="markComplete"
                    />
                    <img
                        v-else-if="currentCampaign.creative?.type === 'image'"
                        :key="currentCampaign.id"
                        :src="currentCampaign.creative.url"
                        :alt="currentCampaign.title"
                        class="absolute inset-0 h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 absolute inset-0 flex items-center justify-center bg-gradient-to-br px-8 text-center"
                    >
                        <p class="text-wpx-muted-dark text-sm">
                            Le média de cette publicité publique est momentanément indisponible.
                        </p>
                    </div>

                    <div
                        class="absolute inset-x-0 top-0 z-20 bg-gradient-to-b from-black/90 via-black/50 to-transparent px-3.5 pt-3 pb-12"
                    >
                        <div class="flex items-center gap-2">
                            <img
                                src="/brand/wasplex-logo-transparent.png"
                                alt="Wasplex"
                                class="h-7 w-7 shrink-0 object-contain"
                            />

                            <nav class="flex flex-1 items-center justify-center gap-3 text-[11px]">
                                <span class="border-wpx-blue border-b-2 pb-1 font-bold text-white">Pour toi</span>
                                <span class="pb-1 font-semibold text-white/65">Explorer</span>
                            </nav>

                            <button
                                type="button"
                                class="border-wpx-gold/45 flex shrink-0 items-center gap-1.5 rounded-full border bg-black/55 px-2.5 py-1.5"
                                @click.stop="recordJoinAndGo('/register')"
                            >
                                <span class="bg-wpx-gold h-1.5 w-1.5 rounded-full" />
                                <span class="text-wpx-gold text-[11px] font-black">{{ simulatedBalance }} WP*</span>
                            </button>
                        </div>

                        <div class="mt-2 flex justify-end">
                            <button
                                type="button"
                                class="rounded-full bg-white/12 px-3 py-1.5 text-[10px] font-bold text-white backdrop-blur"
                                @click.stop="recordJoinAndGo('/login')"
                            >
                                Se connecter
                            </button>
                        </div>
                    </div>

                    <div v-if="gainToast !== null" class="absolute inset-x-0 top-28 z-30 flex justify-center px-4">
                        <div
                            class="border-wpx-gold/40 bg-wpx-navy-950/92 rounded-2xl border px-4 py-3 text-center shadow-2xl backdrop-blur"
                        >
                            <p class="text-wpx-gold text-lg font-black">+{{ gainToast }} WP potentiels</p>
                            <p class="mt-0.5 text-[10px] text-white/70">
                                Simulation — inscris-toi pour gagner réellement.
                            </p>
                        </div>
                    </div>

                    <div
                        class="absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black via-black/90 to-transparent px-4 pt-28 pb-5"
                    >
                        <p class="text-wpx-gold text-[10px] font-extrabold tracking-[0.16em] uppercase">
                            Sponsorisé · {{ currentCampaign.brand_name }}
                        </p>
                        <h1 class="mt-1 max-w-[85%] text-xl leading-tight font-black">{{ currentCampaign.title }}</h1>

                        <div class="mt-4 flex items-end gap-3">
                            <div class="min-w-0 flex-1">
                                <button
                                    type="button"
                                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 w-full rounded-2xl bg-gradient-to-r px-4 py-3 text-sm font-black"
                                    @click.stop="recordJoinAndGo('/register')"
                                >
                                    Créer mon compte pour gagner
                                </button>
                                <p class="mt-2 text-center text-[9px] leading-relaxed text-white/55">
                                    * Solde simulé uniquement. Aucun WP réel n'est créé et cette vue publique ne débite
                                    pas le budget rémunéré de l'annonceur.
                                </p>
                            </div>

                            <button
                                type="button"
                                aria-label="Partager"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/12 text-lg backdrop-blur"
                                @click.stop="shareCampaign"
                            >
                                {{ shared ? '✓' : '↗' }}
                            </button>
                        </div>

                        <div v-if="props.campaigns.length > 1" class="mt-3 flex items-center justify-center gap-1.5">
                            <span
                                v-for="(_, index) in props.campaigns"
                                :key="index"
                                class="h-1 rounded-full transition-all"
                                :class="index === currentIndex ? 'bg-wpx-blue w-6' : 'w-2 bg-white/30'"
                            />
                        </div>
                    </div>
                </section>
            </template>

            <section v-else class="flex min-h-screen flex-col items-center justify-center gap-5 px-6 text-center">
                <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-20 w-20 object-contain" />
                <div>
                    <h1 class="text-xl font-black">Ton attention a de la valeur.</h1>
                    <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                        Aucune campagne publique n'est disponible maintenant. Crée ton compte pour accéder à ton Feed
                        Wasplex et à tes campagnes éligibles.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 w-full rounded-2xl bg-gradient-to-r px-4 py-3.5 text-sm font-black"
                    @click="recordJoinAndGo('/register')"
                >
                    Créer mon compte
                </button>
                <button type="button" class="text-wpx-blue text-sm font-semibold" @click="recordJoinAndGo('/login')">
                    J'ai déjà un compte
                </button>
            </section>
        </div>
    </main>
</template>
