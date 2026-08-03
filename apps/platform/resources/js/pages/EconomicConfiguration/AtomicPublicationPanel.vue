<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type EconomicVersion = {
    id: string;
    version: number;
    state: 'draft' | 'approved' | 'published' | 'suspended';
    publicName: string;
    weightBasisPoints: number;
};

type EconomicClass = {
    id: string;
    code: string;
    published: EconomicVersion | null;
    versions: EconomicVersion[];
};

const props = defineProps<{
    classes: EconomicClass[];
}>();

const open = ref(false);
const selectedByClass = ref<Record<string, string>>({});
const form = useForm<{ version_ids: string[] }>({ version_ids: [] });

const approvedByClass = computed(() =>
    props.classes
        .map((economicClass) => ({
            economicClass,
            approved: economicClass.versions.filter((version) => version.state === 'approved'),
        }))
        .filter((entry) => entry.approved.length > 0),
);

const selectedVersions = computed(() =>
    approvedByClass.value.flatMap(({ economicClass, approved }) => {
        const selectedId = selectedByClass.value[economicClass.id];
        const selected = approved.find((version) => version.id === selectedId);

        return selected ? [{ economicClass, version: selected }] : [];
    }),
);

const projectedTotal = computed(() =>
    props.classes.reduce((total, economicClass) => {
        const selected = selectedVersions.value.find(
            (entry) => entry.economicClass.id === economicClass.id,
        );

        return (
            total +
            (selected?.version.weightBasisPoints ?? economicClass.published?.weightBasisPoints ?? 0)
        );
    }, 0),
);

const canPublish = computed(
    () => selectedVersions.value.length > 0 && projectedTotal.value === 10000 && !form.processing,
);

const percentage = (basisPoints: number) => `${(basisPoints / 100).toLocaleString('fr-FR')} %`;

const submit = () => {
    if (!canPublish.value) {
        return;
    }

    if (!window.confirm('Publier cette décision économique atomique dans Wasplex ?')) {
        return;
    }

    form.version_ids = selectedVersions.value.map(({ version }) => version.id);
    form.post('/administration/economie/versions/publier-ensemble', {
        preserveScroll: true,
        onSuccess: () => {
            selectedByClass.value = {};
            open.value = false;
        },
    });
};
</script>

<template>
    <div class="fixed right-5 bottom-5 z-40 hidden w-[28rem] max-w-[calc(100vw-2.5rem)] md:block">
        <button
            v-if="!open"
            type="button"
            class="ml-auto flex items-center gap-3 rounded-2xl border border-wasplex-gold/25 bg-[#0b1520]/95 px-5 py-4 text-left shadow-2xl backdrop-blur transition hover:border-wasplex-gold/50"
            @click="open = true"
        >
            <span
                class="grid size-10 place-items-center rounded-xl bg-wasplex-gold/10 text-wasplex-gold"
            >
                ∑
            </span>
            <span>
                <span class="block text-sm font-black text-white">Publication coordonnée</span>
                <span class="mt-1 block text-xs text-white/40">
                    {{ approvedByClass.length }} classe(s) avec version approuvée
                </span>
            </span>
        </button>

        <section
            v-else
            class="overflow-hidden rounded-3xl border border-wasplex-gold/25 bg-[#07111d]/98 shadow-2xl backdrop-blur"
        >
            <header class="flex items-start justify-between border-b border-white/8 p-5">
                <div>
                    <p class="text-[11px] font-black tracking-[0.18em] text-wasplex-gold uppercase">
                        Décision atomique
                    </p>
                    <h2 class="mt-2 text-xl font-black text-white">Publier plusieurs classes</h2>
                    <p class="mt-2 text-xs leading-5 text-white/40">
                        Sélectionne une version approuvée par classe. Toutes seront publiées dans
                        une seule transaction.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg border border-white/10 px-3 py-2 text-xs font-bold text-white/45"
                    @click="open = false"
                >
                    Fermer
                </button>
            </header>

            <div class="max-h-[22rem] space-y-3 overflow-y-auto p-5">
                <div
                    v-if="approvedByClass.length === 0"
                    class="rounded-2xl border border-white/8 bg-white/[0.03] p-4 text-sm text-white/45"
                >
                    Aucune version approuvée n’attend actuellement une publication.
                </div>

                <div
                    v-for="entry in approvedByClass"
                    :key="entry.economicClass.id"
                    class="rounded-2xl border border-white/8 bg-white/[0.03] p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-black text-white">{{ entry.economicClass.code }}</p>
                            <p class="mt-1 text-xs text-white/35">
                                Publié :
                                {{
                                    percentage(
                                        entry.economicClass.published?.weightBasisPoints ?? 0,
                                    )
                                }}
                            </p>
                        </div>
                        <select
                            v-model="selectedByClass[entry.economicClass.id]"
                            class="max-w-[16rem] rounded-xl border border-white/10 bg-[#030a12] px-3 py-2 text-sm font-bold text-white outline-none focus:border-wasplex-gold/45"
                        >
                            <option value="">Conserver la version publiée</option>
                            <option
                                v-for="version in entry.approved"
                                :key="version.id"
                                :value="version.id"
                            >
                                v{{ version.version }} · {{ version.publicName }} ·
                                {{ percentage(version.weightBasisPoints) }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <footer class="border-t border-white/8 p-5">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-bold text-white/35">Total projeté</p>
                        <p
                            class="mt-1 text-2xl font-black"
                            :class="projectedTotal === 10000 ? 'text-emerald-300' : 'text-red-300'"
                        >
                            {{ percentage(projectedTotal) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl bg-wasplex-gold px-4 py-3 text-sm font-black text-[#151006] disabled:cursor-not-allowed disabled:opacity-35"
                        :disabled="!canPublish"
                        @click="submit"
                    >
                        {{ form.processing ? 'Publication…' : 'Publier la décision' }}
                    </button>
                </div>
                <p v-if="form.errors.version_ids" class="mt-3 text-xs text-red-300">
                    {{ form.errors.version_ids }}
                </p>
                <p v-else-if="projectedTotal !== 10000" class="mt-3 text-xs text-red-300/80">
                    La sélection doit maintenir exactement 100 % avant publication.
                </p>
            </footer>
        </section>
    </div>
</template>
