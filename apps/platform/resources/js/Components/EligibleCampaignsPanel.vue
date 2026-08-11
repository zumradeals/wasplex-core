<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

interface EligibleCampaign {
    campaign_id: string;
    brand_name: string | null;
    objective_code: string | null;
    cta_label: string | null;
    explanation: string[];
}

const emit = defineEmits<{ openFeed: [] }>();

const campaigns = ref<EligibleCampaign[]>([]);
const loading = ref(true);
const showDetails = ref(false);

const countLabel = computed(() => {
    if (campaigns.value.length === 0) return 'Aucune pour le moment';
    if (campaigns.value.length === 1) return '1 disponible';
    return `${campaigns.value.length} disponibles`;
});

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/me/eligible-campaigns');
        campaigns.value = data.campaigns;
    } finally {
        loading.value = false;
    }
}

function openFeed(): void {
    showDetails.value = false;
    emit('openFeed');
}

void load();
</script>

<template>
    <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg shadow-wpx-card-dark border p-3.5">
        <div class="flex items-center gap-3">
            <span class="bg-wpx-gold/14 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
                <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
                    <rect x="3" y="4" width="18" height="16" rx="4" stroke="#F2C14E" stroke-width="1.7" />
                    <path d="M10 9l6 3-6 3V9z" fill="#F2C14E" />
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-wpx-white-soft text-sm font-bold">Campagnes pour vous</p>
                    <span class="text-wpx-gold text-[10px] font-bold">{{ loading ? '…' : countLabel }}</span>
                </div>
                <p v-if="loading" class="text-wpx-muted-dark mt-0.5 text-[11px]">
                    Recherche des campagnes compatibles…
                </p>
                <p v-else-if="campaigns.length === 0" class="text-wpx-muted-dark mt-0.5 text-[11px] leading-relaxed">
                    Le Feed se met à jour automatiquement quand une campagne correspond à votre profil.
                </p>
                <p v-else class="text-wpx-muted-dark mt-0.5 text-[11px] leading-relaxed">
                    {{
                        campaigns.length === 1
                            ? 'Une campagne compatible vous attend.'
                            : 'Plusieurs campagnes compatibles vous attendent.'
                    }}
                </p>
            </div>
        </div>

        <div v-if="!loading && campaigns.length > 0" class="mt-3 flex gap-2">
            <button
                type="button"
                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md flex-1 bg-gradient-to-br px-3 py-2 text-xs font-bold"
                @click="openFeed"
            >
                Ouvrir le Feed
            </button>
            <button
                type="button"
                class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft rounded-wpx-md border px-3 py-2 text-xs font-semibold"
                @click="showDetails = true"
            >
                Détails
            </button>
        </div>
    </section>

    <Teleport to="body">
        <div
            v-if="showDetails"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 sm:items-center"
            @click.self="showDetails = false"
        >
            <div
                class="bg-wpx-navy-950 border-wpx-border-dark max-h-[88vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-4 shadow-2xl sm:rounded-3xl"
            >
                <div class="bg-wpx-navy-950 sticky top-0 z-10 flex items-start justify-between gap-3 pb-4">
                    <div>
                        <p class="text-wpx-white-soft text-lg font-bold">Pourquoi ces campagnes ?</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                            Wasplex vous montre ici les campagnes compatibles avec vos choix et consentements actuels.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                        @click="showDetails = false"
                    >
                        ×
                    </button>
                </div>

                <div class="flex flex-col gap-3">
                    <article
                        v-for="campaign in campaigns"
                        :key="campaign.campaign_id"
                        class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg border p-3.5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-wpx-white-soft text-sm font-bold">
                                    {{ campaign.brand_name ?? 'Annonceur' }}
                                </p>
                                <p v-if="campaign.cta_label" class="text-wpx-gold mt-0.5 text-xs">
                                    {{ campaign.cta_label }}
                                </p>
                            </div>
                            <span
                                class="bg-wpx-success/12 text-wpx-success-light rounded-full px-2 py-1 text-[10px] font-bold"
                            >
                                Compatible
                            </span>
                        </div>
                        <ul
                            v-if="campaign.explanation.length > 0"
                            class="text-wpx-muted-dark mt-2 list-disc pl-4 text-xs"
                        >
                            <li v-for="(reason, index) in campaign.explanation" :key="index">{{ reason }}</li>
                        </ul>
                    </article>
                </div>

                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md mt-4 w-full bg-gradient-to-br px-4 py-3 text-sm font-bold"
                    @click="openFeed"
                >
                    Voir dans le Feed
                </button>
            </div>
        </div>
    </Teleport>
</template>
