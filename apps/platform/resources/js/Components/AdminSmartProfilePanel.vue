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
    demographic: 'Âge & genre',
    possession: 'Ce que la personne possède',
    usage: 'Ce que la personne utilise',
    interest: 'Centres d’intérêt',
    project: 'Projets',
    situation: 'Situation actuelle',
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
            items: taxonomies.value.filter((taxonomy) => taxonomy.category === category),
        }))
        .filter((group) => group.items.length > 0),
);

const activeTaxonomyCount = computed(() => taxonomies.value.filter((taxonomy) => taxonomy.status === 'active').length);
const publishedPurposeCount = computed(
    () => purposes.value.filter((purpose) => purpose.versions.some((version) => version.status === 'published')).length,
);

function publishedVersion(purpose: ConsentPurpose): ConsentVersion | null {
    const published = purpose.versions
        .filter((version) => version.status === 'published')
        .sort((a, b) => b.version_number - a.version_number);
    return published[0] ?? null;
}

function draftVersion(purpose: ConsentPurpose): ConsentVersion | undefined {
    return purpose.versions.find((version) => version.status === 'draft');
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
    <div class="text-wpx-white-soft mx-auto flex max-w-5xl flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">
                        Profil intelligent
                    </p>
                    <h2 class="mt-1 text-xl font-extrabold">
                        Choisir les informations volontaires que Wasplex peut proposer.
                    </h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-3xl text-sm">
                        Les membres choisissent eux-mêmes ce qu’ils partagent. Une information inactive n’est proposée à
                        personne et un annonceur ne voit jamais l’identité d’un membre.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan rounded-wpx-md text-wpx-navy-950 bg-gradient-to-br px-4 py-2.5 text-xs font-extrabold"
                    @click="creatingTaxonomy = !creatingTaxonomy"
                >
                    {{ creatingTaxonomy ? 'Fermer' : '+ Nouvelle information' }}
                </button>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <span class="bg-wpx-success/15 text-wpx-success rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ activeTaxonomyCount }} informations actives
                </span>
                <span class="bg-wpx-blue/15 text-wpx-blue rounded-wpx-full px-3 py-1 text-xs font-extrabold">
                    {{ publishedPurposeCount }} autorisations publiées
                </span>
            </div>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <form
                v-if="creatingTaxonomy"
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5"
                @submit.prevent="submitTaxonomy"
            >
                <h3 class="text-base font-extrabold">Ajouter une information</h3>
                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Question ou choix affiché au membre</span>
                        <input
                            v-model="newTaxonomy.label"
                            placeholder="Ex. Intéressé par la mode"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2.5 text-sm"
                            required
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Catégorie</span>
                        <select
                            v-model="newTaxonomy.category"
                            class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2.5 text-sm"
                        >
                            <option v-for="category in categories" :key="category" :value="category">
                                {{ CATEGORY_LABELS[category] ?? category }}
                            </option>
                        </select>
                    </label>
                </div>
                <button
                    type="button"
                    class="text-wpx-cyan mt-3 text-xs font-bold"
                    @click="advancedMode = !advancedMode"
                >
                    {{ advancedMode ? 'Masquer le code technique' : 'Options avancées' }}
                </button>
                <label v-if="advancedMode" class="mt-3 flex max-w-md flex-col gap-1.5 text-xs">
                    <span class="text-wpx-muted-dark">Code technique</span>
                    <input
                        v-model="newTaxonomy.code"
                        :placeholder="autoTaxonomyCode"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-white-soft border px-3 py-2 text-sm"
                    />
                </label>
                <div class="mt-4 flex gap-2">
                    <button
                        type="submit"
                        class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-4 py-2 text-xs font-extrabold"
                    >
                        Ajouter l’information
                    </button>
                    <button
                        type="button"
                        class="text-wpx-muted-dark px-3 text-xs font-bold"
                        @click="creatingTaxonomy = false"
                    >
                        Annuler
                    </button>
                </div>
            </form>

            <section>
                <div class="mb-3">
                    <h3 class="text-base font-extrabold">Informations proposées aux membres</h3>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Ouvre seulement la catégorie que tu veux gérer.</p>
                </div>
                <div class="flex flex-col gap-3">
                    <details
                        v-for="group in groupedTaxonomies"
                        :key="group.category"
                        class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border"
                    >
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
                            <span
                                class="bg-wpx-blue/12 text-wpx-blue rounded-wpx-md flex h-9 w-9 shrink-0 items-center justify-center"
                                >◎</span
                            >
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-extrabold">{{ group.label }}</span>
                                <span class="text-wpx-muted-dark mt-0.5 block text-xs">
                                    {{ group.items.filter((item) => item.status === 'active').length }}/{{
                                        group.items.length
                                    }}
                                    actives
                                </span>
                            </span>
                            <span class="text-wpx-muted-dark text-xs">Ouvrir</span>
                        </summary>
                        <div class="border-wpx-border-dark divide-wpx-border-dark divide-y border-t px-5">
                            <div
                                v-for="taxonomy in group.items"
                                :key="taxonomy.id"
                                class="flex items-center gap-3 py-3.5"
                            >
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold">{{ taxonomy.label }}</p>
                                    <details class="mt-1">
                                        <summary class="text-wpx-muted-dark cursor-pointer text-[10px]">
                                            Détail technique
                                        </summary>
                                        <p class="text-wpx-muted-dark mt-1 font-mono text-[10px] break-all">
                                            {{ taxonomy.code }}
                                        </p>
                                    </details>
                                </div>
                                <span
                                    class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                    :class="
                                        taxonomy.status === 'active'
                                            ? 'bg-wpx-success/15 text-wpx-success'
                                            : 'bg-wpx-warning/15 text-wpx-gold'
                                    "
                                >
                                    {{ taxonomy.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                                <button
                                    v-if="taxonomy.status === 'active'"
                                    type="button"
                                    class="border-wpx-danger/40 text-wpx-danger rounded-wpx-md border px-3 py-1.5 text-[11px] font-bold disabled:opacity-50"
                                    :disabled="busy === taxonomy.id"
                                    @click="suspendTaxonomy(taxonomy)"
                                >
                                    Suspendre
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-3 py-1.5 text-[11px] font-extrabold disabled:opacity-50"
                                    :disabled="busy === taxonomy.id"
                                    @click="activateTaxonomy(taxonomy)"
                                >
                                    Activer
                                </button>
                            </div>
                        </div>
                    </details>
                    <p v-if="groupedTaxonomies.length === 0" class="text-wpx-muted-dark text-sm">
                        Aucune information de profil.
                    </p>
                </div>
            </section>

            <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-extrabold">Autorisations demandées aux utilisateurs</h3>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Les textes que le membre accepte ou refuse explicitement.
                        </p>
                    </div>
                    <button type="button" class="text-wpx-cyan text-xs font-extrabold" @click="startCreatePurpose">
                        + Nouvelle autorisation
                    </button>
                </div>

                <form
                    v-if="creatingPurpose"
                    class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 mt-4 border p-4"
                    @submit.prevent="submitNewPurpose"
                >
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <label class="flex flex-col gap-1 text-xs">
                            <span class="text-wpx-muted-dark">Titre</span>
                            <input
                                v-model="purposeForm.name"
                                required
                                class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                            />
                        </label>
                        <label class="flex flex-col gap-1 text-xs md:col-span-2">
                            <span class="text-wpx-muted-dark">Explication affichée au membre</span>
                            <textarea
                                v-model="purposeForm.description"
                                required
                                rows="3"
                                class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                            />
                        </label>
                    </div>
                    <label class="text-wpx-muted-dark mt-3 flex items-center gap-2 text-xs">
                        <input v-model="purposeForm.requires_new_decision" type="checkbox" class="accent-wpx-cyan" />
                        Demander une nouvelle décision aux membres déjà inscrits
                    </label>
                    <div class="mt-4 flex gap-2">
                        <button
                            type="submit"
                            :disabled="busy === 'new-purpose'"
                            class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-4 py-2 text-xs font-extrabold disabled:opacity-50"
                        >
                            Publier cette autorisation
                        </button>
                        <button
                            type="button"
                            class="text-wpx-muted-dark px-3 text-xs font-bold"
                            @click="cancelPurposeForm"
                        >
                            Annuler
                        </button>
                    </div>
                </form>

                <div class="mt-4 flex flex-col gap-3">
                    <article
                        v-for="purpose in purposes"
                        :key="purpose.id"
                        class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4"
                    >
                        <template v-if="editingPurposeId !== purpose.id">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-extrabold">
                                            {{
                                                publishedVersion(purpose)?.name ??
                                                draftVersion(purpose)?.name ??
                                                purpose.code
                                            }}
                                        </p>
                                        <span
                                            class="rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                            :class="
                                                publishedVersion(purpose)
                                                    ? 'bg-wpx-success/15 text-wpx-success'
                                                    : 'bg-wpx-warning/15 text-wpx-gold'
                                            "
                                        >
                                            {{ publishedVersion(purpose) ? 'En vigueur' : 'Brouillon' }}
                                        </span>
                                    </div>
                                    <p class="text-wpx-muted-dark mt-1 text-xs">
                                        {{
                                            publishedVersion(purpose)?.description ??
                                            draftVersion(purpose)?.description ??
                                            'Aucune description.'
                                        }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="text-wpx-cyan text-xs font-extrabold"
                                    @click="startEditPurpose(purpose)"
                                >
                                    Modifier le texte
                                </button>
                            </div>
                        </template>

                        <form v-else class="flex flex-col gap-3" @submit.prevent="submitPurposeEdit(purpose)">
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-muted-dark">Titre</span>
                                <input
                                    v-model="purposeForm.name"
                                    required
                                    class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs">
                                <span class="text-wpx-muted-dark">Explication</span>
                                <textarea
                                    v-model="purposeForm.description"
                                    required
                                    rows="3"
                                    class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-850 border px-3 py-2 text-sm"
                                />
                            </label>
                            <label class="text-wpx-muted-dark flex items-center gap-2 text-xs">
                                <input
                                    v-model="purposeForm.requires_new_decision"
                                    type="checkbox"
                                    class="accent-wpx-cyan"
                                />
                                Demander une nouvelle décision aux membres
                            </label>
                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="busy === purpose.id"
                                    class="bg-wpx-success rounded-wpx-md text-wpx-navy-950 px-4 py-2 text-xs font-extrabold disabled:opacity-50"
                                >
                                    Publier la nouvelle version
                                </button>
                                <button
                                    type="button"
                                    class="text-wpx-muted-dark px-3 text-xs font-bold"
                                    @click="cancelPurposeForm"
                                >
                                    Annuler
                                </button>
                            </div>
                        </form>
                    </article>
                    <p v-if="purposes.length === 0" class="text-wpx-muted-dark text-sm">
                        Aucune autorisation configurée.
                    </p>
                </div>
            </section>
        </template>
    </div>
</template>
