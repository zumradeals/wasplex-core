<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const advertiserSpace = computed(() => page.props.auth.spaces.find((s) => s.space_type === 'advertiser') ?? null);

const ISO2_CODES =
    `AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW`.split(
        ' ',
    );
const regionNames = new Intl.DisplayNames(['fr'], { type: 'region' });
const countryOptions = ISO2_CODES.map((code) => ({ code, name: regionNames.of(code) ?? code })).sort((a, b) =>
    a.name.localeCompare(b.name, 'fr'),
);

const name = ref('');
const countryCode = ref('CI');
const error = ref<string | null>(null);
const submitting = ref(false);
const showForm = ref(false);
const countryLoaded = ref(false);

async function openForm(): Promise<void> {
    showForm.value = true;
    error.value = null;

    if (countryLoaded.value) return;

    try {
        const { data } = await http.get('/me/smart-profile');
        const currentCountry = String(data.country_code ?? '').toUpperCase();
        if (currentCountry.length === 2) countryCode.value = currentCountry;
    } finally {
        countryLoaded.value = true;
    }
}

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;

    try {
        const { data } = await http.post('/organizations', {
            name: name.value,
            type: 'advertiser',
            country_code: countryCode.value,
        });

        const { data: spacesData } = await http.get('/me/spaces');
        const createdSpace = (spacesData.spaces as AuthShared['spaces']).find((s) => s.organization_id === data.id);

        if (createdSpace) {
            await http.post(`/me/spaces/${createdSpace.user_space_id}/switch`);
        }

        router.visit('/studio');
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "La création de l'espace annonceur a échoué.";
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <a
        v-if="advertiserSpace"
        href="/studio"
        class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-lg shadow-wpx-card-dark flex items-center gap-3 border p-3.5"
    >
        <span class="bg-wpx-blue/16 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
                <rect x="4" y="8" width="16" height="12" rx="2" stroke="#4FA3FF" stroke-width="1.7" />
                <path d="M8 8V6a4 4 0 018 0v2" stroke="#4FA3FF" stroke-width="1.7" />
            </svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="text-wpx-white-soft block text-sm font-bold">Studio annonceur</span>
            <span class="text-wpx-muted-dark mt-0.5 block truncate text-[11px]">
                {{ advertiserSpace.organization_name }} · gérer mes campagnes
            </span>
        </span>
        <span class="text-wpx-blue text-xs font-bold">Ouvrir ›</span>
    </a>

    <button
        v-else
        type="button"
        class="border-wpx-border-dark from-wpx-navy-750 to-wpx-navy-850 rounded-wpx-lg shadow-wpx-card-dark flex w-full items-center gap-3 border bg-gradient-to-br p-3.5 text-left"
        @click="openForm"
    >
        <span class="bg-wpx-gold/14 rounded-wpx-sm flex h-10 w-10 shrink-0 items-center justify-center">
            <svg width="19" height="19" viewBox="0 0 24 24" fill="none">
                <path d="M4 8h16v11H4z" stroke="#F2C14E" stroke-width="1.7" stroke-linejoin="round" />
                <path d="M8 8V6h8v2M9 13h6" stroke="#F2C14E" stroke-width="1.7" stroke-linecap="round" />
            </svg>
        </span>
        <span class="min-w-0 flex-1">
            <span class="text-wpx-white-soft block text-sm font-bold">Vous avez une marque ou une entreprise ?</span>
            <span class="text-wpx-muted-dark mt-0.5 block text-[11px] leading-relaxed">
                Créez votre espace annonceur uniquement quand vous en avez besoin.
            </span>
        </span>
        <span class="text-wpx-gold text-xs font-bold">Créer ›</span>
    </button>

    <Teleport to="body">
        <div
            v-if="showForm"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 sm:items-center"
            @click.self="showForm = false"
        >
            <div
                class="bg-wpx-navy-950 border-wpx-border-dark max-h-[88vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-4 shadow-2xl sm:rounded-3xl"
            >
                <div class="bg-wpx-navy-950 sticky top-0 z-10 flex items-start justify-between gap-3 pb-4">
                    <div>
                        <p class="text-wpx-white-soft text-lg font-bold">Créer mon espace annonceur</p>
                        <p class="text-wpx-muted-dark mt-1 text-xs leading-relaxed">
                            Un espace séparé pour financer, créer et suivre vos campagnes.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                        @click="showForm = false"
                    >
                        ×
                    </button>
                </div>

                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="text-wpx-white-soft font-semibold">Nom de la marque ou de l'organisation</span>
                        <input
                            v-model="name"
                            type="text"
                            maxlength="180"
                            required
                            autocomplete="organization"
                            placeholder="Ex. DG Afrique"
                            class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3.5 py-3 focus:ring-2 focus:outline-none"
                        />
                    </label>

                    <label class="flex flex-col gap-1.5 text-sm">
                        <span class="text-wpx-white-soft font-semibold">Pays principal</span>
                        <select
                            v-model="countryCode"
                            required
                            class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3.5 py-3 focus:ring-2 focus:outline-none"
                        >
                            <option v-for="country in countryOptions" :key="country.code" :value="country.code">
                                {{ country.name }} ({{ country.code }})
                            </option>
                        </select>
                    </label>

                    <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md p-3 text-xs">
                        {{ error }}
                    </p>

                    <button
                        type="submit"
                        :disabled="submitting || !name.trim()"
                        class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 bg-gradient-to-br px-4 py-3 text-sm font-bold transition disabled:opacity-50"
                    >
                        {{ submitting ? 'Création…' : 'Créer mon espace annonceur' }}
                    </button>
                    <p class="text-wpx-muted-dark text-center text-[10px] leading-relaxed">
                        Votre compte membre reste séparé de votre activité annonceur.
                    </p>
                </form>
            </div>
        </div>
    </Teleport>
</template>
