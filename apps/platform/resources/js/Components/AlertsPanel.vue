<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import HealthPanel from '@/Components/HealthPanel.vue';

type AlertTab = 'for-you' | 'community' | 'sos' | 'health' | 'mine';
type AlertCategory = 'object' | 'document' | 'vehicle' | 'person' | 'sos';

interface PublicAlert {
    id: string;
    category: AlertCategory;
    situation: string;
    priority: string;
    title: string;
    summary: string;
    city: string | null;
    area_label: string | null;
    published_at: string | null;
}

interface MyAlert extends PublicAlert {
    status: string;
    description: string;
    rejection_reason: string | null;
    public_visibility: boolean;
    submitted_at: string | null;
}

const tabs: Array<{ key: AlertTab; label: string }> = [
    { key: 'for-you', label: 'Pour vous' },
    { key: 'community', label: 'Communauté' },
    { key: 'sos', label: 'SOS' },
    { key: 'health', label: 'Santé' },
    { key: 'mine', label: 'Mes déclarations' },
];

const categories: Array<{ key: AlertCategory; label: string; icon: string; hint: string }> = [
    { key: 'object', label: 'Objet', icon: '📦', hint: 'Perdu ou trouvé' },
    { key: 'document', label: 'Document', icon: '🪪', hint: 'Pièce ou papier' },
    { key: 'vehicle', label: 'Véhicule', icon: '🚗', hint: 'Volé ou retrouvé' },
    { key: 'person', label: 'Personne', icon: '🧍', hint: 'Disparue ou retrouvée' },
];

const activeTab = ref<AlertTab>('for-you');
const publicAlerts = ref<PublicAlert[]>([]);
const myAlerts = ref<MyAlert[]>([]);
const loading = ref(true);
const submitting = ref(false);
const successMessage = ref('');
const errorMessage = ref('');
const selectedCategory = ref<AlertCategory>('object');
const situation = ref('lost');
const title = ref('');
const description = ref('');
const city = ref('');
const areaLabel = ref('');
const exactLocation = ref('');

const isSos = computed(() => activeTab.value === 'sos');

const statusLabel = (status: string): string => {
    const labels: Record<string, string> = {
        draft: 'Brouillon',
        submitted: 'En vérification',
        published: 'Publiée',
        rejected: 'À corriger',
        resolved: 'Résolue',
    };
    return labels[status] ?? status;
};

const categoryLabel = (category: AlertCategory): string =>
    categories.find((item) => item.key === category)?.label ?? 'SOS';

async function loadPublic(): Promise<void> {
    const { data } = await http.get('/alerts/public', { params: { limit: 20 } });
    publicAlerts.value = data.alerts;
}

async function loadMine(): Promise<void> {
    const { data } = await http.get('/alerts/mine');
    myAlerts.value = data.alerts;
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        await Promise.all([loadPublic(), loadMine()]);
    } finally {
        loading.value = false;
    }
}

function openDeclaration(category: AlertCategory): void {
    selectedCategory.value = category;
    activeTab.value = category === 'sos' ? 'sos' : 'community';
    situation.value = category === 'person' ? 'missing' : category === 'sos' ? 'emergency' : 'lost';
    successMessage.value = '';
    errorMessage.value = '';
}

async function submitDeclaration(): Promise<void> {
    submitting.value = true;
    successMessage.value = '';
    errorMessage.value = '';

    try {
        await http.post('/alerts', {
            category: isSos.value ? 'sos' : selectedCategory.value,
            situation: isSos.value ? 'emergency' : situation.value,
            title: title.value,
            description: description.value,
            city: city.value || null,
            area_label: areaLabel.value || null,
            exact_location: exactLocation.value || null,
        });
        title.value = '';
        description.value = '';
        city.value = '';
        areaLabel.value = '';
        exactLocation.value = '';
        successMessage.value = isSos.value
            ? 'Votre SOS a été enregistré. Wasplex le traite comme une priorité de protection, sans paiement.'
            : 'Votre déclaration a été envoyée pour vérification avant diffusion publique.';
        await loadMine();
    } catch {
        errorMessage.value = 'Impossible d’envoyer la déclaration. Vérifiez les informations puis réessayez.';
    } finally {
        submitting.value = false;
    }
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4 pb-4">
        <section
            class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 rounded-wpx-xl border-wpx-border-dark overflow-hidden border bg-gradient-to-br shadow-xl"
        >
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="mb-2 flex items-center gap-2">
                            <span class="bg-wpx-danger/15 flex h-9 w-9 items-center justify-center rounded-full text-lg"
                                >🔔</span
                            >
                            <span class="text-wpx-gold text-[10px] font-black tracking-[0.18em] uppercase"
                                >Alertes Wasplex</span
                            >
                        </div>
                        <h1 class="text-wpx-white-soft text-xl leading-tight font-black">
                            Aider, retrouver, protéger.
                        </h1>
                        <p class="text-wpx-muted-dark mt-1.5 max-w-xs text-xs leading-relaxed">
                            Signalez simplement ce qui compte. Vos informations privées restent protégées.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="bg-wpx-danger rounded-full px-3 py-2 text-[11px] font-black text-white shadow-lg"
                        @click="openDeclaration('sos')"
                    >
                        SOS
                    </button>
                </div>
            </div>
        </section>

        <nav class="-mx-1 flex scrollbar-none gap-2 overflow-x-auto px-1 pb-1" aria-label="Sections Alertes">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="shrink-0 rounded-full border px-3 py-2 text-[11px] font-bold transition"
                :class="
                    activeTab === tab.key
                        ? 'border-wpx-gold bg-wpx-gold/12 text-wpx-gold'
                        : 'border-wpx-border-dark bg-wpx-navy-850 text-wpx-muted-dark'
                "
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </nav>

        <template v-if="activeTab === 'for-you'">
            <section class="grid grid-cols-2 gap-2.5">
                <button
                    v-for="category in categories"
                    :key="category.key"
                    type="button"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5 text-left shadow-sm"
                    @click="openDeclaration(category.key)"
                >
                    <span class="bg-wpx-blue/10 flex h-10 w-10 items-center justify-center rounded-xl text-xl">{{
                        category.icon
                    }}</span>
                    <p class="text-wpx-white-soft mt-2.5 text-sm font-black">{{ category.label }}</p>
                    <p class="text-wpx-muted-dark mt-0.5 text-[10px]">{{ category.hint }}</p>
                </button>
            </section>

            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-wpx-white-soft text-sm font-black">Récentes dans la communauté</p>
                        <p class="text-wpx-muted-dark text-[10px]">Uniquement les alertes publiées et sûres</p>
                    </div>
                    <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2 py-1 text-[9px] font-black"
                        >EN COURS</span
                    >
                </div>
                <div v-if="loading" class="text-wpx-muted-dark py-5 text-center text-xs">Chargement…</div>
                <div
                    v-else-if="publicAlerts.length === 0"
                    class="text-wpx-muted-dark rounded-xl bg-white/3 p-4 text-center text-xs"
                >
                    Aucune alerte publique active pour le moment.
                </div>
                <div v-else class="flex flex-col gap-2">
                    <article
                        v-for="alert in publicAlerts.slice(0, 6)"
                        :key="alert.id"
                        class="border-wpx-border-dark rounded-xl border bg-black/10 p-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-xs font-black">{{ alert.title }}</p>
                                <p class="text-wpx-muted-dark mt-1 line-clamp-2 text-[10px] leading-relaxed">
                                    {{ alert.summary }}
                                </p>
                            </div>
                            <span class="text-wpx-gold text-[9px] font-black">{{ alert.priority }}</span>
                        </div>
                        <p class="text-wpx-cyan mt-2 text-[9px] font-bold">
                            {{ alert.area_label || alert.city || 'Zone protégée' }}
                        </p>
                    </article>
                </div>
            </section>
        </template>

        <template v-else-if="activeTab === 'community' || activeTab === 'sos'">
            <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4 shadow-xl">
                <div class="mb-4">
                    <p class="text-wpx-white-soft text-base font-black">
                        {{ isSos ? 'Signaler une urgence' : 'Nouvelle déclaration' }}
                    </p>
                    <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                        {{
                            isSos
                                ? 'Décrivez le danger avec des mots simples. Une urgence vitale ne dépend jamais d’un paiement.'
                                : 'Quelques informations suffisent. Wasplex sépare ce qui reste privé de ce qui peut être montré à la communauté.'
                        }}
                    </p>
                </div>

                <div v-if="!isSos" class="mb-4 grid grid-cols-4 gap-2">
                    <button
                        v-for="category in categories"
                        :key="category.key"
                        type="button"
                        class="rounded-xl border p-2 text-center"
                        :class="
                            selectedCategory === category.key
                                ? 'border-wpx-gold bg-wpx-gold/10'
                                : 'border-wpx-border-dark bg-black/10'
                        "
                        @click="selectedCategory = category.key"
                    >
                        <span class="block text-lg">{{ category.icon }}</span>
                        <span class="text-wpx-white-soft mt-1 block text-[9px] font-bold">{{ category.label }}</span>
                    </button>
                </div>

                <div class="flex flex-col gap-3">
                    <select
                        v-if="!isSos"
                        v-model="situation"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs"
                    >
                        <option value="lost">J’ai perdu</option>
                        <option value="found">J’ai trouvé</option>
                        <option value="stolen">A été volé</option>
                        <option value="missing">Personne disparue</option>
                        <option value="other">Autre situation</option>
                    </select>
                    <input
                        v-model="title"
                        type="text"
                        maxlength="140"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark rounded-xl border px-3 py-3 text-xs"
                        :placeholder="isSos ? 'Ex. Accident grave à Abobo' : 'Un titre simple et précis'"
                    />
                    <textarea
                        v-model="description"
                        rows="4"
                        maxlength="4000"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark rounded-xl border px-3 py-3 text-xs leading-relaxed"
                        placeholder="Que s’est-il passé ? Donnez les détails utiles."
                    />
                    <div class="grid grid-cols-2 gap-2">
                        <input
                            v-model="city"
                            type="text"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark rounded-xl border px-3 py-3 text-xs"
                            placeholder="Ville"
                        />
                        <input
                            v-model="areaLabel"
                            type="text"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark rounded-xl border px-3 py-3 text-xs"
                            placeholder="Quartier / zone"
                        />
                    </div>
                    <div>
                        <input
                            v-model="exactLocation"
                            type="text"
                            class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-xl border px-3 py-3 text-xs"
                            placeholder="Position exacte (facultatif et privée)"
                        />
                        <p class="text-wpx-muted-dark mt-1.5 px-1 text-[9px]">
                            🔒 Cette information n’est jamais publiée automatiquement.
                        </p>
                    </div>
                </div>

                <p
                    v-if="successMessage"
                    class="text-wpx-success-light mt-3 rounded-xl bg-emerald-500/10 p-3 text-[10px] font-semibold"
                >
                    {{ successMessage }}
                </p>
                <p
                    v-if="errorMessage"
                    class="text-wpx-danger mt-3 rounded-xl bg-red-500/10 p-3 text-[10px] font-semibold"
                >
                    {{ errorMessage }}
                </p>

                <button
                    type="button"
                    class="mt-4 w-full rounded-xl py-3 text-xs font-black shadow-lg"
                    :class="
                        isSos
                            ? 'bg-wpx-danger text-white'
                            : 'from-wpx-orange to-wpx-gold text-wpx-navy-950 bg-gradient-to-r'
                    "
                    :disabled="submitting"
                    @click="submitDeclaration"
                >
                    {{ submitting ? 'Envoi…' : isSos ? 'Envoyer le SOS' : 'Envoyer pour vérification' }}
                </button>
            </section>
        </template>

        <template v-else-if="activeTab === 'health'">
            <HealthPanel />
        </template>

        <template v-else>
            <section class="flex flex-col gap-2">
                <article
                    v-for="alert in myAlerts"
                    :key="alert.id"
                    class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-3.5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-wpx-white-soft truncate text-sm font-black">{{ alert.title }}</p>
                            <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                {{ categoryLabel(alert.category) }} ·
                                {{ alert.area_label || alert.city || 'Zone non précisée' }}
                            </p>
                        </div>
                        <span
                            class="bg-wpx-blue/10 text-wpx-blue shrink-0 rounded-full px-2 py-1 text-[9px] font-black"
                            >{{ statusLabel(alert.status) }}</span
                        >
                    </div>
                    <div
                        v-if="alert.status === 'rejected' && alert.rejection_reason"
                        class="border-wpx-danger/20 bg-wpx-danger/8 mt-3 rounded-xl border px-3 py-2.5"
                    >
                        <p class="text-wpx-danger text-[9px] font-black tracking-wide uppercase">À corriger</p>
                        <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                            {{ alert.rejection_reason }}
                        </p>
                    </div>
                </article>
                <div
                    v-if="!loading && myAlerts.length === 0"
                    class="text-wpx-muted-dark bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg border p-5 text-center text-xs"
                >
                    Vous n’avez encore aucune déclaration.
                </div>
            </section>
        </template>
    </div>
</template>
