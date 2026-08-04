<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
};

type Asset = {
    id: string;
    brandId: string | null;
    brandName: string | null;
    kind: 'image' | 'video';
    status: string;
    originalName: string;
    mimeType: string;
    sizeBytes: number;
    width: number | null;
    height: number | null;
    url: string | null;
    label: string | null;
    altText: string | null;
    usageContext: string | null;
    version: number;
    createdAt: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    assets: Asset[];
    brands: Brand[];
    limits: { imageMegabytes: number; videoMegabytes: number };
    flash: { success: string | null };
}>();

const filter = ref<'all' | 'image' | 'video'>('all');
const editor = ref<Asset | null>(null);
const uploadForm = useForm<{
    file: File | null;
    brand_id: string;
    label: string;
    alt_text: string;
    usage_context: string;
}>({
    file: null,
    brand_id: '',
    label: '',
    alt_text: '',
    usage_context: 'campaign',
});
const metadataForm = useForm({ label: '', alt_text: '', usage_context: '' });

const filteredAssets = computed(() =>
    filter.value === 'all'
        ? props.assets
        : props.assets.filter((asset) => asset.kind === filter.value),
);

const fileChanged = (event: Event) => {
    const input = event.target as HTMLInputElement;
    uploadForm.file = input.files?.[0] ?? null;
    if (uploadForm.file && !uploadForm.label) {
        uploadForm.label = uploadForm.file.name.replace(/\.[^.]+$/, '');
    }
};

const upload = () => {
    uploadForm.post('/studio/medias', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => uploadForm.reset(),
    });
};

const openEditor = (asset: Asset) => {
    editor.value = asset;
    metadataForm.label = asset.label ?? '';
    metadataForm.alt_text = asset.altText ?? '';
    metadataForm.usage_context = asset.usageContext ?? '';
    metadataForm.clearErrors();
};

const updateMetadata = () => {
    if (!editor.value) return;
    metadataForm.patch(`/studio/medias/${editor.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editor.value = null;
            metadataForm.reset();
        },
    });
};

const archive = (asset: Asset) => {
    if (!window.confirm(`Archiver le média « ${asset.label || asset.originalName} » ?`)) return;
    router.delete(`/studio/medias/${asset.id}`, { preserveScroll: true });
};

const size = (bytes: number) => {
    if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} Ko`;
    return `${(bytes / 1024 / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Mo`;
};
</script>

<template>
    <Head title="Médiathèque" />
    <StudioLayout :account="account" :active-space="activeSpace" :spaces="spaces" active="media">
        <header>
            <p class="text-xs font-black tracking-[0.24em] text-orange-400 uppercase">
                Images & vidéos
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Médiathèque</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                Importez les contenus de vos marques dans un espace privé. Aucun média d’un autre
                annonceur ne peut apparaître ici.
            </p>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <section class="mt-8 grid gap-6 xl:grid-cols-[0.42fr_1fr]">
            <form
                class="h-fit rounded-[2rem] border border-cyan-400/15 bg-cyan-400/[0.045] p-6 sm:p-7"
                @submit.prevent="upload"
            >
                <p class="text-[10px] font-black tracking-[0.18em] text-cyan-300 uppercase">
                    Nouvel import
                </p>
                <h2 class="mt-3 text-xl font-black">Ajouter un média</h2>
                <p class="mt-2 text-xs leading-5 text-white/35">
                    JPG, PNG, WEBP jusqu’à {{ limits.imageMegabytes }} Mo. MP4 ou WEBM jusqu’à
                    {{ limits.videoMegabytes }} Mo.
                </p>

                <label
                    class="mt-6 block cursor-pointer rounded-2xl border border-dashed border-white/15 bg-black/15 p-6 text-center transition hover:border-cyan-300/35"
                >
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp,video/mp4,video/webm"
                        class="sr-only"
                        @change="fileChanged"
                    />
                    <span
                        class="mx-auto grid size-12 place-items-center rounded-2xl bg-white/5 text-xl"
                        >＋</span
                    >
                    <span class="mt-3 block text-sm font-black">
                        {{ uploadForm.file?.name || 'Choisir une image ou une vidéo' }}
                    </span>
                    <span v-if="uploadForm.file" class="mt-1 block text-xs text-white/30">
                        {{ size(uploadForm.file.size) }}
                    </span>
                </label>
                <p v-if="uploadForm.errors.file" class="mt-2 text-xs text-red-300">
                    {{ uploadForm.errors.file }}
                </p>

                <div class="mt-5 space-y-4">
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Nom lisible</span>
                        <input
                            v-model="uploadForm.label"
                            maxlength="180"
                            placeholder="Ex. Vidéo lancement août"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Associer à une marque</span>
                        <select
                            v-model="uploadForm.brand_id"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-[#071423] px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        >
                            <option value="">Aucune marque</option>
                            <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                {{ brand.name }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Description accessible</span>
                        <input
                            v-model="uploadForm.alt_text"
                            maxlength="300"
                            placeholder="Décrivez brièvement le contenu"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                </div>

                <button
                    type="submit"
                    :disabled="!uploadForm.file || uploadForm.processing"
                    class="mt-6 w-full rounded-2xl bg-cyan-300 px-5 py-3.5 text-sm font-black text-[#04111e] disabled:cursor-not-allowed disabled:opacity-40"
                >
                    {{ uploadForm.processing ? 'Import en cours…' : 'Ajouter à la médiathèque' }}
                </button>
            </form>

            <div>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black">Bibliothèque</h2>
                        <p class="mt-1 text-sm text-white/30">
                            {{ assets.length }} média(s) disponibles
                        </p>
                    </div>
                    <div class="flex rounded-2xl border border-white/8 bg-white/[0.035] p-1">
                        <button
                            v-for="item in [
                                { key: 'all', label: 'Tous' },
                                { key: 'image', label: 'Images' },
                                { key: 'video', label: 'Vidéos' },
                            ]"
                            :key="item.key"
                            class="rounded-xl px-4 py-2 text-xs font-black transition"
                            :class="
                                filter === item.key ? 'bg-white text-[#06111f]' : 'text-white/35'
                            "
                            @click="filter = item.key as 'all' | 'image' | 'video'"
                        >
                            {{ item.label }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="filteredAssets.length"
                    class="mt-5 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3"
                >
                    <article
                        v-for="asset in filteredAssets"
                        :key="asset.id"
                        class="group overflow-hidden rounded-[1.6rem] border border-white/8 bg-white/[0.04]"
                    >
                        <div class="relative aspect-[4/3] overflow-hidden bg-black/25">
                            <img
                                v-if="asset.kind === 'image' && asset.url"
                                :src="asset.url"
                                :alt="asset.altText || asset.label || asset.originalName"
                                class="size-full object-cover transition duration-500 group-hover:scale-105"
                            />
                            <video
                                v-else-if="asset.kind === 'video' && asset.url"
                                :src="asset.url"
                                class="size-full object-cover"
                                controls
                                preload="metadata"
                            />
                            <div
                                v-else
                                class="grid size-full place-items-center text-sm font-black text-white/30"
                            >
                                {{ asset.kind === 'video' ? 'VIDÉO' : 'IMAGE' }}
                            </div>
                            <span
                                class="absolute top-3 left-3 rounded-full bg-black/55 px-3 py-1 text-[10px] font-black uppercase backdrop-blur"
                            >
                                {{ asset.kind }}
                            </span>
                        </div>
                        <div class="p-5">
                            <p class="truncate font-black">
                                {{ asset.label || asset.originalName }}
                            </p>
                            <div
                                class="mt-2 flex items-center justify-between text-xs text-white/30"
                            >
                                <span>{{ asset.brandName || 'Sans marque' }}</span>
                                <span>{{ size(asset.sizeBytes) }}</span>
                            </div>
                            <div class="mt-5 flex justify-end gap-2 border-t border-white/7 pt-4">
                                <button
                                    class="rounded-xl border border-white/10 px-3 py-2 text-xs font-black text-white/45 hover:text-red-300"
                                    @click="archive(asset)"
                                >
                                    Archiver
                                </button>
                                <button
                                    class="rounded-xl bg-white px-3 py-2 text-xs font-black text-[#06111f]"
                                    @click="openEditor(asset)"
                                >
                                    Informations
                                </button>
                            </div>
                        </div>
                    </article>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-[2rem] border border-dashed border-white/12 bg-white/[0.025] px-6 py-16 text-center"
                >
                    <p class="text-xl font-black">Aucun média dans cette vue</p>
                    <p class="mt-2 text-sm text-white/35">
                        Utilisez le formulaire pour commencer la bibliothèque.
                    </p>
                </div>
            </div>
        </section>

        <div
            v-if="editor"
            class="fixed inset-0 z-50 grid place-items-center bg-black/75 px-4 py-8 backdrop-blur-sm"
            @click.self="editor = null"
        >
            <form
                class="w-full max-w-lg rounded-[2rem] border border-white/10 bg-[#081522] p-6 shadow-2xl sm:p-8"
                @submit.prevent="updateMetadata"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-[10px] font-black tracking-[0.18em] text-cyan-300 uppercase">
                            Métadonnées · v{{ editor.version + 1 }}
                        </p>
                        <h2 class="mt-2 text-xl font-black">Informations du média</h2>
                    </div>
                    <button
                        type="button"
                        class="text-xs font-black text-white/35"
                        @click="editor = null"
                    >
                        Fermer
                    </button>
                </div>

                <div class="mt-6 space-y-4">
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Nom lisible</span>
                        <input
                            v-model="metadataForm.label"
                            maxlength="180"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Description accessible</span>
                        <textarea
                            v-model="metadataForm.alt_text"
                            rows="3"
                            maxlength="300"
                            class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label class="block">
                        <span class="text-xs font-black text-white/50">Usage prévu</span>
                        <select
                            v-model="metadataForm.usage_context"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-[#071423] px-4 py-3 text-sm outline-none focus:border-cyan-300/50"
                        >
                            <option value="">Non défini</option>
                            <option value="logo">Logo</option>
                            <option value="campaign">Future campagne</option>
                            <option value="cover">Couverture</option>
                            <option value="social">Réseaux sociaux</option>
                        </select>
                    </label>
                </div>

                <div class="mt-7 flex justify-end gap-3 border-t border-white/7 pt-6">
                    <button
                        type="button"
                        class="rounded-2xl border border-white/10 px-5 py-3 text-sm font-black text-white/50"
                        @click="editor = null"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        :disabled="metadataForm.processing"
                        class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e] disabled:opacity-50"
                    >
                        {{
                            metadataForm.processing ? 'Enregistrement…' : 'Enregistrer une version'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </StudioLayout>
</template>
