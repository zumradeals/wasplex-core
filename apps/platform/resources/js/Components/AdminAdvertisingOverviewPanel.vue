<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface ReviewCase {
    id: string;
}

interface Advertiser {
    id: string;
    status: string;
}

interface Hold {
    id: string;
    amount_minor: number;
}

const emit = defineEmits<{
    openTab: [tab: 'advertisers' | 'feed' | 'matching' | 'smartprofile'];
}>();

const pendingCampaigns = ref(0);
const pendingAdvertisers = ref(0);
const activeCampaigns = ref(0);
const holds = ref<Hold[]>([]);
const wpDistributed = ref(0);
const completedDeliveries = ref(0);
const loading = ref(true);

const numberFormatter = new Intl.NumberFormat('fr-FR');
const heldAmount = computed(() => holds.value.reduce((total, hold) => total + hold.amount_minor, 0));

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [reviewsRes, advertisersRes, campaignsRes, holdsRes, feedRes] = await Promise.allSettled([
            http.get('/admin/campaign-reviews'),
            http.get('/admin/advertisers'),
            http.get('/admin/campaigns/approved'),
            http.get('/admin/feed/holds'),
            http.get('/admin/feed/dashboard'),
        ]);

        pendingCampaigns.value =
            reviewsRes.status === 'fulfilled' ? (reviewsRes.value.data.review_cases as ReviewCase[]).length : 0;
        pendingAdvertisers.value =
            advertisersRes.status === 'fulfilled'
                ? (advertisersRes.value.data.advertisers as Advertiser[]).filter((advertiser) => advertiser.status === 'draft')
                      .length
                : 0;
        activeCampaigns.value =
            campaignsRes.status === 'fulfilled' ? campaignsRes.value.data.campaigns.length : 0;
        holds.value = holdsRes.status === 'fulfilled' ? holdsRes.value.data.holds : [];
        wpDistributed.value = feedRes.status === 'fulfilled' ? feedRes.value.data.wp_distributed_minor : 0;
        completedDeliveries.value =
            feedRes.status === 'fulfilled' ? (feedRes.value.data.delivery_counts?.completed ?? 0) : 0;
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5 text-wpx-white-soft">
        <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark md:p-6">
            <p class="text-wpx-cyan text-[11px] font-extrabold uppercase tracking-[0.16em]">Centre publicité</p>
            <h2 class="mt-1 text-2xl font-extrabold">Décider, surveiller, puis régler.</h2>
            <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                Les campagnes et les annonceurs à valider arrivent en premier. L’antifraude, le ciblage et le Profil
                intelligent restent accessibles sans mélanger les décisions quotidiennes aux réglages avancés.
            </p>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement du centre publicité…</p>

        <template v-else>
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button
                    type="button"
                    class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-850 p-4 text-left shadow-wpx-card-dark"
                    @click="emit('openTab', 'advertisers')"
                >
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase tracking-wide">Campagnes à valider</p>
                    <p class="text-wpx-gold mt-2 text-2xl font-extrabold">{{ pendingCampaigns }}</p>
                    <p class="text-wpx-cyan mt-1 text-[11px] font-bold">Examiner →</p>
                </button>
                <button
                    type="button"
                    class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-850 p-4 text-left shadow-wpx-card-dark"
                    @click="emit('openTab', 'advertisers')"
                >
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase tracking-wide">Annonceurs à vérifier</p>
                    <p class="mt-2 text-2xl font-extrabold">{{ pendingAdvertisers }}</p>
                    <p class="text-wpx-cyan mt-1 text-[11px] font-bold">Voir les comptes →</p>
                </button>
                <button
                    type="button"
                    class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-850 p-4 text-left shadow-wpx-card-dark"
                    @click="emit('openTab', 'advertisers')"
                >
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase tracking-wide">En diffusion</p>
                    <p class="text-wpx-success mt-2 text-2xl font-extrabold">{{ activeCampaigns }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">campagnes actives</p>
                </button>
                <button
                    type="button"
                    class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-850 p-4 text-left shadow-wpx-card-dark"
                    @click="emit('openTab', 'feed')"
                >
                    <p class="text-wpx-muted-dark text-[10px] font-extrabold uppercase tracking-wide">Antifraude</p>
                    <p class="text-wpx-danger mt-2 text-2xl font-extrabold">{{ holds.length }}</p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px]">{{ numberFormatter.format(heldAmount) }} WP retenus</p>
                </button>
            </section>

            <section class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-extrabold">Résultats du Feed</h3>
                            <p class="text-wpx-muted-dark mt-1 text-xs">Uniquement des totaux, jamais l’identité d’une personne.</p>
                        </div>
                        <button type="button" class="text-wpx-cyan text-xs font-extrabold" @click="emit('openTab', 'feed')">
                            Voir →
                        </button>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <div class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-950 p-4">
                            <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Vues complètes</p>
                            <p class="mt-1 text-xl font-extrabold">{{ numberFormatter.format(completedDeliveries) }}</p>
                        </div>
                        <div class="border-wpx-border-dark rounded-wpx-lg border bg-wpx-navy-950 p-4">
                            <p class="text-wpx-muted-dark text-[10px] font-bold uppercase">Récompenses validées</p>
                            <p class="text-wpx-gold mt-1 text-xl font-extrabold">{{ numberFormatter.format(wpDistributed) }} WP</p>
                        </div>
                    </div>
                </div>

                <div class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
                    <h3 class="text-base font-extrabold">Réglages de diffusion</h3>
                    <p class="text-wpx-muted-dark mt-1 text-xs">
                        Ces réglages servent à protéger les utilisateurs et à définir quelles informations volontaires peuvent
                        participer au ciblage.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            type="button"
                            class="border-wpx-border-dark rounded-wpx-full border px-3 py-2 text-xs font-bold text-wpx-cyan"
                            @click="emit('openTab', 'matching')"
                        >
                            Diffusion & fréquence
                        </button>
                        <button
                            type="button"
                            class="border-wpx-border-dark rounded-wpx-full border px-3 py-2 text-xs font-bold text-wpx-cyan"
                            @click="emit('openTab', 'smartprofile')"
                        >
                            Profil intelligent
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>
