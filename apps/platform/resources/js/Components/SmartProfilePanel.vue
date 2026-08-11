<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

type InputType = 'boolean' | 'single_choice' | 'multi_choice';

interface TaxonomyEntry {
    id: string;
    code: string;
    label: string;
    facet: string;
    input_type: InputType;
    declared: boolean;
    answer: boolean | null;
    declared_at: string | null;
}

interface ProfileStep {
    key: string;
    category: string;
    label: string;
    inputType: InputType | 'country';
    entries: TaxonomyEntry[];
}

const CATEGORY_META: Record<string, { title: string; icon: string }> = {
    demographic: { title: 'À propos de vous', icon: '👤' },
    possession: { title: 'Vos équipements', icon: '📦' },
    usage: { title: 'Vos habitudes', icon: '🧭' },
    interest: { title: 'Vos centres d’intérêt', icon: '✨' },
    project: { title: 'Vos projets', icon: '🎯' },
    situation: { title: 'Votre situation', icon: '🪪' },
    territory: { title: 'Votre zone', icon: '📍' },
};

const FACET_LABELS: Record<string, string> = {
    'demographic.gender': 'Quel est votre genre ?',
    'demographic.age_band': 'Quelle est votre tranche d’âge ?',
    interest: 'Qu’est-ce qui vous intéresse ?',
    project: 'Quels sont vos projets ?',
    situation: 'Quelle est votre situation actuelle ?',
    'territory.approximate_area': 'Quelle est votre zone approximative ?',
};

const ISO2_CODES =
    `AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW`.split(
        ' ',
    );

const regionNames = new Intl.DisplayNames(['fr'], { type: 'region' });
const countryOptions = ISO2_CODES.map((code) => ({ code, name: regionNames.of(code) ?? code })).sort((a, b) =>
    a.name.localeCompare(b.name, 'fr'),
);

const categories = ref<Record<string, TaxonomyEntry[]>>({});
const countryCode = ref<string>('');
const loading = ref(true);
const busy = ref<string | null>(null);
const currentIndex = ref(0);
const error = ref<string | null>(null);

const visibleEntries = computed(() =>
    Object.values(categories.value)
        .flat()
        .filter((entry) => {
            if (!entry.code.startsWith('territory.')) return true;
            const taxonomyCountry = entry.code.split('.')[1]?.toUpperCase();
            return !countryCode.value || taxonomyCountry === countryCode.value;
        }),
);

const taxonomySteps = computed<ProfileStep[]>(() => {
    const grouped = new Map<string, ProfileStep>();

    for (const entry of visibleEntries.value) {
        const key = entry.facet || entry.code;
        if (!grouped.has(key)) {
            grouped.set(key, {
                key,
                category: entry.code.split('.')[0] ?? 'demographic',
                label: FACET_LABELS[key] ?? questionFor(entry),
                inputType: entry.input_type,
                entries: [],
            });
        }
        grouped.get(key)?.entries.push(entry);
    }

    const categoryOrder = Object.keys(CATEGORY_META);

    return [...grouped.values()].sort((a, b) => {
        const categoryDiff = categoryOrder.indexOf(a.category) - categoryOrder.indexOf(b.category);
        if (categoryDiff !== 0) return categoryDiff;
        return a.label.localeCompare(b.label, 'fr');
    });
});

const steps = computed<ProfileStep[]>(() => [
    {
        key: 'account.country',
        category: 'demographic',
        label: 'Dans quel pays résidez-vous ?',
        inputType: 'country',
        entries: [],
    },
    ...taxonomySteps.value,
]);

const currentStep = computed(() => steps.value[currentIndex.value] ?? null);

function stepAnswered(step: ProfileStep): boolean {
    if (step.inputType === 'country') return countryCode.value.length === 2;
    if (step.inputType === 'single_choice') return step.entries.some((entry) => entry.answer === true);
    if (step.inputType === 'boolean') return step.entries.some((entry) => entry.answer !== null);
    return step.entries.some((entry) => entry.answer === true);
}

const answeredCount = computed(() => steps.value.filter(stepAnswered).length);
const totalCount = computed(() => steps.value.length);
const percent = computed(() =>
    totalCount.value === 0 ? 0 : Math.round((answeredCount.value / totalCount.value) * 100),
);
const nextSuggestions = computed(() =>
    steps.value
        .filter((step) => !stepAnswered(step))
        .slice(0, 2)
        .map((step) => step.label),
);

defineExpose({ percent, nextSuggestions });

function questionFor(entry: TaxonomyEntry): string {
    if (entry.label.startsWith('Possède ')) return `Possédez-vous ${entry.label.slice(8).toLowerCase()} ?`;
    if (entry.label.startsWith('Utilise ')) return `Utilisez-vous ${entry.label.slice(8).toLowerCase()} ?`;
    return entry.label;
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/me/smart-profile');
        categories.value = data.categories ?? {};
        countryCode.value = (data.country_code ?? '').toUpperCase();
        currentIndex.value = Math.min(currentIndex.value, Math.max(steps.value.length - 1, 0));
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le profil intelligent est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

function move(offset: number): void {
    currentIndex.value = Math.min(Math.max(currentIndex.value + offset, 0), Math.max(steps.value.length - 1, 0));
}

async function saveCountry(): Promise<void> {
    if (countryCode.value.length !== 2) return;

    busy.value = 'account.country';
    error.value = null;
    try {
        await http.patch('/me/smart-profile/country', { country_code: countryCode.value });
        await load();
        move(1);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de mettre à jour le pays.';
    } finally {
        busy.value = null;
    }
}

async function setAnswer(entry: TaxonomyEntry, value: boolean, advance = false): Promise<void> {
    busy.value = entry.id;
    error.value = null;
    try {
        await http.post(`/me/smart-profile/${entry.id}`, { answer: value });
        await load();
        if (advance) move(1);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de mettre à jour cette information.';
    } finally {
        busy.value = null;
    }
}

async function toggleMulti(entry: TaxonomyEntry): Promise<void> {
    busy.value = entry.id;
    error.value = null;
    try {
        if (entry.answer === true) {
            await http.delete(`/me/smart-profile/${entry.id}`);
        } else {
            await http.post(`/me/smart-profile/${entry.id}`, { answer: true });
        }
        await load();
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible de mettre à jour cette information.';
    } finally {
        busy.value = null;
    }
}

async function clearStep(step: ProfileStep): Promise<void> {
    const declared = step.entries.filter((entry) => entry.answer !== null);
    if (declared.length === 0) {
        move(1);
        return;
    }

    busy.value = step.key;
    error.value = null;
    try {
        await Promise.all(declared.map((entry) => http.delete(`/me/smart-profile/${entry.id}`)));
        await load();
        move(1);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Impossible d’effacer cette réponse.';
    } finally {
        busy.value = null;
    }
}

void load();
</script>

<template>
    <div class="rounded-wpx-xl border-wpx-border-dark bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border">
        <div class="from-wpx-navy-750 to-wpx-navy-850 bg-gradient-to-br p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-wpx-white-soft text-sm font-bold">Profil intelligent</p>
                    <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                        Quelques choix simples pour recevoir des campagnes plus pertinentes. Tout reste facultatif et
                        modifiable.
                    </p>
                </div>
                <span class="bg-wpx-blue/15 text-wpx-cyan rounded-full px-2.5 py-1 text-xs font-bold">
                    {{ answeredCount }}/{{ totalCount }}
                </span>
            </div>
            <div v-if="!loading" class="bg-wpx-navy-950/60 mt-3 h-1.5 overflow-hidden rounded-full">
                <div
                    class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                    :style="{ width: `${percent}%` }"
                />
            </div>
        </div>

        <div class="p-4">
            <p v-if="loading" class="text-wpx-muted-dark py-6 text-center text-sm">Chargement…</p>
            <p v-else-if="error && !currentStep" class="text-wpx-danger-light py-4 text-sm">{{ error }}</p>

            <div v-else-if="currentStep" :key="currentStep.key" class="min-h-52">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-wpx-cyan text-xs font-bold">
                        {{ CATEGORY_META[currentStep.category]?.icon ?? '•' }}
                        {{ CATEGORY_META[currentStep.category]?.title ?? 'Votre profil' }}
                    </span>
                    <span class="text-wpx-muted-dark text-[11px]">{{ currentIndex + 1 }} / {{ totalCount }}</span>
                </div>

                <h3 class="text-wpx-white-soft mt-3 text-base leading-snug font-bold">{{ currentStep.label }}</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Votre réponse peut être mise à jour plus tard et n’est jamais montrée directement à l’annonceur.
                </p>

                <div v-if="currentStep.inputType === 'country'" class="mt-4">
                    <select
                        v-model="countryCode"
                        class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-cyan w-full border px-3 py-3 text-sm focus:ring-1 focus:outline-none"
                        :disabled="busy === 'account.country'"
                        @change="saveCountry"
                    >
                        <option value="" disabled>Choisir un pays</option>
                        <option v-for="country in countryOptions" :key="country.code" :value="country.code">
                            {{ country.name }} ({{ country.code }})
                        </option>
                    </select>
                </div>

                <div v-else-if="currentStep.inputType === 'single_choice'" class="mt-4 grid grid-cols-2 gap-2">
                    <button
                        v-for="entry in currentStep.entries"
                        :key="entry.id"
                        type="button"
                        class="rounded-wpx-md border px-3 py-3 text-sm font-semibold transition disabled:opacity-50"
                        :class="
                            entry.answer === true
                                ? 'border-wpx-cyan bg-wpx-cyan/15 text-wpx-cyan'
                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                        "
                        :disabled="busy !== null"
                        @click="setAnswer(entry, true, true)"
                    >
                        {{ entry.label }}
                    </button>
                </div>

                <div
                    v-else-if="currentStep.inputType === 'boolean' && currentStep.entries[0]"
                    class="mt-4 grid grid-cols-2 gap-2"
                >
                    <button
                        type="button"
                        class="rounded-wpx-md border px-3 py-3 text-sm font-bold transition disabled:opacity-50"
                        :class="
                            currentStep.entries[0].answer === true
                                ? 'border-wpx-cyan bg-wpx-cyan text-wpx-navy-950'
                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                        "
                        :disabled="busy !== null"
                        @click="setAnswer(currentStep.entries[0], true, true)"
                    >
                        Oui
                    </button>
                    <button
                        type="button"
                        class="rounded-wpx-md border px-3 py-3 text-sm font-bold transition disabled:opacity-50"
                        :class="
                            currentStep.entries[0].answer === false
                                ? 'border-wpx-gold bg-wpx-gold text-wpx-navy-950'
                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                        "
                        :disabled="busy !== null"
                        @click="setAnswer(currentStep.entries[0], false, true)"
                    >
                        Non
                    </button>
                </div>

                <div v-else-if="currentStep.inputType === 'multi_choice'" class="mt-4 flex flex-wrap gap-2">
                    <button
                        v-for="entry in currentStep.entries"
                        :key="entry.id"
                        type="button"
                        class="rounded-full border px-3 py-2 text-xs font-semibold transition disabled:opacity-50"
                        :class="
                            entry.answer === true
                                ? 'border-wpx-cyan bg-wpx-cyan/15 text-wpx-cyan'
                                : 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                        "
                        :disabled="busy !== null"
                        @click="toggleMulti(entry)"
                    >
                        <span v-if="entry.answer === true">✓ </span>{{ entry.label }}
                    </button>
                </div>

                <p v-if="error" class="text-wpx-danger-light mt-3 text-xs">{{ error }}</p>

                <button
                    v-if="currentStep.inputType !== 'country'"
                    type="button"
                    class="text-wpx-muted-dark mt-4 text-xs underline disabled:opacity-50"
                    :disabled="busy !== null"
                    @click="clearStep(currentStep)"
                >
                    {{ stepAnswered(currentStep) ? 'Effacer / ne pas préciser' : 'Je préfère ne pas répondre' }}
                </button>

                <div class="border-wpx-border-dark mt-5 flex items-center justify-between border-t pt-3">
                    <button
                        type="button"
                        class="text-wpx-muted-dark text-xs font-semibold disabled:opacity-30"
                        :disabled="currentIndex === 0 || busy !== null"
                        @click="move(-1)"
                    >
                        ← Précédent
                    </button>
                    <div class="flex items-center gap-1">
                        <span
                            v-for="(step, index) in steps"
                            :key="step.key"
                            class="h-1.5 rounded-full transition-all"
                            :class="[
                                index === currentIndex
                                    ? 'bg-wpx-cyan w-4'
                                    : stepAnswered(step)
                                      ? 'bg-wpx-success w-1.5'
                                      : 'bg-wpx-border-dark w-1.5',
                            ]"
                        />
                    </div>
                    <button
                        type="button"
                        class="text-wpx-cyan text-xs font-bold disabled:opacity-30"
                        :disabled="currentIndex >= totalCount - 1 || busy !== null"
                        @click="move(1)"
                    >
                        Suivant →
                    </button>
                </div>
            </div>

            <p v-if="!loading && totalCount === 0" class="text-wpx-muted-dark text-sm">
                Aucune information de profil disponible pour le moment.
            </p>
        </div>
    </div>
</template>
