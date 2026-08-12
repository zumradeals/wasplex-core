<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';

type Space = {
    id: string;
    organization_id: string;
    organization_name: string | null;
    space_kind: string;
    verification_status: string;
    legal_name: string;
    trade_name: string | null;
    country_code: string;
    territory: Record<string, string> | null;
    purposes: string[];
    restrictions: Record<string, unknown> | string[];
    review_note: string | null;
    submitted_at: string | null;
    reviewed_at: string | null;
    verified_at: string | null;
    space_status: string | null;
    registration_number?: string | null;
    roles?: Array<{ id: string; account_id: string; role_code: string; status: string }>;
    service_points?: Array<{
        id: string;
        name: string;
        type: string | null;
        city: string | null;
        area_label: string | null;
        status: string;
    }>;
};

const statuses = [
    ['pending_verification', 'À vérifier'],
    ['verified', 'Vérifiés'],
    ['restricted', 'Restreints'],
    ['rejected', 'Refusés'],
    ['suspended', 'Suspendus'],
] as const;

const activeStatus = ref('pending_verification');
const spaces = ref<Space[]>([]);
const selected = ref<Space | null>(null);
const loading = ref(true);
const loadingDetail = ref(false);
const saving = ref(false);
const errorMessage = ref('');
const reason = ref('');

const kindLabels: Record<string, string> = {
    security_institution: 'Institution de sécurité',
    healthcare_institution: 'Établissement de Santé',
    health_professional: 'Professionnel de Santé',
    service_provider: 'Prestataire',
    partner: 'Partenaire',
    merchant: 'Commerce / point de vente',
    financial_operator: 'Opérateur financier',
    field_agent: 'Agent de terrain',
};

const selectedTerritory = computed(() => {
    const territory = selected.value?.territory;
    if (!territory) return 'Non précisé';
    return [territory.area_label, territory.city, territory.country_code].filter(Boolean).join(' · ') || 'Non précisé';
});

function labelKind(kind: string): string {
    return kindLabels[kind] ?? kind;
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value));
}

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await http.get('/admin/professional-spaces', { params: { status: activeStatus.value } });
        spaces.value = data.spaces ?? [];
        if (selected.value && !spaces.value.some((space) => space.id === selected.value?.id)) selected.value = null;
    } catch {
        errorMessage.value = 'Impossible de charger les demandes professionnelles.';
    } finally {
        loading.value = false;
    }
}

async function openSpace(space: Space): Promise<void> {
    loadingDetail.value = true;
    errorMessage.value = '';
    try {
        const { data } = await http.get(`/admin/professional-spaces/${space.id}`);
        selected.value = data.space;
        reason.value = data.space.review_note ?? '';
    } catch {
        errorMessage.value = 'Impossible d’ouvrir ce dossier.';
    } finally {
        loadingDetail.value = false;
    }
}

async function decide(decision: 'verify' | 'restrict' | 'reject' | 'suspend'): Promise<void> {
    if (!selected.value) return;
    if (decision !== 'verify' && !reason.value.trim()) {
        errorMessage.value = 'Expliquez le motif avant de restreindre, refuser ou suspendre.';
        return;
    }

    saving.value = true;
    errorMessage.value = '';
    try {
        await http.post(`/admin/professional-spaces/${selected.value.id}/decision`, {
            decision,
            reason: reason.value.trim() || null,
        });
        selected.value = null;
        reason.value = '';
        await load();
    } catch {
        errorMessage.value = 'La décision n’a pas pu être enregistrée.';
    } finally {
        saving.value = false;
    }
}

async function changeStatus(status: string): Promise<void> {
    activeStatus.value = status;
    selected.value = null;
    await load();
}

onMounted(load);
</script>

<template>
    <div class="flex flex-col gap-4">
        <section
            class="border-wpx-border-dark from-wpx-navy-750 to-wpx-navy-850 rounded-wpx-xl border bg-gradient-to-br p-5"
        >
            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[10px] font-black tracking-[0.18em] uppercase">
                        P019 · Confiance institutionnelle
                    </p>
                    <h2 class="text-wpx-white-soft mt-1 text-xl font-black">
                        Espaces professionnels &amp; institutions
                    </h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-xs leading-relaxed">
                        Vérifiez l’organisation, son territoire et sa finalité avant d’ouvrir tout accès métier
                        sensible. Aucun compte partagé n’est accepté.
                    </p>
                </div>
                <div class="border-wpx-border-dark bg-wpx-navy-950/60 rounded-2xl border px-4 py-3 text-center">
                    <p class="text-wpx-white-soft text-xl font-black">{{ spaces.length }}</p>
                    <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">dans cette file</p>
                </div>
            </div>
        </section>

        <nav class="border-wpx-border-dark bg-wpx-navy-850 flex gap-2 overflow-x-auto rounded-2xl border p-2">
            <button
                v-for="status in statuses"
                :key="status[0]"
                type="button"
                class="shrink-0 rounded-xl px-3 py-2.5 text-[10px] font-black"
                :class="activeStatus === status[0] ? 'bg-wpx-white-soft text-wpx-navy-950' : 'text-wpx-muted-dark'"
                @click="changeStatus(status[0])"
            >
                {{ status[1] }}
            </button>
        </nav>

        <p v-if="errorMessage" class="rounded-2xl bg-red-500/10 px-4 py-3 text-xs text-red-300">{{ errorMessage }}</p>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,0.8fr)_minmax(420px,1.2fr)]">
            <section class="border-wpx-border-dark bg-wpx-navy-850 min-h-80 rounded-3xl border p-3">
                <div v-if="loading" class="text-wpx-muted-dark py-12 text-center text-xs">Chargement…</div>
                <div v-else-if="spaces.length === 0" class="text-wpx-muted-dark py-12 text-center text-xs">
                    Aucun dossier dans cette file.
                </div>
                <div v-else class="space-y-2">
                    <button
                        v-for="space in spaces"
                        :key="space.id"
                        type="button"
                        class="border-wpx-border-dark w-full rounded-2xl border p-3 text-left transition"
                        :class="
                            selected?.id === space.id
                                ? 'bg-wpx-cyan/10 border-wpx-cyan/30'
                                : 'bg-wpx-navy-950/45 hover:bg-white/5'
                        "
                        @click="openSpace(space)"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-wpx-white-soft truncate text-xs font-black">
                                    {{ space.trade_name || space.legal_name }}
                                </p>
                                <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ labelKind(space.space_kind) }}</p>
                            </div>
                            <span class="bg-wpx-blue/10 text-wpx-blue rounded-full px-2 py-1 text-[8px] font-black">{{
                                space.country_code
                            }}</span>
                        </div>
                        <p class="text-wpx-muted-dark mt-2 text-[9px]">Soumis {{ formatDate(space.submitted_at) }}</p>
                    </button>
                </div>
            </section>

            <section class="border-wpx-border-dark bg-wpx-navy-850 min-h-80 rounded-3xl border p-4 md:p-5">
                <div v-if="loadingDetail" class="text-wpx-muted-dark py-12 text-center text-xs">
                    Ouverture du dossier…
                </div>
                <div
                    v-else-if="!selected"
                    class="flex h-full min-h-72 flex-col items-center justify-center text-center"
                >
                    <span class="bg-wpx-cyan/10 flex h-14 w-14 items-center justify-center rounded-2xl text-2xl"
                        >🏛️</span
                    >
                    <p class="text-wpx-white-soft mt-3 text-sm font-black">Choisissez une organisation</p>
                    <p class="text-wpx-muted-dark mt-2 max-w-sm text-[11px] leading-relaxed">
                        La décision doit porter sur une organisation réelle, un territoire et une finalité identifiés.
                    </p>
                </div>
                <template v-else>
                    <div class="flex flex-col gap-4">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2.5 py-1 text-[9px] font-black"
                                    >{{ labelKind(selected.space_kind) }}</span
                                >
                                <span
                                    class="border-wpx-border-dark text-wpx-muted-dark rounded-full border px-2.5 py-1 text-[9px]"
                                    >{{ selected.verification_status }}</span
                                >
                            </div>
                            <h3 class="text-wpx-white-soft mt-3 text-lg font-black">
                                {{ selected.trade_name || selected.legal_name }}
                            </h3>
                            <p class="text-wpx-muted-dark mt-1 text-[10px]">{{ selected.legal_name }}</p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">
                            <div class="bg-wpx-navy-950/50 rounded-2xl p-3">
                                <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">Territoire</p>
                                <p class="text-wpx-white-soft mt-1 text-xs font-bold">{{ selectedTerritory }}</p>
                            </div>
                            <div class="bg-wpx-navy-950/50 rounded-2xl p-3">
                                <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">Référence officielle</p>
                                <p class="text-wpx-white-soft mt-1 text-xs font-bold break-all">
                                    {{ selected.registration_number || 'Non renseignée' }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">Finalités déclarées</p>
                            <div v-if="selected.purposes.length" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="purpose in selected.purposes"
                                    :key="purpose"
                                    class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-muted-dark rounded-full border px-2.5 py-1.5 text-[9px]"
                                    >{{ purpose }}</span
                                >
                            </div>
                            <p v-else class="text-wpx-muted-dark mt-2 text-[10px]">Aucune finalité complémentaire.</p>
                        </div>

                        <div v-if="selected.roles?.length">
                            <p class="text-wpx-muted-dark text-[9px] font-bold uppercase">Responsables nominatifs</p>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="role in selected.roles"
                                    :key="role.id"
                                    class="border-wpx-border-dark bg-wpx-navy-950/40 flex items-center justify-between rounded-xl border px-3 py-2.5"
                                >
                                    <span class="text-wpx-white-soft text-[10px] font-bold">{{
                                        role.role_code.replaceAll('_', ' ')
                                    }}</span>
                                    <span class="text-wpx-muted-dark font-mono text-[8px]"
                                        >…{{ role.account_id.slice(-6) }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="text-wpx-muted-dark mb-1.5 block text-[9px] font-bold uppercase"
                                >Note / motif de décision</label
                            >
                            <textarea
                                v-model="reason"
                                rows="4"
                                maxlength="1000"
                                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft placeholder:text-wpx-muted-dark w-full rounded-2xl border px-3 py-3 text-xs"
                                placeholder="Obligatoire pour refuser, restreindre ou suspendre."
                            />
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-emerald-500/15 px-3 py-3 text-[10px] font-black text-emerald-300 disabled:opacity-50"
                                @click="decide('verify')"
                            >
                                Vérifier
                            </button>
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-amber-500/12 px-3 py-3 text-[10px] font-black text-amber-300 disabled:opacity-50"
                                @click="decide('restrict')"
                            >
                                Restreindre
                            </button>
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-red-500/10 px-3 py-3 text-[10px] font-black text-red-300 disabled:opacity-50"
                                @click="decide('reject')"
                            >
                                Refuser
                            </button>
                            <button
                                type="button"
                                :disabled="saving"
                                class="border-wpx-border-dark text-wpx-muted-dark rounded-xl border px-3 py-3 text-[10px] font-black disabled:opacity-50"
                                @click="decide('suspend')"
                            >
                                Suspendre
                            </button>
                        </div>

                        <p class="text-wpx-muted-dark text-[9px] leading-relaxed">
                            Vérifier active l’espace et ses capacités de base. Les accès Alertes/Santé restent soumis
                            aux contrôles propres à ces domaines ; cette décision n’ouvre jamais toute la base Wasplex.
                        </p>
                    </div>
                </template>
            </section>
        </div>
    </div>
</template>
