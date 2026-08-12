<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

interface Profile {
    id: string;
    advertiser_type: string | null;
    legal_name: string | null;
    status: string;
}

interface Brand {
    id: string;
    name: string;
    slogan: string | null;
    status: string;
    colors: Array<{ id: string; name: string; hex: string; usage: string }>;
}

interface Asset {
    id: string;
    type: string;
    filename: string;
    moderation_status: string;
    url?: string;
    duration?: number | null;
    duration_ms?: number | null;
}

const ADVERTISER_TYPES = [
    { value: 'individual', label: 'Particulier / activité solo' },
    { value: 'business', label: 'Commerce / entreprise' },
    { value: 'agency', label: 'Agence' },
    { value: 'institutional_advertiser', label: 'Institution / organisation' },
] as const;

const profile = ref<Profile | null>(null);
const brands = ref<Brand[]>([]);
const selectedBrandId = ref<string | null>(null);
const assets = ref<Asset[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const savingProfile = ref(false);
const profileSaved = ref(false);
const creatingBrand = ref(false);
const showCreateBrand = ref(false);
const uploading = ref(false);
const newBrandName = ref('');
const newColorName = ref('');
const newColorHex = ref('#4FA3FF');
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);

const selectedBrand = computed(() => brands.value.find((brand) => brand.id === selectedBrandId.value) ?? null);
const profileTypeLabel = computed(
    () => ADVERTISER_TYPES.find((type) => type.value === profile.value?.advertiser_type)?.label ?? 'À préciser',
);

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [profileRes, brandsRes] = await Promise.all([
            http.get('/advertiser/profile'),
            http.get('/advertiser/brands'),
        ]);
        profile.value = profileRes.data.profile;
        brands.value = brandsRes.data.brands;
        if (brands.value.length > 0 && selectedBrandId.value === null) {
            await selectBrand(brands.value[0].id);
        }
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? 'Les informations de votre activité sont momentanément indisponibles.';
    } finally {
        loading.value = false;
    }
}

async function saveProfile(): Promise<void> {
    if (!profile.value) {
        return;
    }

    savingProfile.value = true;
    profileSaved.value = false;
    try {
        const { data } = await http.patch('/advertiser/profile', {
            advertiser_type: profile.value.advertiser_type,
            legal_name: profile.value.legal_name,
        });
        profile.value = data.profile;
        profileSaved.value = true;
        setTimeout(() => {
            profileSaved.value = false;
        }, 1800);
    } finally {
        savingProfile.value = false;
    }
}

async function createBrand(): Promise<void> {
    if (!newBrandName.value.trim()) {
        return;
    }

    creatingBrand.value = true;
    try {
        const { data } = await http.post('/advertiser/brands', { name: newBrandName.value.trim() });
        brands.value = [data.brand, ...brands.value];
        newBrandName.value = '';
        showCreateBrand.value = false;
        await selectBrand(data.brand.id);
    } finally {
        creatingBrand.value = false;
    }
}

async function selectBrand(brandId: string): Promise<void> {
    selectedBrandId.value = brandId;
    const [brandRes, assetsRes] = await Promise.all([
        http.get(`/advertiser/brands/${brandId}`),
        http.get(`/advertiser/assets?brand_id=${brandId}`),
    ]);
    const index = brands.value.findIndex((brand) => brand.id === brandId);
    if (index !== -1) {
        brands.value[index] = brandRes.data.brand;
    }
    assets.value = (assetsRes.data.assets as Asset[]).filter((asset) => asset.type === 'video');
}

async function addColor(): Promise<void> {
    if (!selectedBrand.value || !newColorName.value.trim()) {
        return;
    }

    const colors = [
        ...selectedBrand.value.colors.map((color) => ({ name: color.name, hex: color.hex, usage: color.usage })),
        { name: newColorName.value.trim(), hex: newColorHex.value, usage: 'accent' },
    ];
    const { data } = await http.put(`/advertiser/brands/${selectedBrand.value.id}/colors`, { colors });
    const index = brands.value.findIndex((brand) => brand.id === selectedBrandId.value);
    if (index !== -1) {
        brands.value[index] = data.brand;
    }
    newColorName.value = '';
}

async function uploadFile(file: File | undefined): Promise<void> {
    if (!file || !selectedBrandId.value) {
        return;
    }

    if (!file.type.startsWith('video/')) {
        loadError.value = 'Wasplex V1 accepte uniquement une vidéo publicitaire.';
        return;
    }

    uploading.value = true;
    try {
        const form = new FormData();
        form.append('brand_id', selectedBrandId.value);
        form.append('file', file);
        const { data } = await http.post('/advertiser/assets', form, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        assets.value = [data.asset, ...assets.value];
    } finally {
        uploading.value = false;
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
}

function onFileInputChange(event: Event): void {
    void uploadFile((event.target as HTMLInputElement).files?.[0]);
}

function onDrop(event: DragEvent): void {
    dragOver.value = false;
    void uploadFile(event.dataTransfer?.files?.[0]);
}

function statusLabel(status: string): string {
    return (
        {
            verified: 'Vérifié',
            active: 'Actif',
            ready: 'Prêt',
            approved: 'Validé',
            pending: 'En validation',
            rejected: 'Refusé',
            restricted: 'Restreint',
            suspended: 'Suspendu',
            needs_changes: 'À corriger',
        }[status] ?? status
    );
}

function statusClasses(status: string): string {
    if (['verified', 'active', 'ready', 'approved'].includes(status)) {
        return 'bg-wpx-success/15 text-wpx-success-light';
    }
    if (['rejected', 'restricted', 'suspended', 'needs_changes'].includes(status)) {
        return 'bg-wpx-danger/15 text-wpx-danger-light';
    }

    return 'bg-wpx-gold/12 text-wpx-gold';
}

void load();
</script>

<template>
    <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 lg:gap-5">
        <div v-if="loadError" class="bg-wpx-danger/10 text-wpx-danger-light rounded-2xl p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-5 sm:p-6">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="max-w-xl">
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">Mon activité</p>
                        <h1 class="text-wpx-white-soft mt-1 text-xl font-extrabold sm:text-2xl">
                            Présentez simplement qui vous êtes.
                        </h1>
                        <p class="text-wpx-muted-dark mt-2 text-sm leading-relaxed">
                            Que vous travailliez seul, avec un commerce, une entreprise ou une institution, Wasplex
                            adapte le Studio à votre activité.
                        </p>
                    </div>
                    <div v-if="profile" class="flex items-center gap-2">
                        <span class="rounded-full px-3 py-1.5 text-xs font-bold" :class="statusClasses(profile.status)">
                            {{ statusLabel(profile.status) }}
                        </span>
                    </div>
                </div>

                <div v-if="profile" class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="text-wpx-muted-dark text-xs font-semibold">Vous êtes…</span>
                        <select
                            v-model="profile.advertiser_type"
                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft mt-1.5 w-full rounded-2xl border px-4 py-3 text-sm outline-none"
                        >
                            <option :value="null" disabled>Choisir…</option>
                            <option v-for="type in ADVERTISER_TYPES" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-wpx-muted-dark text-xs font-semibold">Nom officiel ou raison sociale</span>
                        <input
                            v-model="profile.legal_name"
                            placeholder="Facultatif pour une activité informelle"
                            class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft placeholder:text-wpx-muted-dark mt-1.5 w-full rounded-2xl border px-4 py-3 text-sm outline-none"
                        />
                    </label>
                </div>

                <div v-if="profile" class="mt-4 flex items-center gap-3">
                    <button
                        type="button"
                        class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-xl bg-gradient-to-r px-5 py-2.5 text-sm font-extrabold disabled:opacity-50"
                        :disabled="savingProfile"
                        @click="saveProfile"
                    >
                        {{ savingProfile ? 'Enregistrement…' : 'Enregistrer' }}
                    </button>
                    <span v-if="profileSaved" class="text-wpx-success-light text-xs font-bold">✓ Enregistré</span>
                    <span v-else class="text-wpx-muted-dark text-xs">{{ profileTypeLabel }}</span>
                </div>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-4 sm:p-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-wpx-white-soft text-sm font-bold">Mes activités et marques</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            Une seule suffit pour commencer. Ajoutez-en d’autres seulement si nécessaire.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="text-wpx-gold shrink-0 text-xs font-bold"
                        @click="showCreateBrand = !showCreateBrand"
                    >
                        {{ showCreateBrand ? 'Annuler' : '+ Ajouter' }}
                    </button>
                </div>

                <form
                    v-if="showCreateBrand"
                    class="border-wpx-border-dark bg-wpx-navy-750 mt-4 flex gap-2 rounded-2xl border p-2"
                    @submit.prevent="createBrand"
                >
                    <input
                        v-model="newBrandName"
                        placeholder="Ex. Boutique Awa, Mon restaurant, Wasplex…"
                        class="text-wpx-white-soft placeholder:text-wpx-muted-dark min-w-0 flex-1 bg-transparent px-2 py-2 text-sm outline-none"
                    />
                    <button
                        type="submit"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-4 text-xs font-extrabold disabled:opacity-50"
                        :disabled="creatingBrand || !newBrandName.trim()"
                    >
                        Créer
                    </button>
                </form>

                <div v-if="loading" class="text-wpx-muted-dark mt-4 text-sm">Chargement…</div>
                <div
                    v-else-if="brands.length === 0"
                    class="border-wpx-border-dark mt-4 rounded-2xl border border-dashed px-5 py-8 text-center"
                >
                    <p class="text-wpx-white-soft text-sm font-bold">Ajoutez le nom de votre activité</p>
                    <p class="text-wpx-muted-dark mx-auto mt-1 max-w-md text-xs leading-relaxed">
                        Cela peut être votre nom, votre boutique, votre commerce, votre entreprise ou votre
                        organisation.
                    </p>
                    <button type="button" class="text-wpx-cyan mt-3 text-xs font-bold" @click="showCreateBrand = true">
                        + Ajouter mon activité
                    </button>
                </div>
                <div v-else class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="brand in brands"
                        :key="brand.id"
                        type="button"
                        class="min-w-44 rounded-2xl border p-3 text-left transition"
                        :class="
                            selectedBrandId === brand.id
                                ? 'border-wpx-cyan bg-wpx-cyan/8'
                                : 'border-wpx-border-dark bg-wpx-navy-750'
                        "
                        @click="selectBrand(brand.id)"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br text-xs font-black"
                            >
                                {{ brand.name.slice(0, 2).toUpperCase() }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-sm font-bold">{{ brand.name }}</p>
                                <p class="mt-0.5 text-[10px] font-bold" :class="statusClasses(brand.status)">
                                    {{ statusLabel(brand.status) }}
                                </p>
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <section
                v-if="selectedBrand"
                class="border-wpx-border-dark bg-wpx-navy-850 overflow-hidden rounded-2xl border"
            >
                <div class="border-wpx-border-dark flex items-center justify-between gap-3 border-b px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-wpx-cyan text-[10px] font-extrabold tracking-wide uppercase">
                            {{ selectedBrand.name }}
                        </p>
                        <h2 class="text-wpx-white-soft mt-0.5 text-base font-extrabold">Mes vidéos</h2>
                        <p class="text-wpx-muted-dark mt-0.5 text-xs">
                            Vidéos réutilisables dans vos publicités · 5 minutes maximum en V1.
                        </p>
                    </div>
                    <span class="text-wpx-muted-dark shrink-0 text-xs"
                        >{{ assets.length }} média{{ assets.length > 1 ? 's' : '' }}</span
                    >
                </div>

                <div class="p-4 sm:p-5">
                    <label
                        class="flex cursor-pointer flex-col items-center rounded-2xl border border-dashed p-5 text-center transition sm:p-6"
                        :class="dragOver ? 'border-wpx-cyan bg-wpx-cyan/8' : 'border-wpx-border-dark bg-wpx-navy-750'"
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="onDrop"
                    >
                        <span class="bg-wpx-blue/12 flex h-11 w-11 items-center justify-center rounded-2xl">
                            <svg width="23" height="23" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M12 16V4M7 9l5-5 5 5"
                                    stroke="#4FA3FF"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"
                                    stroke="#4FA3FF"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </span>
                        <span class="text-wpx-white-soft mt-2.5 text-sm font-bold">Ajouter une vidéo</span>
                        <span class="text-wpx-muted-dark mt-1 text-[11px]"
                            >MP4, MOV ou WEBM · 5 minutes maximum · 200 Mo max</span
                        >
                        <input
                            ref="fileInput"
                            type="file"
                            accept="video/mp4,video/quicktime,video/webm"
                            class="hidden"
                            @change="onFileInputChange"
                        />
                    </label>
                    <p v-if="uploading" class="text-wpx-cyan mt-3 text-xs font-bold">Envoi de la vidéo en cours…</p>

                    <div v-if="assets.length > 0" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <article
                            v-for="asset in assets"
                            :key="asset.id"
                            class="border-wpx-border-dark bg-wpx-navy-750 overflow-hidden rounded-2xl border"
                        >
                            <img
                                v-if="asset.type === 'image' && asset.url"
                                :src="asset.url"
                                :alt="asset.filename"
                                class="h-28 w-full object-cover"
                            />
                            <div v-else class="bg-wpx-navy-950 flex h-28 w-full items-center justify-center text-3xl">
                                🎬
                            </div>
                            <div class="p-2.5">
                                <p class="text-wpx-white-soft truncate text-[11px] font-bold">{{ asset.filename }}</p>
                                <span
                                    class="mt-1.5 inline-block rounded-full px-2 py-0.5 text-[9px] font-bold"
                                    :class="statusClasses(asset.moderation_status)"
                                >
                                    {{ statusLabel(asset.moderation_status) }}
                                </span>
                            </div>
                        </article>
                    </div>

                    <details class="border-wpx-border-dark mt-5 border-t pt-4">
                        <summary class="text-wpx-muted-dark cursor-pointer text-xs font-bold">
                            Options de marque avancées (facultatif)
                        </summary>
                        <div class="mt-4">
                            <p class="text-wpx-white-soft text-sm font-bold">Couleurs de marque</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Utile surtout pour les entreprises qui souhaitent conserver une identité visuelle
                                précise.
                            </p>
                            <div v-if="selectedBrand.colors.length > 0" class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="color in selectedBrand.colors"
                                    :key="color.id"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs"
                                >
                                    <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: color.hex }" />
                                    {{ color.name }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <input
                                    v-model="newColorHex"
                                    type="color"
                                    class="h-10 w-12 rounded-xl border-0 bg-transparent"
                                />
                                <input
                                    v-model="newColorName"
                                    placeholder="Nom de la couleur"
                                    class="border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft placeholder:text-wpx-muted-dark min-w-40 flex-1 rounded-xl border px-3 py-2.5 text-sm outline-none"
                                    @keyup.enter="addColor"
                                />
                                <button
                                    type="button"
                                    class="text-wpx-cyan px-2 py-2 text-xs font-bold"
                                    @click="addColor"
                                >
                                    Ajouter
                                </button>
                            </div>
                        </div>
                    </details>
                </div>
            </section>
        </template>
    </div>
</template>
