<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import http from '@/lib/http';
import { economicClassLabel } from '@/lib/economicClasses';

interface AccountRow {
    id: string;
    status: string;
    is_restricted: boolean;
    primary_identifier: string;
    created_at: string;
}

interface Organization {
    id: string;
    name: string;
    type: string;
    status: string;
}

interface AccountDetail {
    account: {
        id: string;
        status: string;
        country_code: string | null;
        is_restricted: boolean;
        created_at: string;
        identifiers: string[];
    };
    is_online: boolean;
    wallet_balance_minor: number;
    economic_class_code: string | null;
    advertiser_organization_name: string | null;
    organizations: Organization[];
}

const TABS = ['Vue d’ensemble', 'Wallet', 'Abonnement', 'Organisations', 'Historique'] as const;

const accounts = ref<AccountRow[]>([]);
const searchQuery = ref('');
const selectedDetail = ref<AccountDetail | null>(null);
const activeTab = ref<(typeof TABS)[number]>('Vue d’ensemble');
const loading = ref(true);
const busy = ref(false);
const actionMessage = ref<string | null>(null);

const numberFormatter = new Intl.NumberFormat('fr-FR');

function initials(identifier: string): string {
    const alnum = identifier.replace(/[^a-zA-Z0-9]/g, '');
    return (alnum.slice(-2) || 'WP').toUpperCase();
}

function statusLabel(status: string): string {
    return (
        {
            active: 'Actif',
            restricted: 'Restreint',
            suspended: 'Suspendu',
            draft: 'Brouillon',
            verified: 'Vérifié',
        }[status] ?? status
    );
}

function organizationTypeLabel(type: string): string {
    return (
        {
            advertiser: 'Annonceur',
            business: 'Entreprise',
            individual: 'Activité solo',
            agency: 'Agence',
            institutional_advertiser: 'Institution',
        }[type] ?? type
    );
}

async function search(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/accounts', { params: { q: searchQuery.value || undefined } });
        accounts.value = data.accounts;

        if (accounts.value.length === 0) {
            selectedDetail.value = null;
            return;
        }

        const selectedStillVisible = accounts.value.some((account) => account.id === selectedDetail.value?.account.id);
        if (!selectedStillVisible) {
            await selectAccount(accounts.value[0].id);
        }
    } finally {
        loading.value = false;
    }
}

async function selectAccount(accountId: string): Promise<void> {
    activeTab.value = 'Vue d’ensemble';
    actionMessage.value = null;
    const { data } = await http.get(`/admin/accounts/${accountId}`);
    selectedDetail.value = data;
}

async function toggleRestriction(): Promise<void> {
    if (!selectedDetail.value) return;

    if (!selectedDetail.value.account.is_restricted) {
        const confirmed = window.confirm(
            'Restreindre ce compte ? Cette action protège Wasplex en limitant l’accès du compte jusqu’à sa restauration.',
        );
        if (!confirmed) return;
    }

    busy.value = true;
    actionMessage.value = null;
    try {
        const action = selectedDetail.value.account.is_restricted ? 'unrestrict' : 'restrict';
        const { data } = await http.post(`/admin/accounts/${selectedDetail.value.account.id}/${action}`);
        selectedDetail.value.account.is_restricted = data.account.is_restricted;
        actionMessage.value = data.account.is_restricted ? 'Compte restreint.' : 'Compte restauré.';
        await search();
    } finally {
        busy.value = false;
    }
}

const memberSince = computed(() => {
    if (!selectedDetail.value) return '';
    return new Date(selectedDetail.value.account.created_at).toLocaleDateString('fr-FR', {
        month: 'long',
        year: 'numeric',
    });
});

const primaryIdentifier = computed(
    () => selectedDetail.value?.account.identifiers[0] ?? selectedDetail.value?.account.id ?? 'Compte Wasplex',
);

onMounted(search);
</script>

<template>
    <div class="text-wpx-white-soft flex flex-col gap-5">
        <section class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold tracking-[0.16em] uppercase">Utilisateurs</p>
                    <h2 class="mt-1 text-xl font-extrabold">Comprendre un compte avant d’agir.</h2>
                    <p class="text-wpx-muted-dark mt-1 max-w-2xl text-sm">
                        Recherche une personne par téléphone ou email. Son wallet, son abonnement et ses organisations
                        sont regroupés dans une seule fiche.
                    </p>
                </div>
                <div
                    class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 flex items-center gap-2 border px-3.5 py-2.5 md:w-96"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" class="shrink-0">
                        <circle cx="11" cy="11" r="7" stroke="#A9B7C8" stroke-width="1.8" />
                        <path d="M21 21l-4.3-4.3" stroke="#A9B7C8" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        placeholder="Téléphone ou email…"
                        class="placeholder:text-wpx-muted-dark text-wpx-white-soft min-w-0 flex-1 bg-transparent text-sm outline-none"
                        @keyup.enter="search"
                    />
                    <button type="button" class="text-wpx-cyan text-xs font-extrabold" @click="search">Chercher</button>
                </div>
            </div>
        </section>

        <div class="grid min-h-[520px] grid-cols-1 gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
            <aside
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
            >
                <div class="border-wpx-border-dark flex items-center justify-between border-b px-4 py-3.5">
                    <p class="text-sm font-extrabold">Comptes</p>
                    <span class="text-wpx-muted-dark text-xs">{{ accounts.length }}</span>
                </div>
                <p v-if="loading" class="text-wpx-muted-dark p-4 text-sm">Chargement…</p>
                <div v-else class="max-h-[650px] overflow-y-auto p-2">
                    <button
                        v-for="account in accounts"
                        :key="account.id"
                        type="button"
                        class="rounded-wpx-md flex w-full items-center gap-3 border p-3 text-left transition"
                        :class="
                            selectedDetail?.account.id === account.id
                                ? 'border-wpx-cyan/40 bg-wpx-navy-750'
                                : 'hover:bg-wpx-navy-750/60 border-transparent'
                        "
                        @click="selectAccount(account.id)"
                    >
                        <span
                            class="from-wpx-blue to-wpx-cyan flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xs font-extrabold text-white"
                        >
                            {{ initials(account.primary_identifier) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[13px] font-bold">{{ account.primary_identifier }}</span>
                            <span
                                class="mt-0.5 block text-[11px]"
                                :class="account.is_restricted ? 'text-wpx-danger' : 'text-wpx-muted-dark'"
                            >
                                {{ account.is_restricted ? 'Compte restreint' : statusLabel(account.status) }}
                            </span>
                        </span>
                    </button>
                    <p v-if="accounts.length === 0" class="text-wpx-muted-dark p-4 text-sm">Aucun compte trouvé.</p>
                </div>
            </aside>

            <section v-if="selectedDetail" class="min-w-0">
                <div class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark border p-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-center">
                        <span
                            class="from-wpx-orange to-wpx-gold rounded-wpx-lg text-wpx-navy-950 flex h-16 w-16 shrink-0 items-center justify-center bg-gradient-to-br text-xl font-extrabold"
                        >
                            {{ initials(primaryIdentifier) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate text-xl font-extrabold">{{ primaryIdentifier }}</h3>
                                <span
                                    class="rounded-wpx-full px-2.5 py-1 text-[11px] font-extrabold"
                                    :class="
                                        selectedDetail.account.is_restricted
                                            ? 'bg-wpx-danger/15 text-wpx-danger'
                                            : 'bg-wpx-success/15 text-wpx-success'
                                    "
                                >
                                    {{ selectedDetail.account.is_restricted ? 'Restreint' : 'Actif' }}
                                </span>
                                <span v-if="selectedDetail.is_online" class="text-wpx-success text-[11px] font-bold"
                                    >● En ligne</span
                                >
                            </div>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                {{ selectedDetail.account.country_code ?? 'Pays non renseigné' }} · membre depuis
                                {{ memberSince }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-wpx-md border px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                            :class="
                                selectedDetail.account.is_restricted
                                    ? 'border-wpx-success/50 text-wpx-success'
                                    : 'border-wpx-danger/50 text-wpx-danger'
                            "
                            :disabled="busy"
                            @click="toggleRestriction"
                        >
                            {{
                                selectedDetail.account.is_restricted ? 'Restaurer le compte' : 'Protéger / restreindre'
                            }}
                        </button>
                    </div>

                    <p
                        v-if="actionMessage"
                        class="border-wpx-border-dark rounded-wpx-md bg-wpx-navy-950 text-wpx-muted-dark mt-4 border p-3 text-xs"
                    >
                        {{ actionMessage }}
                    </p>

                    <div class="border-wpx-border-dark mt-5 grid grid-cols-2 gap-3 border-t pt-5 xl:grid-cols-4">
                        <div>
                            <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">Wallet</p>
                            <p class="mt-1 text-lg font-extrabold">
                                {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} WP
                            </p>
                            <p class="text-wpx-muted-dark text-[10px]">
                                ≈ {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} FCFA
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                                Abonnement
                            </p>
                            <p class="mt-1 text-lg font-extrabold">
                                {{
                                    selectedDetail.economic_class_code
                                        ? economicClassLabel(selectedDetail.economic_class_code)
                                        : 'Aucun'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                                Espace annonceur
                            </p>
                            <p class="mt-1 truncate text-lg font-extrabold">
                                {{ selectedDetail.advertiser_organization_name ?? 'Aucun' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                                Organisations
                            </p>
                            <p class="mt-1 text-lg font-extrabold">{{ selectedDetail.organizations.length }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <button
                        v-for="tab in TABS"
                        :key="tab"
                        type="button"
                        class="rounded-wpx-full shrink-0 border px-4 py-2 text-xs font-extrabold"
                        :class="
                            activeTab === tab
                                ? 'border-wpx-cyan bg-wpx-cyan/10 text-wpx-cyan'
                                : 'border-wpx-border-dark text-wpx-muted-dark'
                        "
                        @click="activeTab = tab"
                    >
                        {{ tab }}
                    </button>
                </div>

                <div class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 shadow-wpx-card-dark mt-3 border p-5">
                    <template v-if="activeTab === 'Vue d’ensemble'">
                        <h4 class="text-base font-extrabold">Protection du compte</h4>
                        <p class="text-wpx-muted-dark mt-1 max-w-2xl text-sm">
                            La restriction globale est active aujourd’hui. Les restrictions ciblées seront ajoutées plus
                            tard pour éviter de bloquer tout le compte lorsqu’un seul usage pose problème.
                        </p>
                        <div class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 mt-4 border p-4">
                            <p class="text-sm font-bold">Restrictions ciblées prévues</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Connexion · retraits · publication de publicités · réception de récompenses.
                            </p>
                            <span
                                class="bg-wpx-warning/15 text-wpx-gold rounded-wpx-full mt-3 inline-flex px-2.5 py-1 text-[10px] font-extrabold"
                            >
                                En préparation
                            </span>
                        </div>

                        <details class="border-wpx-border-dark rounded-wpx-lg mt-4 border p-4">
                            <summary class="text-wpx-muted-dark cursor-pointer text-xs font-extrabold">
                                Détails techniques
                            </summary>
                            <p class="text-wpx-muted-dark mt-3 font-mono text-[11px] break-all">
                                ID : {{ selectedDetail.account.id }}
                            </p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Identifiants : {{ selectedDetail.account.identifiers.join(' · ') }}
                            </p>
                        </details>
                    </template>

                    <template v-else-if="activeTab === 'Wallet'">
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            Solde actuel
                        </p>
                        <p class="mt-1 text-3xl font-extrabold">
                            {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} WP
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            ≈ {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} FCFA
                        </p>
                        <p class="text-wpx-muted-dark mt-4 text-xs">
                            L’historique financier détaillé sera accessible depuis Finance.
                        </p>
                    </template>

                    <template v-else-if="activeTab === 'Abonnement'">
                        <p class="text-wpx-muted-dark text-[10px] font-extrabold tracking-wide uppercase">
                            Offre actuelle
                        </p>
                        <p class="mt-1 text-2xl font-extrabold">
                            {{
                                selectedDetail.economic_class_code
                                    ? economicClassLabel(selectedDetail.economic_class_code)
                                    : 'Aucun abonnement actif'
                            }}
                        </p>
                        <p class="text-wpx-muted-dark mt-3 text-xs">
                            Les offres publiées se gèrent depuis le module Offres.
                        </p>
                    </template>

                    <template v-else-if="activeTab === 'Organisations'">
                        <div class="mb-4">
                            <h4 class="text-base font-extrabold">Activités & organisations</h4>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Les espaces auxquels cette personne appartient sont regroupés ici ; aucun écran séparé
                                n’est nécessaire.
                            </p>
                        </div>
                        <div v-if="selectedDetail.organizations.length" class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div
                                v-for="org in selectedDetail.organizations"
                                :key="org.id"
                                class="border-wpx-border-dark rounded-wpx-lg bg-wpx-navy-950 border p-4"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-extrabold">{{ org.name }}</p>
                                        <p class="text-wpx-muted-dark mt-1 text-xs">
                                            {{ organizationTypeLabel(org.type) }}
                                        </p>
                                    </div>
                                    <span
                                        class="bg-wpx-success/15 text-wpx-success rounded-wpx-full px-2.5 py-1 text-[10px] font-extrabold"
                                    >
                                        {{ statusLabel(org.status) }}
                                    </span>
                                </div>
                                <details class="mt-3">
                                    <summary class="text-wpx-muted-dark cursor-pointer text-[10px] font-bold">
                                        ID technique
                                    </summary>
                                    <p class="text-wpx-muted-dark mt-1 font-mono text-[10px] break-all">{{ org.id }}</p>
                                </details>
                            </div>
                        </div>
                        <p v-else class="text-wpx-muted-dark text-sm">Aucune organisation liée à ce compte.</p>
                    </template>

                    <template v-else>
                        <p class="text-wpx-muted-dark text-sm">Historique détaillé bientôt disponible.</p>
                    </template>
                </div>
            </section>

            <div
                v-else
                class="border-wpx-border-dark rounded-wpx-xl bg-wpx-navy-850 text-wpx-muted-dark shadow-wpx-card-dark flex min-h-72 items-center justify-center border p-8 text-center text-sm"
            >
                Aucun compte à afficher.
            </div>
        </div>
    </div>
</template>
