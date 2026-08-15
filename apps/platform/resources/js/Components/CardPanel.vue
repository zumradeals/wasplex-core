<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import CardPaymentPanel from '@/Components/CardPaymentPanel.vue';
import http from '@/lib/http';
import { makeQrMatrix } from '@/lib/qrCode';

interface CardData {
    id: string;
    public_identifier: string;
    status: string;
    display_name: string;
    offer: { code: string; name: string };
    issued_at: string | null;
    expires_at: string | null;
    supports_virtual: boolean;
    supports_physical: boolean;
}

interface OfferData {
    code: string;
    name: string;
    description: string | null;
    price_minor: number;
    currency: string;
    supports_virtual: boolean;
    supports_physical: boolean;
}

interface QrData {
    token_id: string;
    purpose: string;
    payload: string;
    expires_at: string;
}

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();

const loading = ref(false);
const busy = ref(false);
const error = ref<string | null>(null);
const card = ref<CardData | null>(null);
const offer = ref<OfferData | null>(null);
const qr = ref<QrData | null>(null);
const paymentsOpen = ref(false);

const statusLabel = computed(() => {
    if (!card.value) return '';
    if (card.value.status === 'active') return 'Active';
    if (card.value.status === 'suspended') return 'Suspendue';
    return card.value.status;
});

const qrMatrix = computed(() => (qr.value ? makeQrMatrix(qr.value.payload) : []));
const qrPath = computed(() => {
    const commands: string[] = [];
    qrMatrix.value.forEach((row, y) =>
        row.forEach((cell, x) => {
            if (cell) commands.push(`M${x + 4} ${y + 4}h1v1h-1z`);
        }),
    );
    return commands.join('');
});
const qrSize = computed(() => (qrMatrix.value.length || 41) + 8);

function messageFrom(errorValue: unknown): string {
    const response = (errorValue as { response?: { data?: { message?: string } } })?.response;
    return response?.data?.message ?? 'Une erreur est survenue. Réessayez.';
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await http.get('/cards');
        card.value = data.card;
        offer.value = data.offer;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        loading.value = false;
    }
}

async function issue(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/cards');
        card.value = data.card;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function generateQr(): Promise<void> {
    if (!card.value) return;
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/cards/${card.value.id}/qr`);
        qr.value = data.qr;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function suspend(): Promise<void> {
    if (!card.value || !window.confirm('Suspendre immédiatement cette Carte Wasplex ? Les QR actifs seront révoqués.'))
        return;
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(`/cards/${card.value.id}/suspend`);
        card.value = data.card;
        qr.value = null;
        paymentsOpen.value = false;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) void load();
        else {
            qr.value = null;
            paymentsOpen.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex justify-center bg-black/70" @click.self="emit('close')">
            <div class="bg-wpx-navy-950 min-h-full w-full max-w-md overflow-y-auto px-4 py-5">
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-gold text-xs font-bold tracking-[0.18em] uppercase">Carte Wasplex</p>
                        <h2 class="text-wpx-white-soft mt-1 text-xl font-extrabold">Ma Carte</h2>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Identité minimale, QR sécurisé et accès aux services.
                        </p>
                    </div>
                    <button
                        type="button"
                        aria-label="Fermer"
                        class="bg-wpx-navy-750 text-wpx-white-soft flex h-10 w-10 items-center justify-center rounded-full text-xl"
                        @click="emit('close')"
                    >
                        ×
                    </button>
                </div>

                <p v-if="error" role="alert" class="bg-wpx-danger/10 text-wpx-danger mb-4 rounded-xl px-3 py-2 text-xs">
                    {{ error }}
                </p>
                <div v-if="loading" class="text-wpx-muted-dark py-16 text-center text-sm">
                    Chargement de votre Carte…
                </div>

                <template v-else-if="card">
                    <section
                        class="from-wpx-navy-700 via-wpx-navy-850 to-wpx-navy-950 border-wpx-gold/25 relative overflow-hidden rounded-[26px] border bg-gradient-to-br p-5 shadow-2xl"
                    >
                        <div class="bg-wpx-orange/15 absolute -top-12 -right-12 h-36 w-36 rounded-full blur-2xl"></div>
                        <div class="relative flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <img
                                    src="/brand/wasplex-logo-transparent.png"
                                    alt="Wasplex"
                                    class="h-8 w-8 object-contain"
                                />
                                <div>
                                    <p class="text-wpx-white-soft text-sm font-extrabold">WASPLEX</p>
                                    <p class="text-wpx-muted-dark text-[9px] tracking-[0.18em] uppercase">
                                        Carte de services
                                    </p>
                                </div>
                            </div>
                            <span
                                class="rounded-full px-2.5 py-1 text-[10px] font-bold"
                                :class="
                                    card.status === 'active'
                                        ? 'bg-wpx-success/15 text-wpx-success-light'
                                        : 'bg-wpx-gold/15 text-wpx-gold'
                                "
                                >{{ statusLabel }}</span
                            >
                        </div>

                        <div class="relative mt-10">
                            <p class="text-wpx-muted-dark text-[9px] tracking-[0.16em] uppercase">Titulaire</p>
                            <p class="text-wpx-white-soft mt-1 truncate text-lg font-bold">{{ card.display_name }}</p>
                            <p class="text-wpx-gold mt-4 font-mono text-sm tracking-[0.08em] break-all">
                                {{ card.public_identifier }}
                            </p>
                        </div>

                        <div class="relative mt-7 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-wpx-muted-dark text-[9px] uppercase">Offre</p>
                                <p class="text-wpx-white-soft mt-0.5 text-xs font-semibold">{{ card.offer.name }}</p>
                            </div>
                            <span class="text-wpx-muted-dark text-[10px]">Virtuelle</span>
                        </div>
                    </section>

                    <section class="mt-4 grid grid-cols-2 gap-2.5">
                        <button
                            type="button"
                            :disabled="busy || card.status !== 'active'"
                            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-xl bg-gradient-to-r px-3 py-3 text-sm font-extrabold disabled:opacity-40"
                            @click="paymentsOpen = true"
                        >
                            Recevoir / Payer
                        </button>
                        <button
                            type="button"
                            :disabled="busy || card.status !== 'active'"
                            class="border-wpx-gold/25 bg-wpx-navy-850 text-wpx-gold rounded-xl border px-3 py-3 text-sm font-bold disabled:opacity-40"
                            @click="generateQr"
                        >
                            QR identité
                        </button>
                        <button
                            type="button"
                            class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft col-span-2 rounded-xl border px-3 py-3 text-sm font-bold"
                            @click="emit('close')"
                        >
                            Fermer
                        </button>
                    </section>

                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-2xl border p-4">
                        <p class="text-wpx-white-soft text-sm font-bold">Protection de vos données</p>
                        <p class="text-wpx-muted-dark mt-1.5 text-xs leading-relaxed">
                            Votre QR ne révèle ni téléphone, ni e-mail, ni solde Wallet, ni KYC, ni données Santé ou
                            Fonds. Le QR d’identité expire après 2 minutes. Un QR de réception ne déclenche aucun débit
                            au simple scan.
                        </p>
                    </section>

                    <button
                        v-if="card.status === 'active'"
                        type="button"
                        :disabled="busy"
                        class="text-wpx-danger mt-4 w-full py-2 text-xs font-bold disabled:opacity-40"
                        @click="suspend"
                    >
                        Suspendre ma Carte
                    </button>
                    <p v-else class="text-wpx-gold mt-4 text-center text-xs">
                        Cette carte est suspendue. Sa réactivation sera traitée dans le cycle de vie Carte.
                    </p>
                </template>

                <section v-else class="border-wpx-border-dark bg-wpx-navy-850 rounded-2xl border p-5 text-center">
                    <span
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br text-2xl"
                        >▣</span
                    >
                    <h3 class="text-wpx-white-soft mt-4 text-base font-bold">Activez votre Carte Wasplex virtuelle</h3>
                    <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                        {{ offer?.description ?? 'Une carte liée à votre compte, sans solde séparé du Wallet.' }}
                    </p>
                    <p class="text-wpx-success-light mt-3 text-sm font-extrabold">Gratuite</p>
                    <button
                        type="button"
                        :disabled="busy"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-5 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-40"
                        @click="issue"
                    >
                        {{ busy ? 'Activation…' : 'Créer ma carte virtuelle' }}
                    </button>
                </section>
            </div>

            <div
                v-if="qr"
                class="fixed inset-0 z-[60] flex items-center justify-center bg-black/80 p-5"
                @click.self="qr = null"
            >
                <div class="w-full max-w-xs rounded-3xl bg-white p-5 text-center shadow-2xl">
                    <p class="text-sm font-extrabold text-slate-900">QR d’identité Wasplex</p>
                    <p class="mt-1 text-[11px] text-slate-500">Valable 2 minutes · usage unique</p>
                    <svg
                        class="mx-auto mt-4 h-64 w-64"
                        :viewBox="`0 0 ${qrSize} ${qrSize}`"
                        role="img"
                        aria-label="QR Carte Wasplex"
                    >
                        <rect :width="qrSize" :height="qrSize" fill="white" />
                        <path :d="qrPath" fill="black" />
                    </svg>
                    <p class="mt-2 font-mono text-[10px] text-slate-500">{{ card?.public_identifier }}</p>
                    <button
                        type="button"
                        class="bg-wpx-navy-950 mt-4 w-full rounded-xl px-4 py-2.5 text-sm font-bold text-white"
                        @click="qr = null"
                    >
                        Masquer le QR
                    </button>
                </div>
            </div>

            <CardPaymentPanel v-if="card" :open="paymentsOpen" :card="card" @close="paymentsOpen = false" />
        </div>
    </Teleport>
</template>
