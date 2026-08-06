<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface PriceVersion {
    id: string;
    status: string;
    currency: string;
    base_price_minor_per_event: number;
    image_multiplier: string;
    video_multiplier: string;
    catalog: { code: string };
}

const versions = ref<PriceVersion[]>([]);
const loading = ref(true);
const busy = ref<string | null>(null);

const draftForm = reactive({
    base_price_minor_per_event: 0,
    image_multiplier: 1,
    video_multiplier: 1,
});

const numberFormatter = new Intl.NumberFormat('fr-FR');

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/advertising/pricing');
        versions.value = data.price_versions;
    } finally {
        loading.value = false;
    }
}

async function saveDraft(version: PriceVersion): Promise<void> {
    busy.value = version.id;
    try {
        await http.patch(`/admin/advertising/pricing/${version.id}`, {
            base_price_minor_per_event: draftForm.base_price_minor_per_event,
            image_multiplier: draftForm.image_multiplier,
            video_multiplier: draftForm.video_multiplier,
        });
        await load();
    } finally {
        busy.value = null;
    }
}

async function publish(version: PriceVersion): Promise<void> {
    busy.value = version.id;
    try {
        await http.post(`/admin/advertising/pricing/${version.id}/publish`);
        await load();
    } finally {
        busy.value = null;
    }
}

function statusClasses(status: string): string {
    if (status === 'published') {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (status === 'suspended') {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(load);
</script>

<template>
    <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
        <h2 class="text-wpx-text mb-3 text-sm font-semibold">Catalogue de prix publicitaire</h2>
        <p class="text-wpx-text-muted mb-3 text-xs">
            docs/chantiers/P006-RAPPORT.md : aucun prix de base n'a été inventé — une version reste en brouillon à 0
            tant qu'un administrateur ne fixe pas le vrai montant et ne la publie pas. Seuls le format et la classe
            ciblée sont des axes de prix réels ; durée/territoire/rareté restent neutres (coefficient 1,0).
        </p>
        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
        <table v-else class="w-full text-sm">
            <thead>
                <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                    <th class="p-2">Catalogue</th>
                    <th class="p-2">Prix de base / événement</th>
                    <th class="p-2">Multiplicateur image</th>
                    <th class="p-2">Multiplicateur vidéo</th>
                    <th class="p-2">Statut</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="v in versions" :key="v.id" class="border-wpx-border text-wpx-text border-b">
                    <td class="p-2">{{ v.catalog.code }}</td>
                    <td class="p-2">
                        <input
                            v-if="v.status === 'draft'"
                            v-model.number="draftForm.base_price_minor_per_event"
                            type="number"
                            class="rounded-wpx-sm border-wpx-border w-28 border px-1 py-0.5 text-xs"
                        />
                        <span v-else>{{ numberFormatter.format(v.base_price_minor_per_event) }} {{ v.currency }}</span>
                    </td>
                    <td class="p-2">
                        <input
                            v-if="v.status === 'draft'"
                            v-model.number="draftForm.image_multiplier"
                            type="number"
                            step="0.01"
                            class="rounded-wpx-sm border-wpx-border w-20 border px-1 py-0.5 text-xs"
                        />
                        <span v-else>{{ v.image_multiplier }}</span>
                    </td>
                    <td class="p-2">
                        <input
                            v-if="v.status === 'draft'"
                            v-model.number="draftForm.video_multiplier"
                            type="number"
                            step="0.01"
                            class="rounded-wpx-sm border-wpx-border w-20 border px-1 py-0.5 text-xs"
                        />
                        <span v-else>{{ v.video_multiplier }}</span>
                    </td>
                    <td class="p-2">
                        <span class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold" :class="statusClasses(v.status)">
                            {{ v.status }}
                        </span>
                    </td>
                    <td class="p-2 text-right whitespace-nowrap">
                        <button
                            v-if="v.status === 'draft'"
                            class="text-wpx-blue-light mr-2 text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === v.id"
                            @click="saveDraft(v)"
                        >
                            Enregistrer
                        </button>
                        <button
                            v-if="v.status === 'draft'"
                            class="text-wpx-success-light text-xs hover:underline disabled:opacity-50"
                            :disabled="busy === v.id"
                            @click="publish(v)"
                        >
                            Publier
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
