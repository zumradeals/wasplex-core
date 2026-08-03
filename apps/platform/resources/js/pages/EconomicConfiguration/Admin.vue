<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import WasplexMark from '@/components/WasplexMark.vue';

type ClassCode = 'FREE' | 'PREMIUM' | 'GOLD' | 'PLATINUM';

type EconomicVersion = {
    id: string;
    version: number;
    publicName: string;
    weightBasisPoints: number;
    publishedAt: string | null;
};

type EconomicClass = {
    id: string;
    code: ClassCode;
    published: EconomicVersion | null;
};

const props = defineProps<{
    classes: EconomicClass[];
    flash: {
        success: string | null;
        simulation: unknown;
    };
}>();

const classOrder: ClassCode[] = ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM'];
const classDescriptions: Record<ClassCode, string> = {
    FREE: 'Utilisateurs de la formule gratuite',
    PREMIUM: 'Abonnés à la formule Premium',
    GOLD: 'Abonnés à la formule Gold',
    PLATINUM: 'Abonnés à la formule Platinum',
};

const publishedPercentage = (code: ClassCode): number => {
    const economicClass = props.classes.find((item) => item.code === code);

    return (economicClass?.published?.weightBasisPoints ?? 0) / 100;
};

const initialPercentages = (): Record<ClassCode, number> => ({
    FREE: publishedPercentage('FREE'),
    PREMIUM: publishedPercentage('PREMIUM'),
    GOLD: publishedPercentage('GOLD'),
    PLATINUM: publishedPercentage('PLATINUM'),
});

const form = useForm<{ percentages: Record<ClassCode, number> }>({
    percentages: initialPercentages(),
});

watch(
    () => props.classes,
    () => {
        if (form.processing) {
            return;
        }

        const values = initialPercentages();

        for (const code of classOrder) {
            form.percentages[code] = values[code];
        }

        form.defaults({ percentages: values });
    },
    { deep: true },
);

const orderedClasses = computed(() =>
    classOrder.map((code) => {
        const economicClass = props.classes.find((item) => item.code === code);

        return {
            code,
            id: economicClass?.id ?? code,
            publicName: economicClass?.published?.publicName ?? code,
            currentPercentage: publishedPercentage(code),
            version: economicClass?.published?.version ?? null,
        };
    }),
);

const total = computed(
    () =>
        Math.round(
            classOrder.reduce((sum, code) => sum + Number(form.percentages[code] || 0), 0) * 100,
        ) / 100,
);

const isValid = computed(() => Math.abs(total.value - 100) < 0.001);
const hasChanges = computed(() =>
    classOrder.some(
        (code) => Math.abs(Number(form.percentages[code]) - publishedPercentage(code)) >= 0.001,
    ),
);
const difference = computed(() => Math.round(Math.abs(100 - total.value) * 100) / 100);
const totalMessage = computed(() => {
    if (isValid.value) {
        return 'La répartition est prête à être appliquée.';
    }

    return total.value < 100
        ? `Il reste ${formatPercentage(difference.value)} à répartir.`
        : `Le total dépasse de ${formatPercentage(difference.value)}.`;
});

function formatPercentage(value: number): string {
    return `${Number(value || 0).toLocaleString('fr-FR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    })} %`;
}

const clampPercentage = (value: number): number => Math.min(100, Math.max(0, Number(value || 0)));

const reset = () => {
    const values = initialPercentages();

    for (const code of classOrder) {
        form.percentages[code] = values[code];
    }

    form.clearErrors();
};

const submit = () => {
    if (!isValid.value || !hasChanges.value || form.processing) {
        return;
    }

    if (!window.confirm('Appliquer cette nouvelle répartition de 100 % dans Wasplex ?')) {
        return;
    }

    form.post('/administration/economie/repartition', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Répartition économique" />

    <main class="min-h-screen bg-[#030a12] text-white">
        <section class="mx-auto max-w-[1280px] px-5 py-8 lg:px-10 lg:py-12">
            <header
                class="flex flex-wrap items-end justify-between gap-6 border-b border-white/8 pb-7"
            >
                <div>
                    <WasplexMark />
                    <p
                        class="mt-8 text-xs font-black tracking-[0.22em] text-wasplex-orange uppercase"
                    >
                        Administration Wasplex
                    </p>
                    <h1 class="mt-2 text-4xl font-black">Répartition des pourcentages</h1>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-white/45">
                        Indique simplement le pourcentage attribué à chaque formule. Wasplex
                        s’occupe automatiquement de toute la partie technique et conserve
                        l’historique de la décision.
                    </p>
                </div>

                <Link
                    href="/administration"
                    class="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold transition hover:bg-white/5"
                >
                    Retour à la console
                </Link>
            </header>

            <div
                v-if="flash.success"
                class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/[0.07] px-5 py-4 text-sm font-semibold text-emerald-200"
            >
                {{ flash.success }}
            </div>

            <section
                class="mt-8 overflow-hidden rounded-3xl border border-white/8 bg-white/[0.035]"
            >
                <div class="border-b border-white/8 p-6 lg:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div>
                            <p
                                class="text-xs font-black tracking-[0.18em] text-wasplex-cyan uppercase"
                            >
                                Configuration simple
                            </p>
                            <h2 class="mt-2 text-2xl font-black">
                                Le total doit toujours faire 100 %
                            </h2>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                                Tu peux saisir des nombres entiers ou des décimales, par exemple 35
                                %, 12,5 % ou 7,25 %.
                            </p>
                        </div>

                        <div
                            class="min-w-52 rounded-2xl border px-5 py-4 text-right"
                            :class="
                                isValid
                                    ? 'border-emerald-400/25 bg-emerald-400/[0.07]'
                                    : 'border-red-400/25 bg-red-400/[0.07]'
                            "
                        >
                            <p class="text-xs font-bold text-white/40">Total renseigné</p>
                            <p
                                class="mt-1 text-3xl font-black"
                                :class="isValid ? 'text-emerald-300' : 'text-red-300'"
                            >
                                {{ formatPercentage(total) }}
                            </p>
                            <p class="mt-1 text-xs text-white/40">{{ totalMessage }}</p>
                        </div>
                    </div>
                </div>

                <form class="p-6 lg:p-8" @submit.prevent="submit">
                    <div class="grid gap-5 md:grid-cols-2">
                        <article
                            v-for="economicClass in orderedClasses"
                            :key="economicClass.id"
                            class="rounded-2xl border border-white/8 bg-black/15 p-5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-xs font-black tracking-[0.14em] text-wasplex-cyan uppercase"
                                    >
                                        {{ economicClass.code }}
                                    </p>
                                    <h3 class="mt-2 text-xl font-black">
                                        {{ economicClass.publicName }}
                                    </h3>
                                    <p class="mt-1 text-sm text-white/35">
                                        {{ classDescriptions[economicClass.code] }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-white/7 px-3 py-1 text-xs font-bold text-white/50"
                                >
                                    Actuel : {{ formatPercentage(economicClass.currentPercentage) }}
                                </span>
                            </div>

                            <label class="mt-6 block">
                                <span class="text-sm font-bold">Nouveau pourcentage</span>
                                <span class="relative mt-2 block">
                                    <input
                                        v-model.number="form.percentages[economicClass.code]"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.01"
                                        inputmode="decimal"
                                        required
                                        class="w-full rounded-xl border border-white/10 bg-[#07111d] px-4 py-3 pr-12 text-2xl font-black transition outline-none focus:border-wasplex-cyan/55"
                                    />
                                    <span
                                        class="pointer-events-none absolute top-1/2 right-4 -translate-y-1/2 text-lg font-black text-white/35"
                                    >
                                        %
                                    </span>
                                </span>
                            </label>

                            <div class="mt-4 h-2 overflow-hidden rounded-full bg-white/7">
                                <div
                                    class="h-full rounded-full bg-wasplex-cyan transition-all duration-300"
                                    :style="{
                                        width: `${clampPercentage(form.percentages[economicClass.code])}%`,
                                    }"
                                />
                            </div>

                            <p
                                v-if="form.errors[`percentages.${economicClass.code}`]"
                                class="mt-3 text-xs font-semibold text-red-300"
                            >
                                {{ form.errors[`percentages.${economicClass.code}`] }}
                            </p>
                        </article>
                    </div>

                    <div
                        v-if="form.errors.percentages"
                        class="mt-5 rounded-2xl border border-red-400/20 bg-red-400/[0.06] p-4 text-sm font-semibold text-red-200"
                    >
                        {{ form.errors.percentages }}
                    </div>

                    <div
                        class="mt-7 flex flex-wrap items-center justify-between gap-5 border-t border-white/8 pt-7"
                    >
                        <div class="max-w-2xl">
                            <p class="font-bold text-white/70">
                                Aucun réglage technique à renseigner
                            </p>
                            <p class="mt-1 text-sm leading-6 text-white/35">
                                Les noms, quotas et paramètres internes déjà publiés restent
                                inchangés. Seuls les quatre pourcentages sont remplacés, ensemble et
                                de manière traçable.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <button
                                type="button"
                                class="rounded-xl border border-white/10 px-5 py-3 text-sm font-black text-white/65 disabled:opacity-35"
                                :disabled="form.processing || !hasChanges"
                                @click="reset"
                            >
                                Annuler les changements
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-wasplex-cyan px-6 py-3 text-sm font-black text-[#03111a] disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="!isValid || !hasChanges || form.processing"
                            >
                                {{ form.processing ? 'Application…' : 'Appliquer la répartition' }}
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <aside
                class="mt-6 rounded-2xl border border-wasplex-orange/15 bg-wasplex-orange/[0.045] p-5"
            >
                <p class="text-sm leading-6 text-white/45">
                    <span class="font-black text-wasplex-orange">Sécurité :</span>
                    la modification exige toujours la session fondatrice renforcée. L’ancienne
                    répartition reste conservée dans l’historique et aucune valeur n’est modifiée
                    silencieusement.
                </p>
            </aside>
        </section>
    </main>
</template>
