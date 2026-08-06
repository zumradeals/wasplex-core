<script setup lang="ts">
import { ref } from 'vue';
import http from '@/lib/http';

interface EligibleCampaign {
    campaign_id: string;
    brand_name: string | null;
    objective_code: string | null;
    cta_label: string | null;
    explanation: string[];
}

const campaigns = ref<EligibleCampaign[]>([]);
const loading = ref(true);
const expanded = ref<string | null>(null);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/me/eligible-campaigns');
        campaigns.value = data.campaigns;
    } finally {
        loading.value = false;
    }
}

function toggleExplanation(campaignId: string): void {
    expanded.value = expanded.value === campaignId ? null : campaignId;
}

void load();
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-3 p-4">
        <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">
            Campagnes qui vous correspondent
        </p>
        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <div
            v-for="campaign in campaigns"
            :key="campaign.campaign_id"
            class="border-wpx-border-dark rounded-wpx-md border p-3"
        >
            <div class="flex items-center justify-between gap-2">
                <div>
                    <p class="text-wpx-white-soft text-sm font-semibold">{{ campaign.brand_name ?? 'Annonceur' }}</p>
                    <p v-if="campaign.cta_label" class="text-wpx-gold text-xs">{{ campaign.cta_label }}</p>
                </div>
                <button
                    type="button"
                    class="text-wpx-blue-light shrink-0 text-xs hover:underline"
                    @click="toggleExplanation(campaign.campaign_id)"
                >
                    Pourquoi cette publicité ?
                </button>
            </div>
            <ul v-if="expanded === campaign.campaign_id" class="text-wpx-muted-dark mt-2 list-disc pl-4 text-xs">
                <li v-for="(reason, index) in campaign.explanation" :key="index">{{ reason }}</li>
            </ul>
        </div>

        <p v-if="!loading && campaigns.length === 0" class="text-wpx-muted-dark text-sm">
            Aucune campagne ne vous correspond pour le moment. Complétez votre profil intelligent et vos consentements
            pour en recevoir.
        </p>
    </div>
</template>
