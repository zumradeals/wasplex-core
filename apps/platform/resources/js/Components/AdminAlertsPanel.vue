<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

interface AdminAlert {
    id: string;
    account_id: string;
    category: string;
    situation: string;
    priority: string;
    status: string;
    title: string;
    description: string;
    city: string | null;
    area_label: string | null;
    submitted_at: string | null;
    published_at: string | null;
}

interface Tip {
    title: string;
    body: string;
}

const alerts = ref<AdminAlert[]>([]);
const selected = ref<AdminAlert | null>(null);
const loading = ref(true);
const saving = ref(false);
const notice = ref('');
const error = ref('');
const publicSummary = ref('');
const expiresInHours = ref(168);
const usefulContentInterval = ref(5);
const circlesEnabled = ref(true);
const leftRailEnabled = ref(true);
const fullScreenEnabled = ref(true);
const tips = ref<Tip[]>([]);

const submittedAlerts = computed(() => alerts.value.filter((alert) => alert.status === 'submitted'));
const publishedAlerts = computed(() => alerts.value.filter((alert) => alert.status === 'published'));

function categoryLabel(category: string): string {
    return {
        object: 'Objet',
        document: 'Document',
        vehicle: 'Véhicule',
        person: 'Personne',
        sos: 'SOS',
    }[category] ?? category;
}

function choose(alert: AdminAlert): void {
    selected.value = alert;
    publicSummary.value = alert.description;
    notice.value = '';
    error.value = '';
}

async function load(): Promise<void> {
    loading.value = true;
    try {
        const [alertsResponse, settingsResponse] = await Promise.all([
            http.get('/admin/alerts'),
            http.get('/alerts/feed-configuration'),
        ]);
        alerts.value = alertsResponse.data.alerts;
        const config = settingsResponse.data.configuration;
        usefulContentInterval.value = config.useful_content_interval;
        circlesEnabled.value = config.circles_enabled;
        leftRailEnabled.value = config.left_rail_enabled;
        fullScreenEnabled.value = config.full_screen_enabled;
        tips.value = config.tips ?? [];
    } finally {
        loading.value = false;
    }
}

async function publish(): Promise<void> {
    if (!selected.value) return;
    saving.value = true;
    notice.value = '';
    error.value = '';
    try {
        await http.post(`/admin/alerts/${selected.value.id}/publish`, {
            public_summary: publicSummary.value,
            expires_in_hours: expiresInHours.value,
        });
        notice.value = 'Alerte publiée dans les surfaces publiques.';
        selected.value = null;
        await load();
    } catch {
        error.value = 'Publication impossible. Vérifiez le dossier et votre session MFA.';
    } finally {
        saving.value = false;
    }
}

async function reject(): Promise<void> {
    if (!selected.value) return;
    saving.value = true;
    notice.value = '';
    error.value = '';
    try {
        await http.post(`/admin/alerts/${selected.value.id}/reject`);
        notice.value = 'Alerte retirée de la file de publication.';
        selected.value = null;
        await load();
    } catch {
        error.value = 'Impossible de refuser cette alerte.';
    } finally {
        saving.value = false;
    }
}

async function saveSettings(): Promise<void> {
    saving.value = true;
    notice.value = '';
    error.value = '';
    try {
        await http.put('/admin/alerts/feed-settings', {
            useful_content_interval: usefulContentInterval.value,
            circles_enabled: circlesEnabled.value,
            left_rail_enabled: leftRailEnabled.value,
            full_screen_enabled: fullScreenEnabled.value,
            tips: tips.value.filter((tip) => tip.title.trim() && tip.body.trim()),
        });
        notice.value = 'Diffusion Alertes mise à jour.';
    } catch {
        error.value = 'Impossible d’enregistrer la configuration.';
    } finally {
        saving.value = false;
    }
}

function addTip(): void {
    tips.value.push({ title: '', body: '' });
}

function removeTip(index: number): void {
    tips.value.splice(index, 1);
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-5">
        <section class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-border-dark rounded-wpx-xl border bg-gradient-to-br p-5 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-black tracking-[0.16em] uppercase">Protection & communauté</p>
                    <h2 class="text-wpx-white-soft mt-1 text-xl font-black">Pilotage des Alertes</h2>
                    <p class="text-wpx-muted-dark mt-1 max-w-2xl text-xs leading-relaxed">
                        Vérifiez ce qui peut être montré au public et choisissez le rythme des contenus utiles dans le Feed.
                    </p>
                </div>
                <div class="flex gap-2">
                    <span class="bg-wpx-danger/10 text-wpx-danger rounded-full px-3 py-1.5 text-[10px] font-black">
                        {{ submittedAlerts.length }} à vérifier
                    </span>
                    <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-3 py-1.5 text-[10px] font-black">
                        {{ publishedAlerts.length }} publiées
                    </span>
                </div>
            </div>
        </section>

        <p v-if="notice" class="text-wpx-success-light rounded-wpx-lg bg-emerald-500/10 px-4 py-3 text-xs font-semibold">{{ notice }}</p>
        <p v-if="error" class="text-wpx-danger rounded-wpx-lg bg-red-500/10 px-4 py-3 text-xs font-semibold">{{ error }}</p>

        <section class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-wpx-white-soft text-sm font-black">File de vérification</h3>
                        <p class="text-wpx-muted-dark mt-0.5 text-[10px]">Aucune déclaration n’est publiée automatiquement.</p>
                    </div>
                    <button type="button" class="text-wpx-cyan text-[10px] font-black" @click="load">Rafraîchir</button>
                </div>

                <div v-if="loading" class="text-wpx-muted-dark py-10 text-center text-xs">Chargement…</div>
                <div v-else-if="submittedAlerts.length === 0" class="text-wpx-muted-dark rounded-2xl bg-black/10 p-5 text-center text-xs">
                    Aucune alerte en attente de vérification.
                </div>
                <div v-else class="flex max-h-[540px] flex-col gap-2 overflow-y-auto pr-1">
                    <button
                        v-for="alert in submittedAlerts"
                        :key="alert.id"
                        type="button"
                        class="border-wpx-border-dark rounded-2xl border p-3.5 text-left transition"
                        :class="selected?.id === alert.id ? 'bg-wpx-cyan/8 border-wpx-cyan/40' : 'bg-black/10 hover:bg-white/4'"
                        @click="choose(alert)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-xs font-black">{{ alert.title }}</p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px]">
                                    {{ categoryLabel(alert.category) }} · {{ alert.area_label || alert.city || 'Zone non précisée' }}
                                </p>
                            </div>
                            <span class="text-wpx-gold text-[9px] font-black">{{ alert.priority }}</span>
                        </div>
                    </button>
                </div>
            </div>

            <div class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-4">
                <template v-if="selected">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-wpx-gold text-[9px] font-black">{{ selected.priority }} · {{ categoryLabel(selected.category) }}</p>
                            <h3 class="text-wpx-white-soft mt-1 text-base font-black">{{ selected.title }}</h3>
                        </div>
                        <button type="button" class="text-wpx-muted-dark text-lg" @click="selected = null">×</button>
                    </div>
                    <p class="text-wpx-muted-dark mt-3 text-xs leading-relaxed">{{ selected.description }}</p>
                    <div class="border-wpx-border-dark mt-4 rounded-2xl border bg-black/10 p-3">
                        <p class="text-wpx-muted-dark text-[9px] font-black tracking-wide uppercase">Zone publique</p>
                        <p class="text-wpx-white-soft mt-1 text-xs">{{ selected.area_label || selected.city || 'Non précisée' }}</p>
                        <p class="text-wpx-muted-dark mt-2 text-[9px]">La position exacte privée n’est pas exposée dans cet écran.</p>
                    </div>
                    <label class="text-wpx-muted-dark mt-4 block text-[10px] font-bold">Résumé public</label>
                    <textarea
                        v-model="publicSummary"
                        rows="5"
                        maxlength="1200"
                        class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-2xl border px-3 py-3 text-xs leading-relaxed"
                    />
                    <label class="text-wpx-muted-dark mt-3 block text-[10px] font-bold">Durée de visibilité</label>
                    <select v-model="expiresInHours" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-2.5 text-xs">
                        <option :value="24">24 heures</option>
                        <option :value="72">3 jours</option>
                        <option :value="168">7 jours</option>
                        <option :value="336">14 jours</option>
                        <option :value="720">30 jours</option>
                    </select>
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <button type="button" class="border-wpx-danger/40 text-wpx-danger rounded-xl border py-3 text-xs font-black" :disabled="saving" @click="reject">Refuser</button>
                        <button type="button" class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r py-3 text-xs font-black" :disabled="saving" @click="publish">Publier</button>
                    </div>
                </template>
                <div v-else class="text-wpx-muted-dark flex min-h-72 flex-col items-center justify-center text-center text-xs">
                    <span class="mb-3 text-3xl">🔎</span>
                    Sélectionnez une déclaration pour vérifier sa projection publique.
                </div>
            </div>
        </section>

        <section class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-wpx-white-soft text-base font-black">Rythme du Feed utile</h3>
                    <p class="text-wpx-muted-dark mt-1 max-w-xl text-[11px] leading-relaxed">
                        Exemple : avec 5, Wasplex peut afficher une alerte ou une astuce après cinq publicités réellement complétées.
                    </p>
                </div>
                <div class="bg-wpx-navy-950 border-wpx-border-dark rounded-2xl border px-4 py-3 text-center">
                    <p class="text-wpx-gold text-xl font-black">{{ usefulContentInterval }}</p>
                    <p class="text-wpx-muted-dark text-[9px]">publicités</p>
                </div>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="bg-wpx-navy-950 border-wpx-border-dark rounded-2xl border p-3">
                    <span class="text-wpx-white-soft block text-xs font-black">Cadence</span>
                    <select v-model="usefulContentInterval" class="border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border bg-black/20 px-3 py-2 text-xs">
                        <option :value="3">Toutes les 3 pubs</option>
                        <option :value="5">Toutes les 5 pubs</option>
                        <option :value="10">Toutes les 10 pubs</option>
                        <option :value="15">Toutes les 15 pubs</option>
                    </select>
                </label>
                <label class="bg-wpx-navy-950 border-wpx-border-dark flex items-center justify-between rounded-2xl border p-3">
                    <span><span class="text-wpx-white-soft block text-xs font-black">Cercles</span><span class="text-wpx-muted-dark text-[9px]">En haut du Feed</span></span>
                    <input v-model="circlesEnabled" type="checkbox" class="h-5 w-5" />
                </label>
                <label class="bg-wpx-navy-950 border-wpx-border-dark flex items-center justify-between rounded-2xl border p-3">
                    <span><span class="text-wpx-white-soft block text-xs font-black">Rail gauche</span><span class="text-wpx-muted-dark text-[9px]">Face aux actions sociales</span></span>
                    <input v-model="leftRailEnabled" type="checkbox" class="h-5 w-5" />
                </label>
                <label class="bg-wpx-navy-950 border-wpx-border-dark flex items-center justify-between rounded-2xl border p-3">
                    <span><span class="text-wpx-white-soft block text-xs font-black">Plein écran</span><span class="text-wpx-muted-dark text-[9px]">Contenu utile entre pubs</span></span>
                    <input v-model="fullScreenEnabled" type="checkbox" class="h-5 w-5" />
                </label>
            </div>

            <div class="border-wpx-border-dark mt-5 border-t pt-5">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-wpx-white-soft text-sm font-black">Astuces Wasplex</p>
                        <p class="text-wpx-muted-dark text-[10px]">Messages non récompensés alternés avec les alertes.</p>
                    </div>
                    <button type="button" class="text-wpx-cyan text-[10px] font-black" @click="addTip">+ Ajouter</button>
                </div>
                <div class="grid gap-2 lg:grid-cols-2">
                    <div v-for="(tip, index) in tips" :key="index" class="bg-wpx-navy-950 border-wpx-border-dark rounded-2xl border p-3">
                        <div class="flex gap-2">
                            <input v-model="tip.title" maxlength="120" class="border-wpx-border-dark text-wpx-white-soft min-w-0 flex-1 rounded-xl border bg-black/20 px-3 py-2 text-xs" placeholder="Titre de l’astuce" />
                            <button type="button" class="text-wpx-danger px-2 text-xs" @click="removeTip(index)">×</button>
                        </div>
                        <textarea v-model="tip.body" rows="2" maxlength="500" class="border-wpx-border-dark text-wpx-white-soft mt-2 w-full rounded-xl border bg-black/20 px-3 py-2 text-xs" placeholder="Conseil simple et utile" />
                    </div>
                </div>
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-xl bg-gradient-to-r px-5 py-3 text-xs font-black" :disabled="saving" @click="saveSettings">
                    {{ saving ? 'Enregistrement…' : 'Enregistrer la diffusion' }}
                </button>
            </div>
        </section>
    </div>
</template>
