<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
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
    possession: 'Possession — ce que la personne possède',
    usage: 'Usage — ce que la personne utilise',
    interest: 'Intérêt — ce qui intéresse la personne',
    project: 'Projet — ce que la personne prévoit de faire',
    situation: 'Situation — le contexte actuel de la personne',
    territory: 'Zone approximative',
};

function slugify(text: string, separator: string): string {
    return text
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, separator)
        .replace(new RegExp(`(^${separator}|${separator}$)`, 'g'), '');
}

const taxonomies = ref<Taxonomy[]>([]);
const categories = ref<string[]>([]);
const purposes = ref<ConsentPurpose[]>([]);
const busy = ref<string | null>(null);
const loading = ref(true);

const creatingTaxonomy = ref(false);
const advancedMode = ref(false);
const newTaxonomy = reactive({ code: '', category: 'interest', label: '' });
const autoTaxonomyCode = computed(() => `${newTaxonomy.category}.${slugify(newTaxonomy.label, '-')}`);

const creatingPurpose = ref(false);
const editingPurposeId = ref<string | null>(null);
const purposeForm = reactive({ code: '', name: '', description: '', requires_new_decision: false });

const groupedTaxonomies = computed(() =>
    categories.value
        .map((category) => ({
            category,
            label: CATEGORY_LABELS[category] ?? category,
            items: taxonomies.value.filter((t) => t.category === category),
        }))
        .filter((group) => group.items.length > 0),
);

function publishedVersion(purpose: ConsentPurpose): ConsentVersion | null {
    const published = purpose.versions
        .filter((v) => v.status === 'published')
        .sort((a, b) => b.version_number - a.version_number);
    return published[0] ?? null;
}

function draftVersion(purpose: ConsentPurpose): ConsentVersion | undefined {
    return purpose.versions.find((v) => v.status === 'draft');
}

async function loadTaxonomies(): Promise<void> {
    const { data } = await http.get('/admin/smartprofile/taxonomies');
    taxonomies.value = data.taxonomies;
    categories.value = data.categories;
}

async function loadPurposes(): Promise<void> {
    const { data } = await http.get('/admin/smartprofile/consent-purposes');
    purposes.value = data.purposes;
}

async function submitTaxonomy(): Promise<void> {
    const code = advancedMode.value && newTaxonomy.code ? newTaxonomy.code : autoTaxonomyCode.value;
    await http.post('/admin/smartprofile/taxonomies', { ...newTaxonomy, code });
    newTaxonomy.code = '';
    newTaxonomy.label = '';
    advancedMode.value = false;
    creatingTaxonomy.value = false;
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

function startCreatePurpose(): void {
    editingPurposeId.value = null;
    creatingPurpose.value = true;
    purposeForm.code = '';
    purposeForm.name = '';
    purposeForm.description = '';
    purposeForm.requires_new_decision = false;
}

function startEditPurpose(purpose: ConsentPurpose): void {
    creatingPurpose.value = false;
    editingPurposeId.value = purpose.id;
    const current = publishedVersion(purpose) ?? draftVersion(purpose);
    purposeForm.code = purpose.code;
    purposeForm.name = current?.name ?? '';
    purposeForm.description = current?.description ?? '';
    purposeForm.requires_new_decision = current?.requires_new_decision ?? false;
}

function cancelPurposeForm(): void {
    creatingPurpose.value = false;
    editingPurposeId.value = null;
}

async function submitNewPurpose(): Promise<void> {
    busy.value = 'new-purpose';
    try {
        const code = purposeForm.code || slugify(purposeForm.name, '_');
        const { data } = await http.post('/admin/smartprofile/consent-purposes', { ...purposeForm, code });
        await http.post(`/admin/smartprofile/consent-purposes/versions/${data.version.id}/publish`);
        cancelPurposeForm();
        await loadPurposes();
    } finally {
        busy.value = null;
    }
}

async function submitPurposeEdit(purpose: ConsentPurpose): Promise<void> {
    busy.value = purpose.id;
    try {
        const existingDraft = draftVersion(purpose);
        let versionId: string;
        if (existingDraft) {
            await http.patch(`/admin/smartprofile/consent-purposes/versions/${existingDraft.id}`, {
                name: purposeForm.name,
                description: purposeForm.description,
                requires_new_decision: purposeForm.requires_new_decision,
            });
            versionId = existingDraft.id;
        } else {
            const { data } = await http.post('/admin/smartprofile/consent-purposes', {
                code: purpose.code,
                name: purposeForm.name,
                description: purposeForm.description,
                requires_new_decision: purposeForm.requires_new_decision,
            });
            versionId = data.version.id;
        }
        await http.post(`/admin/smartprofile/consent-purposes/versions/${versionId}/publish`);
        cancelPurposeForm();
        await loadPurposes();
    } finally {
        busy.value = null;
    }
}

onMounted(async () => {
    loading.value = true;
    try {
        await Promise.all([loadTaxonomies(), loadPurposes()]);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-5">
        <div class="border-wpx-blue-light/25 rounded-wpx-md flex gap-3 border bg-white p-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" class="mt-0.5 shrink-0">
                <circle cx="12" cy="12" r="9" stroke="#075CCF" stroke-width="1.6" />
                <path d="M12 8v.5M12 11v5" stroke="#075CCF" stroke-width="1.8" stroke-linecap="round" />
            </svg>
            <p class="text-wpx-text text-xs leading-relaxed">
                Ce sont les informations qu'un utilisateur peut cocher sur son profil (« Possède un smartphone », «
                Habite à Cocody »...) pour recevoir des publicités mieux ciblées. Une information « en attente » n'est
                visible par personne, y compris les annonceurs, jusqu'à son activation.
            </p>
        </div>

        <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>

        <template v-else>
            <div class="flex items-center justify-between">
                <p class="text-wpx-text text-[15px] font-bold">Informations disponibles</p>
                <button
                    type="button"
                    class="rounded-wpx-sm bg-wpx-navy-950 px-4.5 py-2.5 text-[13px] font-bold text-white"
                    @click="creatingTaxonomy = !creatingTaxonomy"
                >
                    + Nouvelle information
                </button>
            </div>

            <form
                v-if="creatingTaxonomy"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface flex flex-col gap-3 border p-4"
                @submit.prevent="submitTaxonomy"
            >
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span class="font-semibold">Libellé affiché à l'utilisateur</span>
                    <input
                        v-model="newTaxonomy.label"
                        placeholder="Ex. Intéressé par la mode"
                        class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                        required
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span class="font-semibold">Catégorie</span>
                    <select
                        v-model="newTaxonomy.category"
                        class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                    >
                        <option v-for="category in categories" :key="category" :value="category">
                            {{ CATEGORY_LABELS[category] ?? category }}
                        </option>
                    </select>
                </label>
                <button
                    type="button"
                    class="text-wpx-blue-light self-start text-xs font-semibold"
                    @click="advancedMode = !advancedMode"
                >
                    {{ advancedMode ? 'Masquer le mode avancé' : 'Mode avancé' }}
                </button>
                <label v-if="advancedMode" class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span class="font-semibold">Code technique (généré automatiquement si laissé vide)</span>
                    <input
                        v-model="newTaxonomy.code"
                        :placeholder="autoTaxonomyCode"
                        class="rounded-wpx-sm border-wpx-border w-56 border px-2.5 py-1.5 text-sm"
                    />
                </label>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="rounded-wpx-sm bg-wpx-blue-light px-4 py-2 text-xs font-bold text-white"
                    >
                        Ajouter
                    </button>
                    <button
                        type="button"
                        class="text-wpx-text-muted text-xs font-semibold"
                        @click="creatingTaxonomy = false"
                    >
                        Annuler
                    </button>
                </div>
            </form>

            <div class="flex flex-col gap-2.5">
                <div
                    v-for="group in groupedTaxonomies"
                    :key="group.category"
                    class="rounded-wpx-md shadow-wpx-card border-wpx-border bg-wpx-surface border p-2"
                >
                    <p class="text-wpx-blue-light px-3 pt-2 pb-1 text-[11px] font-extrabold tracking-wide uppercase">
                        {{ group.label }}
                    </p>
                    <div
                        v-for="taxonomy in group.items"
                        :key="taxonomy.id"
                        class="rounded-wpx-sm flex items-center gap-3 p-3"
                        :class="taxonomy.status !== 'active' ? 'bg-wpx-warning/10' : ''"
                    >
                        <div class="flex-1">
                            <p class="text-wpx-text text-[13px] font-bold">{{ taxonomy.label }}</p>
                            <p v-if="taxonomy.status !== 'active'" class="text-wpx-warning-light mt-0.5 text-[11px]">
                                En attente — pas encore visible des utilisateurs
                            </p>
                        </div>
                        <template v-if="taxonomy.status === 'active'">
                            <span
                                class="bg-wpx-success/10 text-wpx-success-light rounded-wpx-full px-3 py-1 text-[11px] font-bold"
                            >
                                Active
                            </span>
                            <button
                                type="button"
                                class="bg-wpx-success relative h-5.5 w-9.5 shrink-0 rounded-full disabled:opacity-50"
                                :disabled="busy === taxonomy.id"
                                title="Suspendre"
                                @click="suspendTaxonomy(taxonomy)"
                            >
                                <span class="absolute top-0.5 right-0.5 h-4.5 w-4.5 rounded-full bg-white" />
                            </button>
                        </template>
                        <button
                            v-else
                            type="button"
                            class="rounded-wpx-sm bg-wpx-blue-light px-4 py-2 text-xs font-bold whitespace-nowrap text-white disabled:opacity-50"
                            :disabled="busy === taxonomy.id"
                            @click="activateTaxonomy(taxonomy)"
                        >
                            Activer
                        </button>
                    </div>
                </div>
                <p v-if="groupedTaxonomies.length === 0" class="text-wpx-text-muted text-sm">
                    Aucune information de profil pour le moment.
                </p>
            </div>

            <div class="mt-2 flex items-center justify-between">
                <p class="text-wpx-text text-[15px] font-bold">Autorisations demandées aux utilisateurs</p>
                <button type="button" class="text-wpx-blue-light text-xs font-bold" @click="startCreatePurpose">
                    + Nouvelle autorisation
                </button>
            </div>

            <form
                v-if="creatingPurpose"
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface flex flex-col gap-3 border p-4"
                @submit.prevent="submitNewPurpose"
            >
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span class="font-semibold">Titre affiché</span>
                    <input
                        v-model="purposeForm.name"
                        class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                        required
                    />
                </label>
                <label class="text-wpx-text flex flex-col gap-1 text-xs">
                    <span class="font-semibold">Texte présenté à l'utilisateur</span>
                    <input
                        v-model="purposeForm.description"
                        class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                        required
                    />
                </label>
                <label class="text-wpx-text flex items-center gap-1.5 text-xs">
                    <input v-model="purposeForm.requires_new_decision" type="checkbox" />
                    <span>Exige une nouvelle décision des utilisateurs déjà d'accord</span>
                </label>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="rounded-wpx-sm bg-wpx-blue-light px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
                        :disabled="busy === 'new-purpose'"
                    >
                        Publier cette autorisation
                    </button>
                    <button type="button" class="text-wpx-text-muted text-xs font-semibold" @click="cancelPurposeForm">
                        Annuler
                    </button>
                </div>
            </form>

            <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border bg-wpx-surface overflow-hidden border">
                <div
                    v-for="purpose in purposes"
                    :key="purpose.id"
                    class="border-wpx-border/60 flex flex-col gap-3 border-b p-4 last:border-0"
                >
                    <div v-if="editingPurposeId !== purpose.id" class="flex items-center gap-3.5">
                        <div class="flex-1">
                            <p class="text-wpx-text text-[13px] font-bold">
                                {{ publishedVersion(purpose)?.name ?? purpose.code }}
                            </p>
                            <p
                                v-if="publishedVersion(purpose)"
                                class="text-wpx-text-muted mt-0.5 text-xs leading-relaxed"
                            >
                                « {{ publishedVersion(purpose)?.description }} »
                            </p>
                        </div>
                        <span
                            class="rounded-wpx-full px-3 py-1 text-[11px] font-bold whitespace-nowrap"
                            :class="
                                purpose.status === 'active'
                                    ? 'bg-wpx-success/10 text-wpx-success-light'
                                    : purpose.status === 'suspended'
                                      ? 'bg-wpx-danger/10 text-wpx-danger-light'
                                      : 'bg-wpx-pending/10 text-wpx-warning-light'
                            "
                        >
                            {{
                                purpose.status === 'active'
                                    ? 'En vigueur'
                                    : purpose.status === 'suspended'
                                      ? 'Suspendue'
                                      : 'Brouillon'
                            }}
                        </span>
                        <button
                            type="button"
                            class="text-wpx-blue-light text-xs font-bold whitespace-nowrap"
                            @click="startEditPurpose(purpose)"
                        >
                            Modifier le texte
                        </button>
                    </div>

                    <form v-else class="flex flex-col gap-3" @submit.prevent="submitPurposeEdit(purpose)">
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span class="font-semibold">Titre affiché</span>
                            <input
                                v-model="purposeForm.name"
                                class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                                required
                            />
                        </label>
                        <label class="text-wpx-text flex flex-col gap-1 text-xs">
                            <span class="font-semibold">Texte présenté à l'utilisateur</span>
                            <input
                                v-model="purposeForm.description"
                                class="rounded-wpx-sm border-wpx-border border px-2.5 py-1.5 text-sm"
                                required
                            />
                        </label>
                        <label class="text-wpx-text flex items-center gap-1.5 text-xs">
                            <input v-model="purposeForm.requires_new_decision" type="checkbox" />
                            <span>Exige une nouvelle décision des utilisateurs déjà d'accord</span>
                        </label>
                        <div class="flex gap-2">
                            <button
                                type="submit"
                                class="rounded-wpx-sm bg-wpx-blue-light px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
                                :disabled="busy === purpose.id"
                            >
                                Publier cette version
                            </button>
                            <button
                                type="button"
                                class="text-wpx-text-muted text-xs font-semibold"
                                @click="cancelPurposeForm"
                            >
                                Annuler
                            </button>
                        </div>
                    </form>
                </div>
                <p v-if="purposes.length === 0" class="text-wpx-text-muted p-4 text-sm">Aucune autorisation définie.</p>
            </div>
        </template>
    </div>
</template>
