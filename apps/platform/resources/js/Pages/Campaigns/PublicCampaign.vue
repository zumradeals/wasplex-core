<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
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
    campaign: PublicCampaign;
    authenticated: boolean;
}>();

const visitId = ref<string | null>(null);
const completed = ref(false);
const shared = ref(false);
const trackingUnavailable = ref(false);
let imageCompletionTimer: ReturnType<typeof setTimeout> | null = null;

const shareUrl = computed(() => window.location.href);
const memberActionLabel = computed(() => (props.authenticated ? 'Ouvrir mon Feed Wasplex' : 'Rejoindre Wasplex'));

function randomToken(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;
}

function visitorToken(): string {
    const key = 'wpx-public-visitor';
    const existing = window.localStorage.getItem(key);
    if (existing) return existing;

    const created = randomToken();
    window.localStorage.setItem(key, created);
    return created;
}

async function startTracking(): Promise<void> {
    try {
        const { data } = await http.post(`/public/campaigns/${props.campaign.slug}/visits`, {
            visitor_token: visitorToken(),
            visit_token: randomToken(),
        });
        visitId.value = data.visit_id;

        // Une image n'a pas d'événement `ended`. Trois secondes visibles sur
        // la page suffisent pour compter une lecture publique complète ; cette
        // métrique n'ouvre aucun droit à récompense.
        if (props.campaign.creative?.type === 'image') {
            imageCompletionTimer = setTimeout(() => void markComplete(), 3000);
        }
    } catch {
        trackingUnavailable.value = true;
    }
}

async function markComplete(): Promise<void> {
    if (completed.value || visitId.value === null) return;
    completed.value = true;

    try {
        await http.post(`/public/campaigns/${props.campaign.slug}/visits/${visitId.value}/complete`);
    } catch {
        // La publicité reste consultable même si la télémétrie est momentanément indisponible.
    }
}

async function recordJoinAndContinue(): Promise<void> {
    if (visitId.value !== null) {
        try {
            await http.post(`/public/campaigns/${props.campaign.slug}/visits/${visitId.value}/join`);
        } catch {
            // La navigation ne dépend jamais du compteur public.
        }
    }

    window.location.href = props.authenticated ? '/app' : '/register';
}

async function shareCampaign(): Promise<void> {
    try {
        if (navigator.share) {
            await navigator.share({
                title: props.campaign.title,
                text: `${props.campaign.brand_name} sur Wasplex`,
                url: shareUrl.value,
            });
        } else {
            await navigator.clipboard.writeText(shareUrl.value);
        }

        shared.value = true;
        setTimeout(() => (shared.value = false), 1800);

        if (visitId.value !== null) {
            await http.post(`/public/campaigns/${props.campaign.slug}/visits/${visitId.value}/share`);
        }
    } catch {
        // Annuler la feuille de partage n'est pas une erreur utilisateur.
    }
}

onMounted(() => void startTracking());
onBeforeUnmount(() => {
    if (imageCompletionTimer !== null) clearTimeout(imageCompletionTimer);
});
</script>

<template>
    <main class="bg-wpx-navy-950 text-wpx-white-soft min-h-screen">
        <div class="mx-auto flex min-h-screen w-full max-w-lg flex-col">
            <header class="flex items-center justify-between px-4 py-4">
                <a href="/" class="flex items-center gap-2 font-extrabold">
                    <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-8 w-8 object-contain" />
                    <span>Wasplex</span>
                </a>
                <span
                    class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark rounded-full border px-3 py-1.5 text-[11px] font-bold"
                >
                    Publicité publique
                </span>
            </header>

            <section
                class="border-wpx-border-dark relative mx-3 overflow-hidden rounded-3xl border bg-black shadow-2xl"
            >
                <video
                    v-if="campaign.creative?.type === 'video'"
                    :src="campaign.creative.url"
                    class="aspect-[9/16] max-h-[68vh] w-full object-cover"
                    controls
                    autoplay
                    muted
                    playsinline
                    @ended="markComplete"
                />
                <img
                    v-else-if="campaign.creative?.type === 'image'"
                    :src="campaign.creative.url"
                    :alt="campaign.title"
                    class="aspect-[9/16] max-h-[68vh] w-full object-cover"
                />
                <div
                    v-else
                    class="from-wpx-navy-750 to-wpx-navy-950 flex aspect-[9/16] max-h-[68vh] items-center justify-center bg-gradient-to-br p-8 text-center"
                >
                    <p class="text-wpx-muted-dark text-sm">
                        Le média de cette publicité n'est pas disponible pour le moment.
                    </p>
                </div>

                <div
                    class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black via-black/80 to-transparent p-5 pt-20"
                >
                    <p class="text-wpx-gold text-[11px] font-extrabold tracking-[0.16em] uppercase">
                        {{ campaign.brand_name }}
                    </p>
                    <h1 class="mt-1 text-xl leading-tight font-black">{{ campaign.title }}</h1>
                    <p v-if="campaign.cta_label" class="mt-2 text-xs font-semibold text-white/75">
                        Objectif de l'annonceur · {{ campaign.cta_label }}
                    </p>
                </div>
            </section>

            <section class="flex flex-col gap-3 px-4 py-5">
                <div class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4">
                    <p class="text-wpx-white-soft text-sm font-extrabold">Accessible à tous, sans compte</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                        Cette vue publique apporte de la visibilité à l'annonceur mais ne verse aucun WP et ne consomme
                        aucun quota membre. Les récompenses sont réservées au Feed Wasplex authentifié.
                    </p>
                </div>

                <button
                    type="button"
                    class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-2xl bg-gradient-to-r px-4 py-3.5 text-sm font-black"
                    @click="recordJoinAndContinue"
                >
                    {{ memberActionLabel }}
                </button>
                <button
                    type="button"
                    class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft rounded-2xl border px-4 py-3 text-sm font-bold"
                    @click="shareCampaign"
                >
                    {{ shared ? 'Lien partagé / copié ✓' : 'Partager cette publicité' }}
                </button>

                <p class="text-wpx-muted-dark px-2 text-center text-[11px] leading-relaxed">
                    Après inscription, une éventuelle récompense dépend toujours de l'éligibilité dans le Feed. Une vue
                    anonyme n'est jamais rémunérée rétroactivement.
                </p>
                <p v-if="trackingUnavailable" class="text-wpx-muted-dark text-center text-[10px]">
                    La mesure de portée est momentanément indisponible ; la publicité reste consultable.
                </p>
            </section>
        </div>
    </main>
</template>
