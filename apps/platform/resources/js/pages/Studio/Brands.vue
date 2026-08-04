<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Brand = {
    id: string;
    name: string;
    slug: string;
    status: 'active' | 'archived';
    versionCount: number;
    activeVersion: {
        version: number;
        publicName: string;
        slogan: string | null;
        description: string | null;
        primaryColor: string;
        secondaryColor: string;
        logoAssetId: string | null;
        logoUrl: string | null;
        publishedAt: string | null;
    } | null;
};

type Asset = {
    id: string;
    kind: 'image' | 'video';
    originalName: string;
    label: string | null;
    url: string | null;
};

defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    brands: Brand[];
    logoChoices: Asset[];
    flash: { success: string | null };
}>();

const editorOpen = ref(false);
const editing = ref<Brand | null>(null);
const form = useForm({
    public_name: '',
    slogan: '',
    description: '',
    primary_color: '#0EA5E9',
    secondary_color: '#F97316',
    logo_asset_id: '' as string | null,
});

const openCreate = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.primary_color = '#0EA5E9';
    form.secondary_color = '#F97316';
    form.logo_asset_id = '';
    editorOpen.value = true;
};

const openEdit = (brand: Brand) => {
    const version = brand.activeVersion;
    editing.value = brand;
    form.clearErrors();
    form.public_name = version?.publicName ?? brand.name;
    form.slogan = version?.slogan ?? '';
    form.description = version?.description ?? '';
    form.primary_color = version?.primaryColor ?? '#0EA5E9';
    form.secondary_color = version?.secondaryColor ?? '#F97316';
    form.logo_asset_id = version?.logoAssetId ?? '';
    editorOpen.value = true;
};

const closeEditor = () => {
    if (!form.processing) editorOpen.value = false;
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
            editing.value = null;
            form.reset();
        },
    };

    if (editing.value) {
        form.patch(`/studio/marques/${editing.value.id}`, options);
    } else {
        form.post('/studio/marques', options);
    }
};

const archive = (brand: Brand) => {
    if (!window.confirm(`Archiver la marque « ${brand.name} » ?`)) return;
    router.delete(`/studio/marques/${brand.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Marques" />
    <StudioLayout :account="account" :active-space="activeSpace" :spaces="spaces" active="brands">
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.24em] text-orange-400 uppercase">
                    Identités commerciales
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Vos marques</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                    Chaque modification crée une nouvelle version publiée. L’historique précédent
                    est conservé sans écrasement.
                </p>
            </div>
            <button
                class="rounded-2xl bg-white px-5 py-3 text-sm font-black text-[#06111f]"
                @click="openCreate"
            >
                Nouvelle marque
            </button>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <section v-if="brands.length" class="mt-8 grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
            <article
                v-for="brand in brands"
                :key="brand.id"
                class="group overflow-hidden rounded-[2rem] border border-white/8 bg-white/[0.04]"
                :class="brand.status === 'archived' ? 'opacity-55' : ''"
            >
                <div
                    class="relative h-32 overflow-hidden"
                    :style="{
                        background: `linear-gradient(135deg, ${brand.activeVersion?.primaryColor ?? '#0EA5E9'}, ${brand.activeVersion?.secondaryColor ?? '#F97316'})`,
                    }"
                >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/45 to-transparent" />
                    <div
                        class="absolute bottom-4 left-5 grid size-16 place-items-center overflow-hidden rounded-2xl border border-white/20 bg-black/20 text-2xl font-black shadow-xl backdrop-blur"
                    >
                        <img
                            v-if="brand.activeVersion?.logoUrl"
                            :src="brand.activeVersion.logoUrl"
                            :alt="brand.name"
                            class="size-full object-cover"
                        />
                        <span v-else>{{ brand.name.slice(0, 1).toUpperCase() }}</span>
                    </div>
                    <span
                        class="absolute top-4 right-4 rounded-full border border-white/20 bg-black/20 px-3 py-1 text-[10px] font-black uppercase backdrop-blur"
                    >
                        v{{ brand.activeVersion?.version ?? brand.versionCount }}
                    </span>
                </div>

                <div class="p-6">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate text-xl font-black">{{ brand.name }}</h2>
                            <p class="mt-1 truncate text-sm text-white/35">
                                {{ brand.activeVersion?.slogan || 'Aucun slogan renseigné' }}
                            </p>
                        </div>
                        <span
                            class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                            :class="
                                brand.status === 'active'
                                    ? 'bg-emerald-400/10 text-emerald-300'
                                    : 'bg-white/5 text-white/35'
                            "
                        >
                            {{ brand.status === 'active' ? 'Active' : 'Archivée' }}
                        </span>
                    </div>

                    <p class="mt-5 line-clamp-3 min-h-15 text-sm leading-6 text-white/38">
                        {{
                            brand.activeVersion?.description ||
                            'Ajoutez une courte présentation pour préparer les futures campagnes.'
                        }}
                    </p>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-white/7 pt-5"
                    >
                        <p class="text-xs text-white/28">{{ brand.versionCount }} version(s)</p>
                        <div class="flex gap-2">
                            <button
                                v-if="brand.status === 'active'"
                                class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black text-white/55 hover:text-white"
                                @click="archive(brand)"
                            >
                                Archiver
                            </button>
                            <button
                                class="rounded-xl bg-cyan-300 px-3 py-2 text-xs font-black text-[#04111e]"
                                @click="openEdit(brand)"
                            >
                                Modifier
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-else
            class="mt-8 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.025] px-6 py-16 text-center"
        >
            <div
                class="mx-auto grid size-16 place-items-center rounded-3xl bg-cyan-300/10 text-2xl"
            >
                ✦
            </div>
            <h2 class="mt-5 text-2xl font-black">Créez votre première marque</h2>
            <p class="mx-auto mt-3 max-w-lg text-sm leading-6 text-white/38">
                Nom, slogan, couleurs et logo : tout ce qu’il faut pour préparer une identité
                cohérente avant la campagne.
            </p>
            <button
                class="mt-7 rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e]"
                @click="openCreate"
            >
                Commencer
            </button>
        </section>

        <div
            v-if="editorOpen"
            class="fixed inset-0 z-50 grid place-items-center overflow-y-auto bg-black/75 px-4 py-8 backdrop-blur-sm"
            @click.self="closeEditor"
        >
            <form
                class="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-[#081522] p-6 shadow-2xl sm:p-8"
                @submit.prevent="submit"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-black tracking-[0.18em] text-cyan-300 uppercase">
                            {{
                                editing ? `Nouvelle version · ${editing.name}` : 'Nouvelle identité'
                            }}
                        </p>
                        <h2 class="mt-2 text-2xl font-black">
                            {{ editing ? 'Modifier la marque' : 'Créer une marque' }}
                        </h2>
                    </div>
                    <button
                        type="button"
                        class="text-sm font-bold text-white/35"
                        @click="closeEditor"
                    >
                        Fermer
                    </button>
                </div>

                <div class="mt-7 grid gap-5 md:grid-cols-2">
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55">Nom public</span>
                        <input
                            v-model="form.public_name"
                            required
                            maxlength="160"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        />
                        <span
                            v-if="form.errors.public_name"
                            class="mt-2 block text-xs text-red-300"
                            >{{ form.errors.public_name }}</span
                        >
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55">Slogan</span>
                        <input
                            v-model="form.slogan"
                            maxlength="220"
                            placeholder="Une phrase courte et mémorable"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Couleur principale</span>
                        <div
                            class="mt-2 flex items-center gap-3 rounded-2xl border border-white/10 bg-black/20 p-3"
                        >
                            <input
                                v-model="form.primary_color"
                                type="color"
                                class="size-9 rounded-lg bg-transparent"
                            />
                            <input
                                v-model="form.primary_color"
                                class="min-w-0 flex-1 bg-transparent text-sm font-black uppercase outline-none"
                            />
                        </div>
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Couleur secondaire</span>
                        <div
                            class="mt-2 flex items-center gap-3 rounded-2xl border border-white/10 bg-black/20 p-3"
                        >
                            <input
                                v-model="form.secondary_color"
                                type="color"
                                class="size-9 rounded-lg bg-transparent"
                            />
                            <input
                                v-model="form.secondary_color"
                                class="min-w-0 flex-1 bg-transparent text-sm font-black uppercase outline-none"
                            />
                        </div>
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55"
                            >Logo depuis la médiathèque</span
                        >
                        <select
                            v-model="form.logo_asset_id"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-[#071423] px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        >
                            <option value="">Aucun logo pour le moment</option>
                            <option v-for="asset in logoChoices" :key="asset.id" :value="asset.id">
                                {{ asset.label || asset.originalName }}
                            </option>
                        </select>
                        <p v-if="!logoChoices.length" class="mt-2 text-xs text-white/28">
                            Ajoutez d’abord une image dans la médiathèque pour l’utiliser comme
                            logo.
                        </p>
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55"
                            >Présentation de la marque</span
                        >
                        <textarea
                            v-model="form.description"
                            rows="4"
                            maxlength="2000"
                            class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm leading-6 outline-none focus:border-cyan-300/50"
                        />
                    </label>
                </div>

                <div
                    class="mt-6 flex items-center gap-4 rounded-2xl border border-white/7 p-4"
                    :style="{
                        background: `linear-gradient(135deg, ${form.primary_color}22, ${form.secondary_color}22)`,
                    }"
                >
                    <div
                        class="grid size-12 place-items-center rounded-2xl font-black"
                        :style="{
                            background: `linear-gradient(135deg, ${form.primary_color}, ${form.secondary_color})`,
                        }"
                    >
                        {{ form.public_name.slice(0, 1).toUpperCase() || 'W' }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-black">
                            {{ form.public_name || 'Aperçu de la marque' }}
                        </p>
                        <p class="mt-1 truncate text-xs text-white/35">
                            {{ form.slogan || 'Votre slogan apparaîtra ici' }}
                        </p>
                    </div>
                </div>

                <div class="mt-7 flex justify-end gap-3 border-t border-white/7 pt-6">
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-black text-white/55"
                        @click="closeEditor"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e] disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Publication…'
                                : editing
                                  ? 'Publier la nouvelle version'
                                  : 'Créer la marque'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </StudioLayout>
</template>
