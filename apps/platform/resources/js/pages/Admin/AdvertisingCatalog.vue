<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Market = {
    code: string;
    name: string;
    currency: string;
    timezone: string;
    defaultLocale: string;
    supportedLocales: string[];
    default: boolean;
    status: string;
};

type Sector = {
    id: string;
    code: string;
    name: string;
    parentId: string | null;
    parentName: string | null;
    status: string;
    icon: string | null;
    sortOrder: number;
    allowedForTargeting: boolean;
    taxonomiesCount: number;
};

type Taxonomy = {
    id: string;
    code: string;
    label: string;
    sectorId: string | null;
    sectorName: string;
    signalKind: string;
    valueType: string;
    countryCodes: string[];
    status: string;
    allowedForTargeting: boolean;
    sensitive: boolean;
    userVisible: boolean;
    aiUsageMode: string;
    freshnessDays: number | null;
    question: string | null;
    options: Array<{ value: string; label: string }>;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    markets: Market[];
    sectors: Sector[];
    taxonomies: Taxonomy[];
    defaults: { market: string; locale: string; aiEnabled: boolean };
    flash: { success: string | null };
}>();

const sectorForm = useForm({
    code: '',
    name: '',
    description: '',
    icon: '',
    parent_id: '',
    sort_order: Math.max(10, ...props.sectors.map((sector) => sector.sortOrder + 10)),
    status: 'active',
});

const taxonomyForm = useForm({
    sector_id: props.sectors[0]?.id ?? '',
    code: '',
    label: '',
    signal_kind: 'interest',
    prompt: '',
    help_text: '',
    privacy_note:
        'Réponse facultative, modifiable, expirable et jamais transmise nominativement à un annonceur.',
    options_text: '',
    freshness_days: 180,
    country_codes: [] as string[],
    ai_usage_mode: 'proposal_with_confirmation',
});

const parsedOptions = computed(() =>
    taxonomyForm.options_text
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => {
            const [value, ...labelParts] = line.split('|');
            return {
                value: value?.trim() ?? '',
                label: labelParts.join('|').trim() || value?.trim() || '',
            };
        })
        .filter((option) => option.value && option.label),
);

const groupedTaxonomies = computed(() => {
    const groups = new Map<string, { sector: string; items: Taxonomy[] }>();

    for (const taxonomy of props.taxonomies) {
        const group = groups.get(taxonomy.sectorName) ?? {
            sector: taxonomy.sectorName,
            items: [],
        };
        group.items.push(taxonomy);
        groups.set(taxonomy.sectorName, group);
    }

    return [...groups.values()];
});

const createSector = () => {
    sectorForm.post('/administration/publicite/catalogue/secteurs', {
        preserveScroll: true,
        onSuccess: () => sectorForm.reset('code', 'name', 'description', 'icon', 'parent_id'),
    });
};

const createTaxonomy = () => {
    taxonomyForm
        .transform((data) => ({
            sector_id: data.sector_id,
            code: data.code,
            label: data.label,
            signal_kind: data.signal_kind,
            prompt: data.prompt,
            help_text: data.help_text,
            privacy_note: data.privacy_note,
            options: parsedOptions.value,
            freshness_days: data.freshness_days,
            country_codes: data.country_codes.length > 0 ? data.country_codes : null,
            ai_usage_mode: data.ai_usage_mode,
        }))
        .post('/administration/publicite/catalogue/taxonomies', {
            preserveScroll: true,
            onSuccess: () =>
                taxonomyForm.reset(
                    'code',
                    'label',
                    'prompt',
                    'help_text',
                    'options_text',
                    'country_codes',
                ),
        });
};

const signalLabel = (kind: string) =>
    ({
        interest: 'Intérêt',
        habit: 'Habitude',
        preference: 'Préférence',
        project: 'Projet',
        need: 'Besoin',
        status: 'Situation',
        territory: 'Territoire',
    })[kind] ?? kind;
</script>

<template>
    <Head title="Catalogue du Profil intelligent" />
    <AdminLayout :account="account" :active-space="activeSpace" :spaces="spaces" active="catalog">
        <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.22em] text-cyan-300 uppercase">
                    Profil intelligent · Référentiel international
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Secteurs, intérêts et questions
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-white/42">
                    Créez les secteurs, taxonomies et questions du Profil intelligent sans modifier
                    le code. Aucun annonceur ne peut inventer directement un critère de ciblage.
                </p>
            </div>
            <div class="rounded-2xl border border-wasplex-gold/15 bg-wasplex-gold/[0.05] px-5 py-4">
                <p class="text-xs font-black text-wasplex-gold">Marché proposé par défaut</p>
                <p class="mt-1 text-2xl font-black">
                    {{ markets.find((market) => market.default)?.name ?? defaults.market }}
                </p>
                <p class="mt-1 text-xs text-white/35">Le noyau reste international.</p>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-300/20 bg-emerald-300/[0.07] px-5 py-4 text-sm font-bold text-emerald-100"
        >
            {{ flash.success }}
        </div>

        <section class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-2xl border border-white/8 bg-white/[0.035] p-5">
                <p class="text-xs font-black text-white/35 uppercase">Marchés actifs</p>
                <p class="mt-3 text-3xl font-black">{{ markets.length }}</p>
            </article>
            <article class="rounded-2xl border border-cyan-300/15 bg-cyan-300/[0.04] p-5">
                <p class="text-xs font-black text-cyan-200/65 uppercase">Secteurs</p>
                <p class="mt-3 text-3xl font-black text-cyan-200">{{ sectors.length }}</p>
            </article>
            <article class="rounded-2xl border border-wasplex-gold/15 bg-wasplex-gold/[0.04] p-5">
                <p class="text-xs font-black text-wasplex-gold/65 uppercase">Taxonomies</p>
                <p class="mt-3 text-3xl font-black text-wasplex-gold">{{ taxonomies.length }}</p>
            </article>
            <article class="rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.04] p-5">
                <p class="text-xs font-black text-emerald-200/65 uppercase">IA de profil</p>
                <p class="mt-3 text-xl font-black text-emerald-200">
                    {{ defaults.aiEnabled ? 'Activée' : 'Désactivée' }}
                </p>
                <p class="mt-2 text-xs text-white/35">Toujours soumise à confirmation.</p>
            </article>
        </section>

        <section class="mt-7 grid gap-6 2xl:grid-cols-2">
            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-cyan-300 uppercase">
                    Nouveau secteur
                </p>
                <h2 class="mt-2 text-2xl font-black">Étendre le référentiel</h2>
                <p class="mt-2 text-sm leading-6 text-white/38">
                    Un secteur est un univers commercial général : automobile, immobilier,
                    formation, agriculture, services professionnels, etc.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="createSector">
                    <label class="block">
                        <span class="text-sm font-black">Code stable</span>
                        <input
                            v-model="sectorForm.code"
                            required
                            placeholder="agriculture"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 outline-none focus:border-cyan-300/55"
                        />
                        <span
                            v-if="sectorForm.errors.code"
                            class="mt-2 block text-xs text-rose-300"
                            >{{ sectorForm.errors.code }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Nom affiché</span>
                        <input
                            v-model="sectorForm.name"
                            required
                            placeholder="Agriculture"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 outline-none focus:border-cyan-300/55"
                        />
                        <span
                            v-if="sectorForm.errors.name"
                            class="mt-2 block text-xs text-rose-300"
                            >{{ sectorForm.errors.name }}</span
                        >
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black">Description</span>
                        <textarea
                            v-model="sectorForm.description"
                            rows="3"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 outline-none focus:border-cyan-300/55"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Secteur parent · facultatif</span>
                        <select
                            v-model="sectorForm.parent_id"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        >
                            <option value="">Aucun</option>
                            <option v-for="sector in sectors" :key="sector.id" :value="sector.id">
                                {{ sector.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Ordre</span>
                        <input
                            v-model.number="sectorForm.sort_order"
                            type="number"
                            min="0"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                    </label>
                    <div class="flex justify-end sm:col-span-2">
                        <button
                            type="submit"
                            :disabled="sectorForm.processing"
                            class="rounded-xl bg-cyan-300 px-6 py-3 text-sm font-black text-[#04111e] disabled:opacity-40"
                        >
                            {{ sectorForm.processing ? 'Création…' : 'Créer le secteur' }}
                        </button>
                    </div>
                </form>
            </article>

            <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                <p class="text-xs font-black tracking-widest text-wasplex-gold uppercase">
                    Nouveau centre d’intérêt
                </p>
                <h2 class="mt-2 text-2xl font-black">Publier une taxonomie et sa question</h2>
                <p class="mt-2 text-sm leading-6 text-white/38">
                    Chaque ligne d’option utilise le format <code>valeur|Libellé visible</code>.
                </p>

                <form class="mt-6 grid gap-4 sm:grid-cols-2" @submit.prevent="createTaxonomy">
                    <label class="block">
                        <span class="text-sm font-black">Secteur</span>
                        <select
                            v-model="taxonomyForm.sector_id"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        >
                            <option v-for="sector in sectors" :key="sector.id" :value="sector.id">
                                {{ sector.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Nature du signal</span>
                        <select
                            v-model="taxonomyForm.signal_kind"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        >
                            <option value="interest">Intérêt</option>
                            <option value="habit">Habitude</option>
                            <option value="preference">Préférence</option>
                            <option value="project">Projet</option>
                            <option value="need">Besoin actuel</option>
                            <option value="status">Situation déclarée</option>
                            <option value="territory">Territoire</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Code stable</span>
                        <input
                            v-model="taxonomyForm.code"
                            required
                            placeholder="agriculture.equipment.need"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                        <span
                            v-if="taxonomyForm.errors.code"
                            class="mt-2 block text-xs text-rose-300"
                            >{{ taxonomyForm.errors.code }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Libellé administratif</span>
                        <input
                            v-model="taxonomyForm.label"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black">Question utilisateur</span>
                        <textarea
                            v-model="taxonomyForm.prompt"
                            required
                            rows="2"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black">Explication utile</span>
                        <textarea
                            v-model="taxonomyForm.help_text"
                            required
                            rows="2"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-black">Options</span>
                        <textarea
                            v-model="taxonomyForm.options_text"
                            required
                            rows="6"
                            placeholder="owner|Je possède déjà ce produit&#10;purchase_project|Je souhaite l’acheter&#10;general_interest|Je m’informe seulement"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 font-mono text-sm"
                        />
                        <span class="mt-2 block text-xs text-white/30"
                            >{{ parsedOptions.length }} option(s) reconnue(s).</span
                        >
                        <span
                            v-if="taxonomyForm.errors.options"
                            class="mt-2 block text-xs text-rose-300"
                            >{{ taxonomyForm.errors.options }}</span
                        >
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Fraîcheur · jours</span>
                        <input
                            v-model.number="taxonomyForm.freshness_days"
                            type="number"
                            min="1"
                            max="1825"
                            required
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        />
                    </label>
                    <label class="block">
                        <span class="text-sm font-black">Assistance IA</span>
                        <select
                            v-model="taxonomyForm.ai_usage_mode"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3"
                        >
                            <option value="forbidden">Interdite</option>
                            <option value="assist_only">Assistance sans déduction</option>
                            <option value="proposal_with_confirmation">
                                Proposition avec confirmation
                            </option>
                        </select>
                    </label>
                    <fieldset class="sm:col-span-2">
                        <legend class="text-sm font-black">
                            Pays concernés · aucun choix = international
                        </legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <label
                                v-for="market in markets"
                                :key="market.code"
                                class="flex items-center gap-2 rounded-xl border border-white/8 bg-black/15 px-3 py-2 text-xs font-bold"
                            >
                                <input
                                    v-model="taxonomyForm.country_codes"
                                    type="checkbox"
                                    :value="market.code"
                                />
                                {{ market.name }}
                            </label>
                        </div>
                    </fieldset>
                    <div class="flex justify-end sm:col-span-2">
                        <button
                            type="submit"
                            :disabled="taxonomyForm.processing || parsedOptions.length < 2"
                            class="rounded-xl bg-wasplex-gold px-6 py-3 text-sm font-black text-[#171000] disabled:opacity-40"
                        >
                            {{
                                taxonomyForm.processing
                                    ? 'Publication…'
                                    : 'Publier dans le catalogue'
                            }}
                        </button>
                    </div>
                </form>
            </article>
        </section>

        <section class="mt-8">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                        Référentiel actif
                    </p>
                    <h2 class="mt-2 text-2xl font-black">Taxonomies par secteur</h2>
                </div>
                <p class="text-xs text-white/30">Aucune identité utilisateur n’est visible ici.</p>
            </div>

            <div class="mt-5 space-y-5">
                <article
                    v-for="group in groupedTaxonomies"
                    :key="group.sector"
                    class="rounded-[2rem] border border-white/8 bg-white/[0.03] p-5 sm:p-6"
                >
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-xl font-black">{{ group.sector }}</h3>
                        <span
                            class="rounded-full bg-white/7 px-3 py-1 text-xs font-black text-white/45"
                            >{{ group.items.length }}</span
                        >
                    </div>
                    <div class="mt-4 grid gap-3 xl:grid-cols-2">
                        <div
                            v-for="taxonomy in group.items"
                            :key="taxonomy.id"
                            class="rounded-2xl border border-white/7 bg-black/15 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black">{{ taxonomy.label }}</p>
                                    <p class="mt-1 font-mono text-[0.68rem] text-cyan-200/55">
                                        {{ taxonomy.code }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-white/7 px-2 py-1 text-[0.62rem] font-black text-white/45"
                                    >{{ signalLabel(taxonomy.signalKind) }}</span
                                >
                            </div>
                            <p class="mt-3 text-sm leading-6 text-white/40">
                                {{ taxonomy.question ?? 'Question non publiée' }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[0.62rem] font-black">
                                <span
                                    class="rounded-full bg-emerald-300/10 px-2 py-1 text-emerald-200"
                                    >{{ taxonomy.status }}</span
                                >
                                <span class="rounded-full bg-cyan-300/10 px-2 py-1 text-cyan-200"
                                    >{{ taxonomy.freshnessDays ?? '∞' }} jours</span
                                >
                                <span
                                    class="rounded-full bg-wasplex-gold/10 px-2 py-1 text-wasplex-gold"
                                    >{{
                                        taxonomy.countryCodes.length
                                            ? taxonomy.countryCodes.join(', ')
                                            : 'International'
                                    }}</span
                                >
                                <span class="rounded-full bg-white/7 px-2 py-1 text-white/45"
                                    >IA : {{ taxonomy.aiUsageMode }}</span
                                >
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </AdminLayout>
</template>
