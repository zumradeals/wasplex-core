<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Environment = {
    environment: 'sandbox' | 'production';
    label: string;
    configured: boolean;
    apiKeyConfigured: boolean;
    apiSecretConfigured: boolean;
    webhookSecretConfigured: boolean;
    apiKeyMask: string | null;
    apiSecretMask: string | null;
    webhookSecretMask: string | null;
    baseUrl: string;
    isActive: boolean;
    storedInDatabase: boolean;
    lastTestStatus: 'success' | 'failed' | null;
    lastTestMessage: string | null;
    lastTestedAt: string | null;
    activatedAt: string | null;
};

type ProviderEvent = {
    name: string;
    status: string;
    attempts: number;
    occurredAt: string | null;
    processedAt: string | null;
    lastError: string | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    environments: Environment[];
    activeEnvironment: 'sandbox' | 'production';
    productionActivationAllowed: boolean;
    webhookUrl: string;
    webhookHealth: {
        lastAcceptedEvent: ProviderEvent | null;
        paymentsQueueSize: number;
    };
    pendingDeposits: number;
    flash: { success: string | null };
    errors: Record<string, string[]>;
}>();

const sandboxForm = useForm({
    api_key: '',
    api_secret: '',
    webhook_secret: '',
});

const productionForm = useForm({
    api_key: '',
    api_secret: '',
    webhook_secret: '',
});

const copied = ref(false);

const formFor = (environment: Environment['environment']) =>
    environment === 'sandbox' ? sandboxForm : productionForm;

const save = (environment: Environment['environment']) => {
    const form = formFor(environment);
    form.patch(`/administration/paiements/geniuspay/${environment}`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const testConnection = (environment: Environment['environment']) => {
    router.post(
        `/administration/paiements/geniuspay/${environment}/tester`,
        {},
        { preserveScroll: true },
    );
};

const activate = (environment: Environment['environment']) => {
    if (!window.confirm(`Activer GeniusPay ${environment} pour les nouveaux paiements ?`)) return;

    router.post(
        `/administration/paiements/geniuspay/${environment}/activer`,
        {},
        { preserveScroll: true },
    );
};

const reconcile = () => {
    router.post(
        '/administration/paiements/geniuspay/reverifier-les-depots',
        {},
        { preserveScroll: true },
    );
};

const copyWebhook = async () => {
    await navigator.clipboard.writeText(props.webhookUrl);
    copied.value = true;
    window.setTimeout(() => (copied.value = false), 1800);
};

const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value))
        : 'Jamais';
</script>

<template>
    <Head title="Paiements et GeniusPay" />
    <AdminLayout :account="account" :active-space="activeSpace" :spaces="spaces" active="payments">
        <header class="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.22em] text-cyan-300 uppercase">
                    Paiements sécurisés
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Configuration GeniusPay
                </h1>
                <p class="mt-3 max-w-3xl text-sm leading-6 text-white/42">
                    Enregistre, teste et active GeniusPay sans accéder au serveur. Les secrets sont
                    chiffrés et ne sont jamais réaffichés en clair.
                </p>
            </div>
            <div class="rounded-2xl border border-emerald-300/15 bg-emerald-300/[0.055] px-5 py-4">
                <p class="text-xs font-bold text-white/35">Environnement actif</p>
                <p class="mt-1 text-xl font-black text-emerald-200 capitalize">
                    {{ activeEnvironment }}
                </p>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <div
            v-if="
                errors.connection?.length ||
                errors.credentials?.length ||
                errors.environment?.length
            "
            class="mt-6 rounded-2xl border border-rose-400/20 bg-rose-400/8 px-5 py-4 text-sm font-bold text-rose-200"
        >
            {{ errors.connection?.[0] || errors.credentials?.[0] || errors.environment?.[0] }}
        </div>

        <section class="mt-7 grid gap-5 lg:grid-cols-3">
            <article class="rounded-[1.75rem] border border-white/8 bg-white/[0.035] p-5">
                <p class="text-xs font-black tracking-[0.16em] text-cyan-200 uppercase">
                    Adresse du webhook
                </p>
                <p class="mt-4 text-sm leading-6 break-all text-white/60">{{ webhookUrl }}</p>
                <button
                    class="mt-4 rounded-xl border border-cyan-300/20 px-4 py-2 text-xs font-black text-cyan-200"
                    @click="copyWebhook"
                >
                    {{ copied ? 'Adresse copiée' : 'Copier l’adresse' }}
                </button>
            </article>

            <article class="rounded-[1.75rem] border border-white/8 bg-white/[0.035] p-5">
                <p class="text-xs font-black tracking-[0.16em] text-cyan-200 uppercase">
                    Dernier webhook accepté
                </p>
                <template v-if="webhookHealth.lastAcceptedEvent">
                    <p class="mt-4 font-black">{{ webhookHealth.lastAcceptedEvent.name }}</p>
                    <p class="mt-2 text-sm text-white/42">
                        {{ webhookHealth.lastAcceptedEvent.status }} ·
                        {{ date(webhookHealth.lastAcceptedEvent.occurredAt) }}
                    </p>
                    <p
                        v-if="webhookHealth.lastAcceptedEvent.lastError"
                        class="mt-2 text-xs text-rose-300"
                    >
                        {{ webhookHealth.lastAcceptedEvent.lastError }}
                    </p>
                </template>
                <p v-else class="mt-4 text-sm leading-6 text-white/38">
                    Aucun webhook GeniusPay n’a encore été accepté.
                </p>
                <p class="mt-4 text-xs text-white/25">
                    File paiements : {{ webhookHealth.paymentsQueueSize }} élément(s)
                </p>
            </article>

            <article class="rounded-[1.75rem] border border-amber-300/15 bg-amber-300/[0.045] p-5">
                <p class="text-xs font-black tracking-[0.16em] text-amber-200 uppercase">
                    Dépôts à revérifier
                </p>
                <p class="mt-3 text-4xl font-black">{{ pendingDeposits }}</p>
                <p class="mt-2 text-sm leading-6 text-white/42">
                    Wasplex interrogera GeniusPay puis créditera uniquement les paiements réellement
                    confirmés.
                </p>
                <button
                    class="mt-4 w-full rounded-xl bg-amber-200 px-4 py-3 text-xs font-black text-[#151006] disabled:opacity-40"
                    :disabled="pendingDeposits === 0"
                    @click="reconcile"
                >
                    Revérifier maintenant
                </button>
            </article>
        </section>

        <section class="mt-7 grid gap-6 xl:grid-cols-2">
            <article
                v-for="environment in environments"
                :key="environment.environment"
                class="rounded-[2rem] border bg-white/[0.035] p-5 sm:p-7"
                :class="
                    environment.isActive
                        ? 'border-emerald-300/25'
                        : environment.environment === 'production'
                          ? 'border-violet-300/15'
                          : 'border-white/8'
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p
                            class="text-xs font-black tracking-[0.18em] uppercase"
                            :class="
                                environment.environment === 'sandbox'
                                    ? 'text-cyan-200'
                                    : 'text-violet-200'
                            "
                        >
                            {{ environment.label }}
                        </p>
                        <h2 class="mt-2 text-2xl font-black">
                            {{
                                environment.environment === 'sandbox'
                                    ? 'Tests et recettes'
                                    : 'Paiements réels'
                            }}
                        </h2>
                    </div>
                    <span
                        class="rounded-full px-3 py-1.5 text-[10px] font-black uppercase"
                        :class="
                            environment.isActive
                                ? 'bg-emerald-300/12 text-emerald-200'
                                : environment.configured
                                  ? 'bg-cyan-300/10 text-cyan-200'
                                  : 'bg-white/6 text-white/35'
                        "
                    >
                        {{
                            environment.isActive
                                ? 'Actif'
                                : environment.configured
                                  ? 'Configuré'
                                  : 'À configurer'
                        }}
                    </span>
                </div>

                <div
                    v-if="environment.environment === 'production' && !productionActivationAllowed"
                    class="mt-5 rounded-2xl border border-violet-300/15 bg-violet-300/[0.055] px-4 py-3 text-sm leading-6 text-violet-100/75"
                >
                    Tu peux enregistrer et tester les clés Production dès maintenant. Leur
                    activation reste verrouillée jusqu’à l’autorisation officielle de mise en
                    production.
                </div>

                <div class="mt-6 space-y-4">
                    <label class="block">
                        <span class="text-xs font-black text-white/55">Clé API</span>
                        <input
                            v-model="formFor(environment.environment).api_key"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="environment.apiKeyMask || 'Coller la clé API'"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-black text-white/55">Secret API</span>
                        <input
                            v-model="formFor(environment.environment).api_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="environment.apiSecretMask || 'Coller le secret API'"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>

                    <label class="block">
                        <span class="text-xs font-black text-white/55">Secret du webhook</span>
                        <input
                            v-model="formFor(environment.environment).webhook_secret"
                            type="password"
                            autocomplete="new-password"
                            :placeholder="
                                environment.webhookSecretMask || 'Coller le secret webhook'
                            "
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        />
                    </label>
                </div>

                <p class="mt-4 text-xs leading-5 text-white/28">
                    Laisse un champ vide pour conserver la valeur actuelle. Les valeurs enregistrées
                    ne seront jamais visibles en clair après sauvegarde.
                </p>

                <div class="mt-5 rounded-2xl bg-black/15 p-4 text-xs">
                    <div class="flex justify-between gap-4">
                        <span class="text-white/30">Dernier test</span>
                        <strong
                            :class="
                                environment.lastTestStatus === 'success'
                                    ? 'text-emerald-200'
                                    : environment.lastTestStatus === 'failed'
                                      ? 'text-rose-200'
                                      : 'text-white/45'
                            "
                        >
                            {{ environment.lastTestStatus || 'Jamais testé' }}
                        </strong>
                    </div>
                    <p v-if="environment.lastTestMessage" class="mt-2 leading-5 text-white/42">
                        {{ environment.lastTestMessage }}
                    </p>
                    <p class="mt-2 text-white/24">{{ date(environment.lastTestedAt) }}</p>
                </div>

                <div class="mt-5 grid gap-2 sm:grid-cols-3">
                    <button
                        class="rounded-xl bg-white px-4 py-3 text-xs font-black text-[#04111e] disabled:opacity-40"
                        :disabled="formFor(environment.environment).processing"
                        @click="save(environment.environment)"
                    >
                        Enregistrer
                    </button>
                    <button
                        class="rounded-xl border border-cyan-300/20 bg-cyan-300/8 px-4 py-3 text-xs font-black text-cyan-200 disabled:opacity-40"
                        :disabled="
                            !environment.apiKeyConfigured || !environment.apiSecretConfigured
                        "
                        @click="testConnection(environment.environment)"
                    >
                        Tester
                    </button>
                    <button
                        class="rounded-xl border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-xs font-black text-emerald-200 disabled:cursor-not-allowed disabled:opacity-35"
                        :disabled="
                            environment.isActive ||
                            environment.lastTestStatus !== 'success' ||
                            (environment.environment === 'production' &&
                                !productionActivationAllowed)
                        "
                        @click="activate(environment.environment)"
                    >
                        {{ environment.isActive ? 'Déjà actif' : 'Activer' }}
                    </button>
                </div>
            </article>
        </section>
    </AdminLayout>
</template>
