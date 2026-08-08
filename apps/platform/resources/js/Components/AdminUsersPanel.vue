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

const TABS = ['Compte', 'Wallet', 'Abonnement', 'Organisations', 'Historique'] as const;

const accounts = ref<AccountRow[]>([]);
const searchQuery = ref('');
const selectedDetail = ref<AccountDetail | null>(null);
const activeTab = ref<(typeof TABS)[number]>('Compte');
const loading = ref(true);
const busy = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');

function initials(identifier: string): string {
    const alnum = identifier.replace(/[^a-zA-Z0-9]/g, '');
    return alnum.slice(-2).toUpperCase();
}

async function search(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/accounts', { params: { q: searchQuery.value || undefined } });
        accounts.value = data.accounts;
    } finally {
        loading.value = false;
    }
}

async function selectAccount(accountId: string): Promise<void> {
    activeTab.value = 'Compte';
    const { data } = await http.get(`/admin/accounts/${accountId}`);
    selectedDetail.value = data;
}

async function toggleRestriction(): Promise<void> {
    if (!selectedDetail.value) return;
    busy.value = true;
    try {
        const action = selectedDetail.value.account.is_restricted ? 'unrestrict' : 'restrict';
        const { data } = await http.post(`/admin/accounts/${selectedDetail.value.account.id}/${action}`);
        selectedDetail.value.account.is_restricted = data.account.is_restricted;
        await search();
    } finally {
        busy.value = false;
    }
}

const restrictionToggles = [
    { key: 'login', label: 'Connexion' },
    { key: 'withdrawals', label: 'Retraits' },
    { key: 'ads', label: 'Publier des publicités' },
    { key: 'earn', label: 'Gagner des WP (publicités, parrainage)' },
];
const toggleNotice = ref<string | null>(null);
let toggleNoticeTimer: ReturnType<typeof setTimeout> | null = null;
function announceComingSoon(): void {
    toggleNotice.value = 'Bientôt disponible';
    if (toggleNoticeTimer) clearTimeout(toggleNoticeTimer);
    toggleNoticeTimer = setTimeout(() => (toggleNotice.value = null), 1800);
}

const memberSince = computed(() => {
    if (!selectedDetail.value) return '';
    return new Date(selectedDetail.value.account.created_at).toLocaleDateString('fr-FR', {
        month: 'long',
        year: 'numeric',
    });
});

onMounted(search);
</script>

<template>
    <div class="flex gap-5">
        <div class="flex w-80 shrink-0 flex-col gap-3.5">
            <div class="rounded-wpx-md border-wpx-border flex items-center gap-2.5 border bg-white px-3.5 py-2.5">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="11" cy="11" r="7" stroke="#8B99AC" stroke-width="1.8" />
                    <path d="M21 21l-4.3-4.3" stroke="#8B99AC" stroke-width="1.8" stroke-linecap="round" />
                </svg>
                <input
                    v-model="searchQuery"
                    placeholder="Téléphone, email ou identifiant…"
                    class="text-wpx-text placeholder:text-wpx-muted-dark flex-1 text-[13px] outline-none"
                    @keyup.enter="search"
                />
            </div>
            <div class="rounded-wpx-md shadow-wpx-card border-wpx-border overflow-hidden border bg-white p-1.5">
                <button
                    v-for="account in accounts"
                    :key="account.id"
                    type="button"
                    class="rounded-wpx-sm flex w-full items-center gap-3 p-3 text-left"
                    :class="
                        selectedDetail?.account.id === account.id
                            ? 'bg-wpx-blue-light/5 border-wpx-blue-light/25 border'
                            : ''
                    "
                    @click="selectAccount(account.id)"
                >
                    <span
                        class="from-wpx-blue to-wpx-cyan flex h-9.5 w-9.5 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-[13px] font-extrabold text-white"
                    >
                        {{ initials(account.primary_identifier) }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="text-wpx-text block truncate text-[13px] font-bold">{{
                            account.primary_identifier
                        }}</span>
                        <span v-if="account.is_restricted" class="text-wpx-danger-light block text-[11px] font-bold"
                            >Compte restreint</span
                        >
                        <span v-else class="text-wpx-text-muted block text-[11px]">{{ account.status }}</span>
                    </span>
                </button>
                <p v-if="!loading && accounts.length === 0" class="text-wpx-text-muted p-3 text-sm">
                    Aucun compte trouvé.
                </p>
            </div>
        </div>

        <div class="min-w-0 flex-1">
            <template v-if="selectedDetail">
                <div class="rounded-wpx-lg shadow-wpx-card border-wpx-border border bg-white p-5.5">
                    <div class="flex items-center gap-4">
                        <span
                            class="from-wpx-blue to-wpx-cyan flex h-15 w-15 shrink-0 items-center justify-center rounded-full bg-gradient-to-br text-xl font-extrabold text-white"
                        >
                            {{ initials(selectedDetail.account.identifiers[0] ?? selectedDetail.account.id) }}
                        </span>
                        <div class="flex-1">
                            <div class="flex items-center gap-2.5">
                                <span class="text-wpx-text text-[19px] font-bold">{{
                                    selectedDetail.account.identifiers[0]
                                }}</span>
                                <span
                                    class="rounded-wpx-full px-2.5 py-0.5 text-[11px] font-bold"
                                    :class="
                                        selectedDetail.account.is_restricted
                                            ? 'bg-wpx-danger/10 text-wpx-danger-light'
                                            : 'bg-wpx-success/10 text-wpx-success-light'
                                    "
                                >
                                    {{ selectedDetail.account.is_restricted ? 'Restreint' : 'Actif' }}
                                </span>
                                <span
                                    v-if="selectedDetail.is_online"
                                    class="bg-wpx-success h-2 w-2 rounded-full"
                                    title="En ligne"
                                />
                            </div>
                            <p class="text-wpx-text-muted mt-1 text-[13px]">
                                {{ selectedDetail.account.country_code ?? '—' }} · Membre depuis {{ memberSince }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-wpx-md border-[1.5px] px-4.5 py-2.5 text-xs font-bold whitespace-nowrap disabled:opacity-50"
                            :class="
                                selectedDetail.account.is_restricted
                                    ? 'border-wpx-success text-wpx-success-light'
                                    : 'border-wpx-danger-light text-wpx-danger-light'
                            "
                            :disabled="busy"
                            @click="toggleRestriction"
                        >
                            {{ selectedDetail.account.is_restricted ? 'Restaurer le compte' : 'Restreindre le compte' }}
                        </button>
                    </div>

                    <div class="border-wpx-border mt-5 grid grid-cols-4 gap-3 border-t pt-4.5">
                        <div>
                            <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                Solde wallet
                            </p>
                            <p class="text-wpx-text mt-1 text-[17px] font-bold">
                                {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} WP
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Abonnement</p>
                            <p class="text-wpx-text mt-1 text-[17px] font-bold">
                                {{
                                    selectedDetail.economic_class_code
                                        ? economicClassLabel(selectedDetail.economic_class_code)
                                        : '—'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                Espace annonceur
                            </p>
                            <p class="text-wpx-text mt-1 text-[17px] font-bold">
                                {{ selectedDetail.advertiser_organization_name ?? 'Aucun' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">
                                Vérification identité
                            </p>
                            <p class="text-wpx-warning-light mt-1 text-[17px] font-bold">Bientôt disponible</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2.5">
                    <button
                        v-for="tab in TABS"
                        :key="tab"
                        type="button"
                        class="rounded-wpx-full px-4 py-2 text-xs font-bold"
                        :class="
                            activeTab === tab
                                ? 'bg-wpx-navy-950 text-white'
                                : 'border-wpx-border text-wpx-text-muted border bg-white'
                        "
                        @click="activeTab = tab"
                    >
                        {{ tab }}
                    </button>
                </div>

                <div
                    v-if="activeTab === 'Compte'"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border mt-4 border bg-white p-5.5"
                >
                    <p class="text-wpx-text mb-4 text-sm font-bold">Que peux-tu limiter sur ce compte ?</p>
                    <p class="text-wpx-text-muted mb-4 text-xs leading-relaxed">
                        Limite seulement ce qui pose problème — évite de bloquer tout le compte si une seule chose doit
                        être restreinte.
                    </p>
                    <div class="flex flex-col">
                        <div
                            v-for="toggle in restrictionToggles"
                            :key="toggle.key"
                            class="border-wpx-border/60 flex items-center gap-3 border-b py-3 last:border-0"
                        >
                            <span class="text-wpx-text flex-1 text-[13px] font-bold">{{ toggle.label }}</span>
                            <button
                                type="button"
                                class="bg-wpx-border relative h-5.5 w-9.5 shrink-0 rounded-full"
                                @click="announceComingSoon"
                            >
                                <span class="absolute top-0.5 left-0.5 h-4.5 w-4.5 rounded-full bg-white shadow" />
                            </button>
                        </div>
                    </div>
                    <p v-if="toggleNotice" class="text-wpx-text-muted mt-3 text-xs italic">{{ toggleNotice }}</p>
                </div>

                <div
                    v-else-if="activeTab === 'Wallet'"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border mt-4 border bg-white p-5.5"
                >
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Solde actuel</p>
                    <p class="text-wpx-text mt-1 text-2xl font-extrabold">
                        {{ numberFormatter.format(selectedDetail.wallet_balance_minor) }} WP
                    </p>
                    <p class="text-wpx-text-muted mt-3 text-xs italic">Historique détaillé bientôt disponible.</p>
                </div>

                <div
                    v-else-if="activeTab === 'Abonnement'"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border mt-4 border bg-white p-5.5"
                >
                    <p class="text-wpx-text-muted text-[11px] font-bold tracking-wide uppercase">Classe actuelle</p>
                    <p class="text-wpx-text mt-1 text-2xl font-extrabold">
                        {{
                            selectedDetail.economic_class_code
                                ? economicClassLabel(selectedDetail.economic_class_code)
                                : 'Aucun abonnement actif'
                        }}
                    </p>
                </div>

                <div
                    v-else-if="activeTab === 'Organisations'"
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border mt-4 overflow-hidden border bg-white"
                >
                    <div
                        v-for="org in selectedDetail.organizations"
                        :key="org.id"
                        class="border-wpx-border/60 flex items-center gap-3 border-b p-4 last:border-0"
                    >
                        <span class="text-wpx-text flex-1 text-[13px] font-bold">{{ org.name }}</span>
                        <span class="text-wpx-text-muted text-xs">{{ org.type }}</span>
                        <span
                            class="rounded-wpx-full bg-wpx-canvas text-wpx-text-muted px-2.5 py-0.5 text-[11px] font-bold"
                        >
                            {{ org.status }}
                        </span>
                    </div>
                    <p v-if="selectedDetail.organizations.length === 0" class="text-wpx-text-muted p-4 text-sm">
                        Aucune organisation.
                    </p>
                </div>

                <div
                    v-else
                    class="rounded-wpx-lg shadow-wpx-card border-wpx-border mt-4 border bg-white p-5.5 text-center"
                >
                    <p class="text-wpx-text-muted text-sm italic">Bientôt disponible</p>
                </div>
            </template>
            <div
                v-else
                class="rounded-wpx-lg shadow-wpx-card border-wpx-border text-wpx-text-muted flex h-40 items-center justify-center border bg-white text-sm"
            >
                Choisis un compte à gauche pour voir sa fiche.
            </div>
        </div>
    </div>
</template>
