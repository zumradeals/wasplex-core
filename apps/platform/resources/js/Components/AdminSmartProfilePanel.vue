<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface Taxonomy {
    id: string;
    code: string;
    category: string;
    label: string;
    status: string;
    freshness_days: number | null;
}

interface ConsentVersion {
    id: string;
    consent_purpose_id: string;
    version_number: number;
    name: string;
    description: string;
    requires_new_decision: boolean;
    status: string;
}

interface ConsentPurpose {
    id: string;
    code: string;
    status: string;
    versions: ConsentVersion[];
}

const CATEGORY_LABELS: Record<string, string> = {
    possession: 'Possession (ce que la personne possède)',
    usage: 'Usage (ce que la personne utilise)',
    interest: 'Intérêt (ce qui intéresse la personne)',
    project: 'Projet (ce que la personne prévoit de faire)',
    situation: 'Situation (le contexte actuel de la personne)',
    territory: 'Zone approximative',
};

const taxonomies = ref<Taxonomy[]>([]);
const categories = ref<string[]>([]);
const purposes = ref<ConsentPurpose[]>([]);
const busy = ref<string | null>(null);

const newTaxonomy = reactive({ code: '', category: 'interest', label: '' });
const newPurpose = reactive({ code: '', name: '', description: '', requires_new_decision: false });

async function loadTaxonomies(): Promise<void> {
    const { data } = await http.get('/admin/smartprofile/taxonomies');
    taxonomies.value = data.taxonomies;
    categories.value = data.categories;
}

async function loadPurposes(): Promise<void> {
    const { data } = await http.get('/admin/smartprofile/consent-purposes');
    purposes.value = data.purposes;
}

async function createTaxonomy(): Promise<void> {
    await http.post('/admin/smartprofile/taxonomies', newTaxonomy);
    newTaxonomy.code = '';
    newTaxonomy.label = '';
    await loadTaxonomies();
}

async function activateTaxonomy(taxonomy: Taxonomy): Promise<void> {
    busy.value = taxonomy.id;
    try {
        await http.post(`/admin/smartprofile/taxonomies/${taxonomy.id}/activate`);
        await loadTaxonomies();
    } finally {
        busy.value = null;
    }
}

async function suspendTaxonomy(taxonomy: Taxonomy): Promise<void> {
    busy.value = taxonomy.id;
    try {
        await http.post(`/admin/smartprofile/taxonomies/${taxonomy.id}/suspend`);
        await loadTaxonomies();
    } finally {
        busy.value = null;
    }
}

async function createPurposeVersion(): Promise<void> {
    await http.post('/admin/smartprofile/consent-purposes', newPurpose);
    newPurpose.code = '';
    newPurpose.name = '';
    newPurpose.description = '';
    newPurpose.requires_new_decision = false;
    await loadPurposes();
}

async function publishVersion(version: ConsentVersion): Promise<void> {
    busy.value = version.id;
    try {
        await http.post(`/admin/smartprofile/consent-purposes/versions/${version.id}/publish`);
        await loadPurposes();
    } finally {
        busy.value = null;
    }
}

function draftVersion(purpose: ConsentPurpose): ConsentVersion | undefined {
    return purpose.versions.find((v) => v.status === 'draft');
}

function statusClasses(status: string): string {
    if (status === 'active' || status === 'published') {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (status === 'suspended') {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }
    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

onMounted(() => {
    void loadTaxonomies();
    void loadPurposes();
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Informations de profil (SmartProfile)</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Chaque information appartient à une seule catégorie, jamais mélangée avec une autre (ex. « possède un
                deux-roues » n'est jamais confondu avec « intéressé par les deux-roues »). Une information en brouillon
                n'est visible par personne tant qu'elle n'est pas activée.
            </p>

            <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="createTaxonomy">
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Libellé affiché à l'utilisateur</span>
                    <input
                        v-model="newTaxonomy.label"
                        placeholder="Ex. Intéressé par la mode"
                        class="rounded-wpx-sm border-wpx-border w-56 border px-2 py-1"
                        required
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Catégorie</span>
                    <select v-model="newTaxonomy.category" class="rounded-wpx-sm border-wpx-border border px-2 py-1">
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ CATEGORY_LABELS[category] ?? category }}
                        </option>
                    </select>
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Code technique (unique)</span>
                    <input
                        v-model="newTaxonomy.code"
                        placeholder="interest.mode"
                        class="rounded-wpx-sm border-wpx-border w-40 border px-2 py-1"
                        required
                    />
                </label>
                <button
                    type="submit"
                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold"
                >
                    Ajouter (brouillon)
                </button>
            </form>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-wpx-text-muted border-wpx-border border-b text-left text-xs">
                        <th class="p-2">Libellé</th>
                        <th class="p-2">Catégorie</th>
                        <th class="p-2">Statut</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in taxonomies" :key="t.id" class="border-wpx-border text-wpx-text border-b">
                        <td class="p-2">{{ t.label }}</td>
                        <td class="text-wpx-text-muted p-2 text-xs">{{ CATEGORY_LABELS[t.category] ?? t.category }}</td>
                        <td class="p-2">
                            <span
                                class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                                :class="statusClasses(t.status)"
                            >
                                {{ t.status }}
                            </span>
                        </td>
                        <td class="p-2 text-right whitespace-nowrap">
                            <button
                                v-if="t.status !== 'active'"
                                class="text-wpx-success-light mr-2 text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === t.id"
                                @click="activateTaxonomy(t)"
                            >
                                Activer
                            </button>
                            <button
                                v-if="t.status === 'active'"
                                class="text-wpx-danger-light text-xs hover:underline disabled:opacity-50"
                                :disabled="busy === t.id"
                                @click="suspendTaxonomy(t)"
                            >
                                Suspendre
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-4">
            <h2 class="text-wpx-text mb-1 text-sm font-semibold">Finalités de consentement</h2>
            <p class="text-wpx-text-muted mb-3 text-xs">
                Le texte présenté à l'utilisateur est conservé pour preuve. Une nouvelle version « exige une nouvelle
                décision » force les utilisateurs déjà d'accord à redécider — à cocher seulement pour un changement
                important.
            </p>

            <form class="mb-4 flex flex-wrap items-end gap-3" @submit.prevent="createPurposeVersion">
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Code (existant ou nouveau)</span>
                    <input
                        v-model="newPurpose.code"
                        placeholder="advertising_personalization"
                        class="rounded-wpx-sm border-wpx-border w-56 border px-2 py-1"
                        required
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Titre affiché</span>
                    <input
                        v-model="newPurpose.name"
                        class="rounded-wpx-sm border-wpx-border w-48 border px-2 py-1"
                        required
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span>Explication en langage clair</span>
                    <input
                        v-model="newPurpose.description"
                        class="rounded-wpx-sm border-wpx-border w-72 border px-2 py-1"
                        required
                    />
                </label>
                <label class="text-wpx-text flex items-center gap-1 text-xs">
                    <input v-model="newPurpose.requires_new_decision" type="checkbox" />
                    <span>Exige une nouvelle décision</span>
                </label>
                <button
                    type="submit"
                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-3 py-1.5 text-sm font-semibold"
                >
                    Créer une version (brouillon)
                </button>
            </form>

            <div
                v-for="purpose in purposes"
                :key="purpose.id"
                class="border-wpx-border mb-3 border-b pb-3 last:border-0"
            >
                <p class="text-wpx-text text-sm font-semibold">
                    {{ purpose.code }}
                    <span
                        class="rounded-wpx-sm ml-2 px-2 py-0.5 text-xs font-semibold"
                        :class="statusClasses(purpose.status)"
                    >
                        {{ purpose.status }}
                    </span>
                </p>
                <div v-for="version in purpose.versions" :key="version.id" class="text-wpx-text-muted mt-1 text-xs">
                    v{{ version.version_number }} — {{ version.name }} —
                    <span :class="statusClasses(version.status)" class="rounded-wpx-sm px-1.5 py-0.5">{{
                        version.status
                    }}</span>
                    <button
                        v-if="version.status === 'draft'"
                        class="text-wpx-success-light ml-2 hover:underline disabled:opacity-50"
                        :disabled="busy === version.id"
                        @click="publishVersion(version)"
                    >
                        Publier
                    </button>
                </div>
                <p v-if="!draftVersion(purpose)" class="text-wpx-text-muted mt-1 text-[11px] italic">
                    Aucune version en brouillon.
                </p>
            </div>
        </div>
    </div>
</template>
