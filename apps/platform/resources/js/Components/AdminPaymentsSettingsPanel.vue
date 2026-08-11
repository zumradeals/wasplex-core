<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import http from '@/lib/http';

interface GeniusPayStatus {
    configured: boolean;
    api_key_hint: string | null;
    updated_at: string | null;
    webhook_url: string;
}

const status = ref<GeniusPayStatus | null>(null);
const form = reactive({ api_key: '', api_secret: '', webhook_secret: '' });
const loading = ref(true);
const saving = ref(false);
const testing = ref(false);
const message = ref<string | null>(null);
const copied = ref(false);

async function load(): Promise<void> {
    loading.value = true;
    try {
        const { data } = await http.get('/admin/advertiser-wallet/geniuspay');
        status.value = data.geniuspay;
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<void> {
    message.value = null;
    saving.value = true;
    try {
        const { data } = await http.put('/admin/advertiser-wallet/geniuspay', form);
        status.value = data.geniuspay;
        form.api_key = '';
        form.api_secret = '';
        form.webhook_secret = '';
        message.value = 'Configuration GeniusPay enregistrée et chiffrée.';
    } catch (e) {
        message.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'La configuration n’a pas pu être enregistrée.';
    } finally {
        saving.value = false;
    }
}

async function testConnection(): Promise<void> {
    message.value = null;
    testing.value = true;
    try {
        const { data } = await http.post('/admin/advertiser-wallet/geniuspay/test');
        message.value = data.message;
    } catch (e) {
        message.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            'Le test de connexion a échoué.';
    } finally {
        testing.value = false;
    }
}

async function copyWebhook(): Promise<void> {
    if (!status.value?.webhook_url) return;
    await navigator.clipboard.writeText(status.value.webhook_url);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 1600);
}

onMounted(load);
</script>

<template>
    <div class="mx-auto flex max-w-4xl flex-col gap-5 text-wpx-white-soft">
        <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-wpx-cyan text-[11px] font-extrabold uppercase tracking-[0.16em]">Paiements & intégrations</p>
                    <h2 class="mt-1 text-xl font-extrabold">Connexion GeniusPay</h2>
                    <p class="text-wpx-muted-dark mt-2 max-w-2xl text-sm">
                        Cette zone configure la recharge du wallet annonceur. Les clés restent chiffrées et ne sont jamais
                        réaffichées en clair.
                    </p>
                </div>
                <span
                    class="rounded-wpx-full px-3 py-1 text-xs font-extrabold"
                    :class="status?.configured ? 'bg-wpx-success/15 text-wpx-success' : 'bg-wpx-warning/15 text-wpx-gold'"
                >
                    {{ status?.configured ? 'Configuré' : 'À configurer' }}
                </span>
            </div>
            <div class="bg-wpx-warning/10 text-wpx-gold mt-4 rounded-wpx-md p-3 text-xs">
                Mode actuel : <strong>Sandbox / test</strong>. Aucune transaction réelle ne doit être considérée comme encaissée
                tant que l’intégration production n’est pas activée.
            </div>
        </section>

        <p v-if="loading" class="text-wpx-muted-dark text-sm">Chargement…</p>

        <template v-else>
            <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
                <h3 class="text-base font-extrabold">Clés de connexion</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    {{ status?.api_key_hint ? `Clé actuellement enregistrée : ${status.api_key_hint}` : 'Aucune clé enregistrée.' }}
                </p>

                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="save">
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Clé publique sandbox</span>
                        <input
                            v-model="form.api_key"
                            type="password"
                            autocomplete="off"
                            placeholder="pk_sandbox_…"
                            class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm text-wpx-white-soft"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs">
                        <span class="text-wpx-muted-dark font-bold">Clé secrète sandbox</span>
                        <input
                            v-model="form.api_secret"
                            type="password"
                            autocomplete="off"
                            placeholder="sk_sandbox_…"
                            class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm text-wpx-white-soft"
                        />
                    </label>
                    <label class="flex flex-col gap-1.5 text-xs md:col-span-2">
                        <span class="text-wpx-muted-dark font-bold">Secret du webhook</span>
                        <input
                            v-model="form.webhook_secret"
                            type="password"
                            autocomplete="off"
                            placeholder="whsec_…"
                            class="border-wpx-border-dark rounded-wpx-md border bg-wpx-navy-950 px-3 py-2.5 text-sm text-wpx-white-soft"
                        />
                    </label>
                    <div class="flex flex-wrap gap-2 md:col-span-2">
                        <button
                            type="submit"
                            class="from-wpx-blue to-wpx-cyan rounded-wpx-md bg-gradient-to-br px-4 py-2.5 text-xs font-extrabold text-wpx-navy-950 disabled:opacity-50"
                            :disabled="saving"
                        >
                            {{ saving ? 'Enregistrement…' : 'Enregistrer les clés' }}
                        </button>
                        <button
                            type="button"
                            class="border-wpx-border-dark text-wpx-cyan rounded-wpx-md border px-4 py-2.5 text-xs font-extrabold disabled:opacity-50"
                            :disabled="testing"
                            @click="testConnection"
                        >
                            {{ testing ? 'Test…' : 'Tester la connexion' }}
                        </button>
                    </div>
                </form>

                <p v-if="message" class="border-wpx-border-dark text-wpx-muted-dark mt-4 rounded-wpx-md border bg-wpx-navy-950 p-3 text-xs">
                    {{ message }}
                </p>
            </section>

            <section class="border-wpx-border-dark rounded-wpx-xl border bg-wpx-navy-850 p-5 shadow-wpx-card-dark">
                <h3 class="text-base font-extrabold">Webhook GeniusPay</h3>
                <p class="text-wpx-muted-dark mt-1 text-xs">Cette adresse doit être déclarée dans GeniusPay pour confirmer les paiements.</p>
                <div class="border-wpx-border-dark mt-4 flex flex-col gap-3 rounded-wpx-md border bg-wpx-navy-950 p-4 sm:flex-row sm:items-center">
                    <code class="text-wpx-muted-dark min-w-0 flex-1 break-all text-xs">{{ status?.webhook_url ?? '—' }}</code>
                    <button type="button" class="text-wpx-cyan shrink-0 text-xs font-extrabold" @click="copyWebhook">
                        {{ copied ? 'Copié !' : 'Copier l’adresse' }}
                    </button>
                </div>
            </section>
        </template>
    </div>
</template>
