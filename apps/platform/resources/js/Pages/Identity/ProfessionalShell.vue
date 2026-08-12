<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import SpaceSwitcher from '@/Components/SpaceSwitcher.vue';
import type { AuthShared } from '@/types/identity';

type Workspace = {
    space: {
        id: string;
        organization_id: string;
        organization_name: string | null;
        space_kind: string;
        verification_status: string;
        territory: Record<string, string> | null;
        purposes: string[];
        restrictions: string[] | Record<string, unknown>;
        verified_at: string | null;
    };
    my_roles: Array<{ id: string; role_code: string; scope: Record<string, unknown> | null }>;
    members: Array<{ membership_id: string; account_id: string; title: string | null; joined_at: string | null }>;
    service_points: Array<{
        id: string;
        name: string;
        type: string | null;
        country_code: string;
        city: string | null;
        area_label: string | null;
        address: string | null;
        status: string;
    }>;
};

type Tab = 'overview' | 'cases' | 'operations' | 'team' | 'points' | 'audit';

const page = usePage<{ auth: AuthShared }>();
const activeTab = ref<Tab>('overview');
const workspace = ref<Workspace | null>(null);
const loading = ref(true);
const errorMessage = ref('');
const showPointForm = ref(false);
const savingPoint = ref(false);
const pointName = ref('');
const pointType = ref('');
const pointCity = ref('');
const pointArea = ref('');
const pointAddress = ref('');

const tabs: Array<{ key: Tab; label: string; icon: string }> = [
    { key: 'overview', label: 'Vue d’ensemble', icon: '⌂' },
    { key: 'cases', label: 'Dossiers', icon: '▤' },
    { key: 'operations', label: 'Opérations', icon: '↻' },
    { key: 'team', label: 'Équipe', icon: '◎' },
    { key: 'points', label: 'Points de service', icon: '⌖' },
    { key: 'audit', label: 'Audit', icon: '✓' },
];

const kindLabel = computed(() => {
    const labels: Record<string, string> = {
        security_institution: 'Institution de sécurité',
        healthcare_institution: 'Établissement de Santé',
        health_professional: 'Professionnel de Santé',
        service_provider: 'Prestataire',
        partner: 'Partenaire',
        merchant: 'Commerce / point de vente',
        financial_operator: 'Opérateur financier',
        field_agent: 'Agent de terrain',
    };
    return labels[workspace.value?.space.space_kind ?? ''] ?? 'Espace professionnel';
});

const territoryLabel = computed(() => {
    const territory = workspace.value?.space.territory;
    if (!territory) return 'Territoire non précisé';
    return [territory.area_label, territory.city, territory.country_code].filter(Boolean).join(' · ') || 'Territoire non précisé';
});

const myRoleLabels = computed(() => workspace.value?.my_roles.map((role) => role.role_code.replaceAll('_', ' ')) ?? []);

const contextualReadiness = computed(() => {
    const kind = workspace.value?.space.space_kind;
    if (kind === 'security_institution') {
        return {
            title: 'Raccordement Alertes',
            text: 'L’espace est prêt à recevoir les projections institutionnelles. Aucun signalement n’est affiché tant que le raccordement P015 institutionnel n’est pas activé.',
        };
    }
    if (kind === 'healthcare_institution' || kind === 'health_professional') {
        return {
            title: 'Raccordement Santé',
            text: 'L’espace est prêt pour les accès Santé autorisés. Aucun dossier médical n’est ouvert automatiquement et aucune capsule n’est consultable sans le flux d’autorisation prévu.',
        };
    }
    return {
        title: 'Espace opérationnel',
        text: 'Les capacités métiers seront activées progressivement selon les modules Wasplex autorisés pour votre organisation.',
    };
});

async function load(): Promise<void> {
    loading.value = true;
    errorMessage.value = '';
    try {
        const { data } = await http.get('/professional/workspace');
        workspace.value = data;
    } catch {
        errorMessage.value = 'Cet espace n’est pas disponible ou n’est plus autorisé.';
    } finally {
        loading.value = false;
    }
}

async function addServicePoint(): Promise<void> {
    savingPoint.value = true;
    errorMessage.value = '';
    try {
        await http.post('/professional/service-points', {
            name: pointName.value,
            type: pointType.value || null,
            country_code: workspace.value?.space.territory?.country_code || 'CI',
            city: pointCity.value || null,
            area_label: pointArea.value || null,
            address: pointAddress.value || null,
        });
        pointName.value = '';
        pointType.value = '';
        pointCity.value = '';
        pointArea.value = '';
        pointAddress.value = '';
        showPointForm.value = false;
        await load();
        activeTab.value = 'points';
    } catch {
        errorMessage.value = 'Impossible d’ajouter ce point de service.';
    } finally {
        savingPoint.value = false;
    }
}

function backToPersonal(): void {
    const personal = page.props.auth.spaces.find((space) => space.space_type === 'user');
    if (!personal) {
        router.visit('/app');
        return;
    }

    http.post(`/me/spaces/${personal.user_space_id}/switch`)
        .then(() => router.visit('/app'))
        .catch(() => router.visit('/app'));
}

onMounted(load);
</script>

<template>
    <div class="bg-wpx-navy-950 min-h-screen text-white">
        <header class="border-wpx-border-dark bg-wpx-navy-850/95 sticky top-0 z-30 border-b backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 lg:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="/brand/wasplex-logo-transparent.png" alt="Wasplex" class="h-8 w-8 object-contain" />
                    <div class="min-w-0">
                        <p class="text-wpx-cyan text-[9px] font-black tracking-[0.18em] uppercase">Wasplex Pro</p>
                        <p class="text-wpx-white-soft truncate text-sm font-black">
                            {{ workspace?.space.organization_name || 'Espace professionnel' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <SpaceSwitcher
                        variant="dark"
                        :spaces="page.props.auth.spaces"
                        :active-space-id="page.props.auth.active_space_id"
                    />
                    <button
                        type="button"
                        class="border-wpx-border-dark text-wpx-muted-dark hidden rounded-xl border px-3 py-2 text-[10px] font-bold sm:block"
                        @click="backToPersonal"
                    >
                        Mon espace perso
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto grid max-w-7xl gap-5 px-4 py-5 lg:grid-cols-[220px_1fr] lg:px-6">
            <aside class="hidden lg:block">
                <div class="border-wpx-border-dark bg-wpx-navy-850 sticky top-20 rounded-3xl border p-3">
                    <nav class="space-y-1">
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl px-3 py-3 text-left text-xs font-bold transition"
                            :class="activeTab === tab.key ? 'bg-wpx-cyan/12 text-wpx-cyan' : 'text-wpx-muted-dark hover:bg-white/5'"
                            @click="activeTab = tab.key"
                        >
                            <span class="flex h-8 w-8 items-center justify-center rounded-xl bg-black/15 text-base">{{ tab.icon }}</span>
                            {{ tab.label }}
                        </button>
                    </nav>
                </div>
            </aside>

            <main class="min-w-0 pb-24 lg:pb-8">
                <div v-if="loading" class="text-wpx-muted-dark py-16 text-center text-sm">Chargement de l’espace…</div>

                <div v-else-if="errorMessage && !workspace" class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-6 text-center">
                    <p class="text-red-300 text-sm font-bold">{{ errorMessage }}</p>
                    <button type="button" class="text-wpx-cyan mt-4 text-xs font-black" @click="backToPersonal">Retour à Mon Espace</button>
                </div>

                <template v-else-if="workspace">
                    <section
                        class="from-wpx-navy-750 via-wpx-navy-850 to-wpx-navy-950 border-wpx-cyan/20 rounded-3xl border bg-gradient-to-br p-5 shadow-xl"
                    >
                        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                            <div class="min-w-0">
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <span class="bg-emerald-500/12 rounded-full px-2.5 py-1 text-[9px] font-black text-emerald-300">VÉRIFIÉ</span>
                                    <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2.5 py-1 text-[9px] font-black">{{ kindLabel }}</span>
                                </div>
                                <h1 class="text-wpx-white-soft text-xl font-black md:text-2xl">{{ workspace.space.organization_name }}</h1>
                                <p class="text-wpx-muted-dark mt-1 text-xs">{{ territoryLabel }}</p>
                                <p v-if="myRoleLabels.length" class="text-wpx-muted-dark mt-2 text-[10px] capitalize">
                                    Votre rôle : {{ myRoleLabels.join(' · ') }}
                                </p>
                            </div>
                            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                <div class="bg-black/10 rounded-2xl px-4 py-3 text-center">
                                    <p class="text-wpx-white-soft text-lg font-black">{{ workspace.members.length }}</p>
                                    <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">Équipe</p>
                                </div>
                                <div class="bg-black/10 rounded-2xl px-4 py-3 text-center">
                                    <p class="text-wpx-white-soft text-lg font-black">{{ workspace.service_points.length }}</p>
                                    <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">Points</p>
                                </div>
                                <div class="bg-black/10 col-span-2 rounded-2xl px-4 py-3 text-center sm:col-span-1">
                                    <p class="text-emerald-300 text-sm font-black">Actif</p>
                                    <p class="text-wpx-muted-dark text-[8px] font-bold uppercase">Accès</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <p v-if="errorMessage" class="mt-3 rounded-2xl bg-red-500/10 px-4 py-3 text-xs text-red-300">{{ errorMessage }}</p>

                    <section v-if="activeTab === 'overview'" class="mt-4 grid gap-4 xl:grid-cols-2">
                        <article class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-5">
                            <p class="text-wpx-white-soft text-sm font-black">{{ contextualReadiness.title }}</p>
                            <p class="text-wpx-muted-dark mt-2 text-[11px] leading-relaxed">{{ contextualReadiness.text }}</p>
                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="bg-wpx-cyan/10 text-wpx-cyan rounded-2xl px-3 py-3 text-[10px] font-black"
                                    @click="activeTab = 'cases'"
                                >
                                    Voir les dossiers
                                </button>
                                <button
                                    type="button"
                                    class="bg-wpx-blue/10 text-wpx-blue rounded-2xl px-3 py-3 text-[10px] font-black"
                                    @click="activeTab = 'team'"
                                >
                                    Voir l’équipe
                                </button>
                            </div>
                        </article>

                        <article class="border-wpx-border-dark bg-wpx-navy-850 rounded-3xl border p-5">
                            <p class="text-wpx-white-soft text-sm font-black">Périmètre autorisé</p>
                            <p class="text-wpx-muted-dark mt-2 text-[11px]">{{ territoryLabel }}</p>
                            <div v-if="workspace.space.purposes.length" class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="purpose in workspace.space.purposes"
                                    :key="purpose"
                                    class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-muted-dark rounded-full border px-2.5 py-1.5 text-[9px]"
                                >{{ purpose }}</span>
                            </div>
                            <p v-else class="text-wpx-muted-dark mt-3 text-[10px]">Aucune finalité complémentaire déclarée.</p>
                        </article>
                    </section>

                    <section v-else-if="activeTab === 'team'" class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-3xl border p-4 md:p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <p class="text-wpx-white-soft text-sm font-black">Équipe nominative</p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px]">Un compte personnel par agent. Aucun compte générique.</p>
                            </div>
                            <span class="bg-wpx-cyan/10 text-wpx-cyan rounded-full px-2.5 py-1 text-[9px] font-black">{{ workspace.members.length }}</span>
                        </div>
                        <div class="space-y-2">
                            <div
                                v-for="member in workspace.members"
                                :key="member.membership_id"
                                class="border-wpx-border-dark bg-wpx-navy-950/50 flex items-center justify-between rounded-2xl border px-3 py-3"
                            >
                                <div>
                                    <p class="text-wpx-white-soft text-xs font-bold">Agent {{ member.account_id.slice(-6).toUpperCase() }}</p>
                                    <p class="text-wpx-muted-dark mt-0.5 text-[9px] capitalize">{{ member.title || 'membre' }}</p>
                                </div>
                                <span class="text-emerald-300 text-[9px] font-black">ACTIF</span>
                            </div>
                        </div>
                        <p class="text-wpx-muted-dark mt-4 text-[10px] leading-relaxed">
                            Les invitations utilisent le système d’organisation existant. L’attribution fine des rôles reste séparée des identifiants de connexion.
                        </p>
                    </section>

                    <section v-else-if="activeTab === 'points'" class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-3xl border p-4 md:p-5">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <div>
                                <p class="text-wpx-white-soft text-sm font-black">Points de service</p>
                                <p class="text-wpx-muted-dark mt-1 text-[10px]">Guichets, établissements, antennes ou points terrain.</p>
                            </div>
                            <button
                                type="button"
                                class="bg-wpx-cyan/10 text-wpx-cyan rounded-xl px-3 py-2 text-[10px] font-black"
                                @click="showPointForm = !showPointForm"
                            >
                                {{ showPointForm ? 'Fermer' : '+ Ajouter' }}
                            </button>
                        </div>

                        <form v-if="showPointForm" class="mb-4 grid gap-2 rounded-2xl bg-black/10 p-3 md:grid-cols-2" @submit.prevent="addServicePoint">
                            <input v-model="pointName" required placeholder="Nom du point" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs" />
                            <input v-model="pointType" placeholder="Type (antenne, clinique…)" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs" />
                            <input v-model="pointCity" placeholder="Ville" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs" />
                            <input v-model="pointArea" placeholder="Quartier / zone" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs" />
                            <input v-model="pointAddress" placeholder="Adresse" class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft rounded-xl border px-3 py-3 text-xs md:col-span-2" />
                            <button type="submit" :disabled="savingPoint" class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-3 text-xs font-black md:col-span-2 disabled:opacity-50">
                                {{ savingPoint ? 'Enregistrement…' : 'Enregistrer le point' }}
                            </button>
                        </form>

                        <div v-if="workspace.service_points.length" class="grid gap-2 md:grid-cols-2">
                            <article v-for="point in workspace.service_points" :key="point.id" class="border-wpx-border-dark bg-wpx-navy-950/50 rounded-2xl border p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="text-wpx-white-soft text-xs font-black">{{ point.name }}</p>
                                        <p class="text-wpx-muted-dark mt-1 text-[9px]">{{ [point.area_label, point.city].filter(Boolean).join(' · ') || point.country_code }}</p>
                                    </div>
                                    <span class="text-emerald-300 text-[8px] font-black">{{ point.status.toUpperCase() }}</span>
                                </div>
                            </article>
                        </div>
                        <p v-else class="text-wpx-muted-dark py-8 text-center text-xs">Aucun point de service pour le moment.</p>
                    </section>

                    <section v-else class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-3xl border p-6 text-center">
                        <span class="bg-wpx-blue/10 mx-auto flex h-12 w-12 items-center justify-center rounded-2xl text-xl">🔐</span>
                        <p class="text-wpx-white-soft mt-3 text-sm font-black">{{ tabs.find((tab) => tab.key === activeTab)?.label }}</p>
                        <p class="text-wpx-muted-dark mx-auto mt-2 max-w-md text-[11px] leading-relaxed">
                            Cette zone est prête dans l’espace professionnel, mais son contenu métier sera alimenté uniquement par les modules autorisés. Wasplex n’invente aucun dossier ni aucune intervention.
                        </p>
                    </section>
                </template>
            </main>
        </div>

        <nav class="border-wpx-border-dark bg-wpx-navy-850 fixed right-0 bottom-0 left-0 z-30 grid grid-cols-5 border-t px-1 py-1.5 lg:hidden">
            <button
                v-for="tab in tabs.slice(0, 5)"
                :key="tab.key"
                type="button"
                class="flex flex-col items-center gap-1 py-1.5 text-[9px] font-bold"
                :class="activeTab === tab.key ? 'text-wpx-cyan' : 'text-wpx-muted-dark'"
                @click="activeTab = tab.key"
            >
                <span class="text-base">{{ tab.icon }}</span>
                <span>{{ tab.label === 'Vue d’ensemble' ? 'Accueil' : tab.label.replace('Points de service', 'Points') }}</span>
            </button>
        </nav>
    </div>
</template>
