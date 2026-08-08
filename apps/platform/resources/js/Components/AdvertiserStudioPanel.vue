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
}

const ADVERTISER_TYPES = [
    { value: 'individual', label: 'Particulier' },
    { value: 'business', label: 'Entreprise' },
    { value: 'agency', label: 'Agence' },
    { value: 'institutional_advertiser', label: 'Institution' },
] as const;

const profile = ref<Profile | null>(null);
const brands = ref<Brand[]>([]);
const selectedBrandId = ref<string | null>(null);
const assets = ref<Asset[]>([]);
const loading = ref(true);
const loadError = ref<string | null>(null);
const savingProfile = ref(false);
const creatingBrand = ref(false);
const uploading = ref(false);
const newBrandName = ref('');
const newColorName = ref('');
const newColorHex = ref('#4FA3FF');
const fileInput = ref<HTMLInputElement | null>(null);
const dragOver = ref(false);

const selectedBrand = computed(() => brands.value.find((b) => b.id === selectedBrandId.value) ?? null);

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
        loadError.value = message ?? 'Le Studio est momentanément indisponible.';
    } finally {
        loading.value = false;
    }
}

async function saveProfile(): Promise<void> {
    if (!profile.value) {
        return;
    }
    savingProfile.value = true;
    try {
        const { data } = await http.patch('/advertiser/profile', {
            advertiser_type: profile.value.advertiser_type,
            legal_name: profile.value.legal_name,
        });
        profile.value = data.profile;
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
        const { data } = await http.post('/advertiser/brands', { name: newBrandName.value });
        brands.value = [data.brand, ...brands.value];
        newBrandName.value = '';
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
    const index = brands.value.findIndex((b) => b.id === brandId);
    if (index !== -1) {
        brands.value[index] = brandRes.data.brand;
    }
    assets.value = assetsRes.data.assets;
}

async function addColor(): Promise<void> {
    if (!selectedBrand.value || !newColorName.value.trim()) {
        return;
    }
    const colors = [
        ...selectedBrand.value.colors.map((c) => ({ name: c.name, hex: c.hex, usage: c.usage })),
        { name: newColorName.value, hex: newColorHex.value, usage: 'accent' },
    ];
    const { data } = await http.put(`/advertiser/brands/${selectedBrand.value.id}/colors`, { colors });
    const index = brands.value.findIndex((b) => b.id === selectedBrandId.value);
    if (index !== -1) {
        brands.value[index] = data.brand;
    }
    newColorName.value = '';
}

async function uploadFile(file: File | undefined): Promise<void> {
    if (!file || !selectedBrandId.value) {
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

function statusClasses(status: string): string {
    if (['verified', 'active', 'ready', 'approved'].includes(status)) {
        return 'bg-wpx-success/10 text-wpx-success-light';
    }
    if (['rejected', 'restricted', 'suspended', 'needs_changes'].includes(status)) {
        return 'bg-wpx-danger/10 text-wpx-danger-light';
    }

    return 'bg-wpx-pending/10 text-wpx-warning-light';
}

void load();
</script>

<template>
    <div class="flex flex-col gap-6">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <!-- Profil annonceur -->
            <div v-if="profile" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex flex-col gap-3 p-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-wpx-text text-sm font-semibold">Profil annonceur</h2>
                    <span
                        class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                        :class="statusClasses(profile.status)"
                    >
                        {{ profile.status }}
                    </span>
                </div>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex flex-col gap-1 text-xs">
                        <span class="text-wpx-text-muted">Type d'annonceur</span>
                        <select
                            v-model="profile.advertiser_type"
                            class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1.5 text-sm"
                        >
                            <option :value="null" disabled>Choisir…</option>
                            <option v-for="t in ADVERTISER_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1 text-xs">
                        <span class="text-wpx-text-muted">Raison sociale</span>
                        <input
                            v-model="profile.legal_name"
                            class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1.5 text-sm"
                        />
                    </label>
                </div>
                <button
                    type="button"
                    class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 self-start bg-gradient-to-br px-4 py-1.5 text-sm font-semibold disabled:opacity-50"
                    :disabled="savingProfile"
                    @click="saveProfile"
                >
                    Enregistrer
                </button>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row">
                <!-- Liste des marques -->
                <div class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex w-full flex-col gap-3 p-4 lg:w-72">
                    <h2 class="text-wpx-text text-sm font-semibold">Mes marques</h2>
                    <div class="flex gap-2">
                        <input
                            v-model="newBrandName"
                            placeholder="Nouvelle marque"
                            class="rounded-wpx-sm border-wpx-border text-wpx-text w-full border px-2 py-1.5 text-sm"
                            @keyup.enter="createBrand"
                        />
                        <button
                            type="button"
                            class="rounded-wpx-sm bg-wpx-navy-950 px-3 text-sm font-semibold text-white disabled:opacity-50"
                            :disabled="creatingBrand"
                            @click="createBrand"
                        >
                            +
                        </button>
                    </div>
                    <p v-if="loading" class="text-wpx-text-muted text-sm">Chargement…</p>
                    <ul v-else class="flex flex-col gap-1">
                        <li v-for="brand in brands" :key="brand.id">
                            <button
                                type="button"
                                class="rounded-wpx-sm flex w-full items-center justify-between px-2 py-1.5 text-left text-sm"
                                :class="selectedBrandId === brand.id ? 'bg-wpx-canvas font-semibold' : 'text-wpx-text'"
                                @click="selectBrand(brand.id)"
                            >
                                <span>{{ brand.name }}</span>
                                <span
                                    class="rounded-wpx-sm px-1.5 py-0.5 text-[10px] font-semibold"
                                    :class="statusClasses(brand.status)"
                                >
                                    {{ brand.status }}
                                </span>
                            </button>
                        </li>
                        <li v-if="!loading && brands.length === 0" class="text-wpx-text-muted text-sm">
                            Aucune marque encore.
                        </li>
                    </ul>
                </div>

                <!-- Détail de la marque -->
                <div v-if="selectedBrand" class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface flex-1 p-4">
                    <h2 class="text-wpx-text mb-1 text-base font-semibold">{{ selectedBrand.name }}</h2>
                    <p v-if="selectedBrand.slogan" class="text-wpx-text-muted mb-4 text-sm italic">
                        « {{ selectedBrand.slogan }} »
                    </p>

                    <h3 class="text-wpx-text mb-2 text-sm font-semibold">Charte — couleurs</h3>
                    <div class="mb-2 flex flex-wrap gap-2">
                        <span
                            v-for="color in selectedBrand.colors"
                            :key="color.id"
                            class="rounded-wpx-sm border-wpx-border flex items-center gap-1.5 border px-2 py-1 text-xs"
                        >
                            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: color.hex }" />
                            {{ color.name }} ({{ color.usage }})
                        </span>
                    </div>
                    <div class="mb-6 flex items-center gap-2">
                        <input v-model="newColorHex" type="color" class="h-8 w-10 rounded" />
                        <input
                            v-model="newColorName"
                            placeholder="Nom de la couleur"
                            class="rounded-wpx-sm border-wpx-border text-wpx-text border px-2 py-1 text-sm"
                            @keyup.enter="addColor"
                        />
                        <button
                            type="button"
                            class="text-wpx-blue-light text-xs font-semibold hover:underline"
                            @click="addColor"
                        >
                            Ajouter
                        </button>
                    </div>

                    <h3 class="text-wpx-text mb-2 text-sm font-semibold">Bibliothèque créative</h3>
                    <label
                        class="rounded-wpx-md mb-3 flex cursor-pointer flex-col items-center border-[1.5px] border-dashed p-7 text-center transition"
                        :class="
                            dragOver ? 'border-wpx-blue-light bg-wpx-blue-light/5' : 'border-wpx-border bg-wpx-raised'
                        "
                        @dragover.prevent="dragOver = true"
                        @dragleave.prevent="dragOver = false"
                        @drop.prevent="onDrop"
                    >
                        <svg width="30" height="30" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M12 16V4M7 9l5-5 5 5"
                                stroke="#8B99AC"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <path
                                d="M4 16v3a2 2 0 002 2h12a2 2 0 002-2v-3"
                                stroke="#8B99AC"
                                stroke-width="1.8"
                                stroke-linecap="round"
                            />
                        </svg>
                        <span class="text-wpx-text mt-2.5 text-sm font-bold">Glisse tes visuels ici</span>
                        <span class="text-wpx-text-muted mt-0.5 text-[11px]">
                            JPG, PNG, WEBP (10 Mo max) ou MP4, MOV, WEBM (200 Mo max)
                        </span>
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/*,video/*"
                            class="hidden"
                            @change="onFileInputChange"
                        />
                    </label>
                    <p v-if="uploading" class="text-wpx-text-muted mb-3 text-xs">Envoi en cours…</p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="asset in assets"
                            :key="asset.id"
                            class="rounded-wpx-sm border-wpx-border border p-2 text-center"
                        >
                            <img
                                v-if="asset.type === 'image' && asset.url"
                                :src="asset.url"
                                class="mb-1 h-16 w-full rounded object-cover"
                            />
                            <div
                                v-else
                                class="bg-wpx-canvas mb-1 flex h-16 w-full items-center justify-center rounded text-2xl"
                            >
                                🎬
                            </div>
                            <span
                                class="rounded-wpx-sm inline-block px-1.5 py-0.5 text-[10px] font-semibold"
                                :class="statusClasses(asset.moderation_status)"
                            >
                                {{ asset.moderation_status }}
                            </span>
                        </div>
                        <p v-if="assets.length === 0" class="text-wpx-text-muted col-span-full text-sm">
                            Aucun média encore.
                        </p>
                    </div>
                </div>
                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface text-wpx-text-muted flex flex-1 items-center justify-center text-sm"
                >
                    Crée une marque pour commencer.
                </div>
            </div>
        </template>
    </div>
</template>
