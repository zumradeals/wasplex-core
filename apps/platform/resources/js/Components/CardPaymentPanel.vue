<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import http from '@/lib/http';
import { makeQrMatrix } from '@/lib/qrCode';

interface CardSummary {
    id: string;
    public_identifier: string;
    display_name: string;
}

interface ReceiveQr {
    payload: string;
    amount_minor: number | null;
    currency: string;
    note: string | null;
    expires_at: string;
}

interface PaymentPreview {
    action: string;
    recipient: {
        public_identifier: string;
        display_name: string;
        offer_name: string;
        verified: boolean;
    };
    amount_minor: number | null;
    currency: string;
    note: string | null;
    expires_at: string;
    amount_locked: boolean;
    available_minor: number;
    max_payment_minor: number;
}

interface Receipt {
    id: string;
    receipt_reference: string;
    status: string;
    direction: 'sent' | 'received';
    amount_minor: number;
    currency: string;
    note: string | null;
    counterparty: {
        display_name: string;
        public_identifier: string | null;
    };
    posted_at: string | null;
}

interface BarcodeResult {
    rawValue?: string;
}

interface BarcodeDetectorLike {
    detect(source: HTMLVideoElement): Promise<BarcodeResult[]>;
}

type BarcodeDetectorConstructor = new (options?: { formats?: string[] }) => BarcodeDetectorLike;
type Mode = 'home' | 'receive' | 'scan' | 'confirm' | 'receipt';

const props = defineProps<{ open: boolean; card: CardSummary }>();
const emit = defineEmits<{ close: [] }>();

const mode = ref<Mode>('home');
const busy = ref(false);
const error = ref<string | null>(null);
const receiveAmount = ref('');
const receiveNote = ref('');
const receiveQr = ref<ReceiveQr | null>(null);
const scanPayload = ref('');
const preview = ref<PaymentPreview | null>(null);
const paymentAmount = ref('');
const idempotencyKey = ref('');
const receipt = ref<Receipt | null>(null);
const operations = ref<Receipt[]>([]);
const video = ref<HTMLVideoElement | null>(null);
const cameraMessage = ref<string | null>(null);
let cameraStream: MediaStream | null = null;
let animationFrame: number | null = null;

const receiveMatrix = computed(() => (receiveQr.value ? makeQrMatrix(receiveQr.value.payload) : []));
const receivePath = computed(() => {
    const commands: string[] = [];
    receiveMatrix.value.forEach((row, y) =>
        row.forEach((cell, x) => {
            if (cell) commands.push(`M${x + 4} ${y + 4}h1v1h-1z`);
        }),
    );
    return commands.join('');
});
const receiveQrSize = computed(() => (receiveMatrix.value.length || 41) + 8);
const amountToPay = computed(() => {
    if (!preview.value) return 0;
    if (preview.value.amount_locked) return preview.value.amount_minor ?? 0;
    return Number(paymentAmount.value || 0);
});

function messageFrom(errorValue: unknown): string {
    const response = (errorValue as { response?: { data?: { message?: string } } })?.response;
    return response?.data?.message ?? 'Une erreur est survenue. Réessayez.';
}

function formatWp(value: number): string {
    return `${new Intl.NumberFormat('fr-FR').format(value)} WP`;
}

function resetFlow(): void {
    stopCamera();
    mode.value = 'home';
    error.value = null;
    receiveQr.value = null;
    scanPayload.value = '';
    preview.value = null;
    paymentAmount.value = '';
    idempotencyKey.value = '';
    receipt.value = null;
}

async function loadOperations(): Promise<void> {
    try {
        const { data } = await http.get('/cards/operations');
        operations.value = data.operations ?? [];
    } catch {
        operations.value = [];
    }
}

async function generateReceive(): Promise<void> {
    busy.value = true;
    error.value = null;
    try {
        const amountText = String(receiveAmount.value ?? '').trim();
        const amount = amountText === '' ? null : Number(amountText);
        const { data } = await http.post(`/cards/${props.card.id}/receive-qr`, {
            amount_minor: amount,
            note: receiveNote.value.trim() || null,
        });
        receiveQr.value = data.qr;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function openScanner(): Promise<void> {
    mode.value = 'scan';
    error.value = null;
    cameraMessage.value = null;
    await nextTick();
    await startCamera();
}

async function startCamera(): Promise<void> {
    stopCamera();
    const Detector = (window as unknown as { BarcodeDetector?: BarcodeDetectorConstructor }).BarcodeDetector;
    if (!Detector || !navigator.mediaDevices?.getUserMedia || !video.value) {
        cameraMessage.value =
            'Scanner caméra indisponible sur ce navigateur. Vous pouvez coller le contenu du QR ci-dessous.';
        return;
    }

    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        video.value.srcObject = cameraStream;
        await video.value.play();
        const detector = new Detector({ formats: ['qr_code'] });

        const scan = async (): Promise<void> => {
            if (!video.value || !cameraStream) return;
            try {
                const results = await detector.detect(video.value);
                const rawValue = results[0]?.rawValue;
                if (rawValue) {
                    stopCamera();
                    scanPayload.value = rawValue;
                    await resolvePayload();
                    return;
                }
            } catch {
                cameraMessage.value = 'La caméra est ouverte, mais la lecture automatique du QR n’est pas disponible.';
                stopCamera();
                return;
            }
            animationFrame = window.requestAnimationFrame(() => void scan());
        };

        animationFrame = window.requestAnimationFrame(() => void scan());
    } catch {
        cameraMessage.value = 'Accès caméra refusé ou indisponible. Collez le contenu du QR pour continuer.';
        stopCamera();
    }
}

function stopCamera(): void {
    if (animationFrame !== null) {
        window.cancelAnimationFrame(animationFrame);
        animationFrame = null;
    }
    cameraStream?.getTracks().forEach((track) => track.stop());
    cameraStream = null;
    if (video.value) video.value.srcObject = null;
}

async function resolvePayload(): Promise<void> {
    if (scanPayload.value.trim() === '') return;
    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/card-scan/resolve', { payload: scanPayload.value.trim() });
        preview.value = data.payment;
        paymentAmount.value = preview.value?.amount_minor ? String(preview.value.amount_minor) : '';
        idempotencyKey.value = crypto.randomUUID?.() ?? `card-${Date.now()}-${Math.random().toString(16).slice(2)}`;
        stopCamera();
        mode.value = 'confirm';
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function confirmPayment(): Promise<void> {
    if (!preview.value || amountToPay.value < 1) {
        error.value = 'Saisissez un montant supérieur à zéro.';
        return;
    }

    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post('/card-scan/payment', {
            payload: scanPayload.value.trim(),
            amount_minor: preview.value.amount_locked ? preview.value.amount_minor : amountToPay.value,
            idempotency_key: idempotencyKey.value,
        });
        receipt.value = data.receipt;
        mode.value = 'receipt';
        await loadOperations();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

function close(): void {
    resetFlow();
    emit('close');
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            resetFlow();
            void loadOperations();
        } else {
            stopCamera();
        }
    },
    { immediate: true },
);

onBeforeUnmount(stopCamera);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[70] flex justify-center bg-black/80 p-3 sm:p-5" @click.self="close">
            <div
                class="bg-wpx-navy-950 border-wpx-border-dark max-h-full w-full max-w-md overflow-y-auto rounded-3xl border p-4 shadow-2xl"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-gold text-[10px] font-bold tracking-[0.18em] uppercase">Carte Wasplex</p>
                        <h3 class="text-wpx-white-soft mt-1 text-lg font-extrabold">Paiements Carte</h3>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            Recevoir ou payer avec votre Wallet, sans solde Carte séparé.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="bg-wpx-navy-750 text-wpx-white-soft h-9 w-9 rounded-full text-xl"
                        @click="close"
                    >
                        ×
                    </button>
                </div>

                <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger mt-4 rounded-xl px-3 py-2 text-xs" role="alert">
                    {{ error }}
                </p>

                <template v-if="mode === 'home'">
                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 rounded-2xl bg-gradient-to-r p-4 text-left"
                            @click="mode = 'receive'"
                        >
                            <span class="text-xl">↓</span>
                            <span class="mt-3 block text-sm font-extrabold">Recevoir des WP</span>
                            <span class="mt-1 block text-[11px] opacity-80">Créer un QR de réception temporaire.</span>
                        </button>
                        <button
                            type="button"
                            class="border-wpx-border-dark bg-wpx-navy-850 text-wpx-white-soft rounded-2xl border p-4 text-left"
                            @click="openScanner"
                        >
                            <span class="text-wpx-gold text-xl">⌗</span>
                            <span class="mt-3 block text-sm font-extrabold">Scanner pour payer</span>
                            <span class="text-wpx-muted-dark mt-1 block text-[11px]"
                                >Vérifier avant toute confirmation.</span
                            >
                        </button>
                    </div>

                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-4 rounded-2xl border p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-wpx-white-soft text-sm font-bold">Mes dernières opérations</p>
                            <span class="text-wpx-muted-dark text-[10px]">Grand Livre</span>
                        </div>
                        <p v-if="operations.length === 0" class="text-wpx-muted-dark mt-3 text-xs">
                            Aucun paiement Carte pour le moment.
                        </p>
                        <div v-else class="mt-2 divide-y divide-white/5">
                            <div
                                v-for="operation in operations.slice(0, 5)"
                                :key="operation.id"
                                class="flex items-center justify-between gap-3 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-wpx-white-soft truncate text-xs font-semibold">
                                        {{ operation.counterparty.display_name }}
                                    </p>
                                    <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                                        {{ operation.receipt_reference }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p
                                        :class="
                                            operation.direction === 'received'
                                                ? 'text-wpx-success-light'
                                                : 'text-wpx-white-soft'
                                        "
                                        class="text-xs font-extrabold"
                                    >
                                        {{ operation.direction === 'received' ? '+' : '−'
                                        }}{{ formatWp(operation.amount_minor) }}
                                    </p>
                                    <p class="text-wpx-muted-dark mt-0.5 text-[9px]">
                                        {{ operation.direction === 'received' ? 'Reçu' : 'Envoyé' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <template v-else-if="mode === 'receive'">
                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-5 rounded-2xl border p-4">
                        <p class="text-wpx-white-soft text-sm font-bold">Créer un QR de réception</p>
                        <label class="text-wpx-muted-dark mt-4 block text-[11px]">Montant en WP · facultatif</label>
                        <input
                            v-model="receiveAmount"
                            type="number"
                            min="1"
                            inputmode="numeric"
                            placeholder="Ex. 5000"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none"
                        />
                        <label class="text-wpx-muted-dark mt-3 block text-[11px]">Note · facultative</label>
                        <input
                            v-model="receiveNote"
                            type="text"
                            maxlength="180"
                            placeholder="Ex. Participation repas"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-sm outline-none"
                        />
                        <button
                            type="button"
                            :disabled="busy"
                            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-40"
                            @click="generateReceive"
                        >
                            {{ busy ? 'Création…' : 'Générer le QR de réception' }}
                        </button>
                    </section>

                    <section v-if="receiveQr" class="mt-4 rounded-3xl bg-white p-5 text-center">
                        <p class="text-sm font-extrabold text-slate-900">Recevoir sur ma Carte Wasplex</p>
                        <p class="mt-1 text-[11px] text-slate-500">
                            Valable 5 minutes · utilisé seulement après paiement réussi
                        </p>
                        <svg
                            class="mx-auto mt-4 h-64 w-64"
                            :viewBox="`0 0 ${receiveQrSize} ${receiveQrSize}`"
                            role="img"
                            aria-label="QR de réception Carte Wasplex"
                        >
                            <rect :width="receiveQrSize" :height="receiveQrSize" fill="white" />
                            <path :d="receivePath" fill="black" />
                        </svg>
                        <p class="mt-2 font-mono text-[10px] text-slate-500">{{ card.public_identifier }}</p>
                        <p v-if="receiveQr.amount_minor" class="mt-2 text-base font-extrabold text-slate-900">
                            {{ formatWp(receiveQr.amount_minor) }}
                        </p>
                        <p v-if="receiveQr.note" class="mt-1 text-xs text-slate-600">{{ receiveQr.note }}</p>
                    </section>

                    <button
                        type="button"
                        class="text-wpx-muted-dark mt-4 w-full py-2 text-xs font-bold"
                        @click="resetFlow"
                    >
                        ← Retour
                    </button>
                </template>

                <template v-else-if="mode === 'scan'">
                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-5 overflow-hidden rounded-2xl border p-4">
                        <p class="text-wpx-white-soft text-sm font-bold">Scanner un QR de réception</p>
                        <video
                            ref="video"
                            class="bg-wpx-navy-950 mt-3 aspect-square w-full rounded-2xl object-cover"
                            playsinline
                            muted
                        ></video>
                        <p v-if="cameraMessage" class="text-wpx-gold mt-2 text-[11px] leading-relaxed">
                            {{ cameraMessage }}
                        </p>
                        <label class="text-wpx-muted-dark mt-4 block text-[11px]">Ou coller le contenu du QR</label>
                        <textarea
                            v-model="scanPayload"
                            rows="2"
                            placeholder="WPLX:RECEIVE:…"
                            class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full resize-none rounded-xl border px-3 py-3 font-mono text-xs outline-none"
                        ></textarea>
                        <button
                            type="button"
                            :disabled="busy || scanPayload.trim() === ''"
                            class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-3 w-full rounded-xl bg-gradient-to-r px-4 py-3 text-sm font-extrabold disabled:opacity-40"
                            @click="resolvePayload"
                        >
                            {{ busy ? 'Vérification…' : 'Vérifier le bénéficiaire' }}
                        </button>
                    </section>
                    <button
                        type="button"
                        class="text-wpx-muted-dark mt-4 w-full py-2 text-xs font-bold"
                        @click="resetFlow"
                    >
                        ← Retour
                    </button>
                </template>

                <template v-else-if="mode === 'confirm' && preview">
                    <section class="border-wpx-border-dark bg-wpx-navy-850 mt-5 rounded-2xl border p-4">
                        <p class="text-wpx-gold text-[10px] font-bold tracking-[0.15em] uppercase">
                            Bénéficiaire vérifié
                        </p>
                        <p class="text-wpx-white-soft mt-2 text-lg font-extrabold">
                            {{ preview.recipient.display_name }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 font-mono text-[11px]">
                            {{ preview.recipient.public_identifier }}
                        </p>
                        <p v-if="preview.note" class="text-wpx-muted-dark mt-3 text-xs">{{ preview.note }}</p>

                        <div class="border-wpx-border-dark mt-4 border-t pt-4">
                            <p class="text-wpx-muted-dark text-[11px]">Votre solde disponible</p>
                            <p class="text-wpx-white-soft mt-1 text-base font-extrabold">
                                {{ formatWp(preview.available_minor) }}
                            </p>
                        </div>

                        <div v-if="preview.amount_locked" class="mt-4">
                            <p class="text-wpx-muted-dark text-[11px]">Montant demandé</p>
                            <p class="text-wpx-gold mt-1 text-2xl font-extrabold">
                                {{ formatWp(preview.amount_minor ?? 0) }}
                            </p>
                        </div>
                        <div v-else class="mt-4">
                            <label class="text-wpx-muted-dark block text-[11px]">Montant à payer en WP</label>
                            <input
                                v-model="paymentAmount"
                                type="number"
                                min="1"
                                :max="preview.max_payment_minor"
                                inputmode="numeric"
                                class="border-wpx-border-dark bg-wpx-navy-950 text-wpx-white-soft mt-1 w-full rounded-xl border px-3 py-3 text-lg font-extrabold outline-none"
                            />
                        </div>
                    </section>

                    <section class="bg-wpx-gold/10 border-wpx-gold/25 mt-4 rounded-2xl border p-4">
                        <p class="text-wpx-white-soft text-xs font-bold">Confirmation obligatoire</p>
                        <p class="text-wpx-muted-dark mt-1 text-[11px] leading-relaxed">
                            Le scan seul ne débite rien. En confirmant, votre Wallet sera débité et le bénéficiaire
                            crédité dans le Grand Livre.
                        </p>
                    </section>

                    <button
                        type="button"
                        :disabled="busy || amountToPay < 1"
                        class="from-wpx-orange to-wpx-gold text-wpx-navy-950 mt-4 w-full rounded-xl bg-gradient-to-r px-4 py-3.5 text-sm font-extrabold disabled:opacity-40"
                        @click="confirmPayment"
                    >
                        {{ busy ? 'Paiement…' : `Confirmer et payer ${formatWp(amountToPay)}` }}
                    </button>
                    <button
                        type="button"
                        :disabled="busy"
                        class="text-wpx-muted-dark mt-2 w-full py-2 text-xs font-bold"
                        @click="resetFlow"
                    >
                        Annuler
                    </button>
                </template>

                <template v-else-if="mode === 'receipt' && receipt">
                    <section class="border-wpx-success/30 bg-wpx-success/10 mt-5 rounded-3xl border p-5 text-center">
                        <div
                            class="bg-wpx-success/15 text-wpx-success-light mx-auto flex h-14 w-14 items-center justify-center rounded-full text-2xl"
                        >
                            ✓
                        </div>
                        <p class="text-wpx-white-soft mt-4 text-lg font-extrabold">Paiement confirmé</p>
                        <p class="text-wpx-gold mt-2 text-2xl font-extrabold">{{ formatWp(receipt.amount_minor) }}</p>
                        <p class="text-wpx-muted-dark mt-2 text-xs">{{ receipt.counterparty.display_name }}</p>
                        <p class="text-wpx-muted-dark mt-4 font-mono text-[10px]">
                            Reçu {{ receipt.receipt_reference }}
                        </p>
                    </section>
                    <button
                        type="button"
                        class="bg-wpx-navy-750 text-wpx-white-soft mt-4 w-full rounded-xl px-4 py-3 text-sm font-bold"
                        @click="resetFlow"
                    >
                        Terminer
                    </button>
                </template>
            </div>
        </div>
    </Teleport>
</template>