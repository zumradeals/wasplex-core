<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import http from '@/lib/http';
import { loadLiveKit, type LiveKitModule, type LiveKitParticipant, type LiveKitRoom } from '@/lib/livekitBrowser';

interface StageRequest {
    id: string;
    status: 'pending' | 'approved' | 'rejected' | 'withdrawn' | 'lowered';
    requested_at: string | null;
    participant: { account_id: string; display_name: string };
}

interface ApiError {
    response?: { data?: { message?: string } };
}

const props = defineProps<{
    liveId: string;
    mode: 'host' | 'viewer';
    viewerCount: number;
}>();

const emit = defineEmits<{ disconnected: [] }>();
const mediaGrid = ref<HTMLDivElement | null>(null);
const connected = ref(false);
const connecting = ref(false);
const error = ref<string | null>(null);
const cameraEnabled = ref(false);
const microphoneEnabled = ref(false);
const audioEnabled = ref(false);
const canPublish = ref(props.mode === 'host');
const stagePending = ref(false);
const stageRequests = ref<StageRequest[]>([]);
const stageBusyId = ref<string | null>(null);
const renderedParticipantCount = ref(0);
const connectionId = globalThis.crypto.randomUUID();

let room: LiveKitRoom | null = null;
let sdk: LiveKitModule | null = null;
let stagePoll: ReturnType<typeof setInterval> | null = null;

const pendingRequests = computed(() => stageRequests.value.filter((item) => item.status === 'pending'));
const activeSpeakers = computed(() => stageRequests.value.filter((item) => item.status === 'approved'));

function messageFrom(cause: unknown): string {
    return (cause as ApiError)?.response?.data?.message ?? 'La connexion vidéo temps réel a échoué.';
}

function participantLabel(participant: LiveKitParticipant): string {
    return (
        participant.name?.trim() || (participant.identity === room?.localParticipant.identity ? 'Vous' : 'Participant')
    );
}

function renderParticipants(): void {
    if (!mediaGrid.value || !room) return;

    const remoteParticipants = Array.from(room.remoteParticipants.values());
    const includeLocalParticipant = props.mode === 'host' || canPublish.value;
    const participants = includeLocalParticipant ? [room.localParticipant, ...remoteParticipants] : remoteParticipants;

    renderedParticipantCount.value = participants.length;
    mediaGrid.value.replaceChildren();
    mediaGrid.value.className = [
        'grid h-full w-full gap-1 bg-black',
        participants.length <= 1 ? 'grid-cols-1' : 'grid-cols-2',
    ].join(' ');

    participants.slice(0, 6).forEach((participant) => {
        const tile = document.createElement('div');
        tile.className = 'relative min-h-0 overflow-hidden bg-slate-950';
        let hasVideo = false;

        participant.getTrackPublications().forEach((publication) => {
            const track = publication.track;
            if (!track) return;

            const element = track.attach();
            if (track.kind === 'video') {
                hasVideo = true;
                element.className = 'absolute inset-0 h-full w-full object-cover';
                if (element instanceof HTMLVideoElement && participant.identity === room?.localParticipant.identity) {
                    element.muted = true;
                    element.playsInline = true;
                }
                tile.appendChild(element);
            } else if (participant.identity !== room?.localParticipant.identity) {
                element.className = 'hidden';
                tile.appendChild(element);
            }
        });

        if (!hasVideo) {
            const placeholder = document.createElement('div');
            placeholder.className =
                'absolute inset-0 flex items-center justify-center text-3xl font-black text-white/70';
            placeholder.textContent = participantLabel(participant).slice(0, 1).toUpperCase();
            tile.appendChild(placeholder);
        }

        const label = document.createElement('div');
        label.className =
            'absolute bottom-2 left-2 rounded-full bg-black/55 px-2 py-1 text-[10px] font-bold text-white';
        label.textContent = participantLabel(participant);
        tile.appendChild(label);
        mediaGrid.value?.appendChild(tile);
    });
}

function bindRoomEvents(): void {
    if (!room || !sdk) return;

    const rerenderEvents = [
        'TrackSubscribed',
        'TrackUnsubscribed',
        'ParticipantConnected',
        'ParticipantDisconnected',
        'LocalTrackPublished',
        'LocalTrackUnpublished',
        'TrackMuted',
        'TrackUnmuted',
    ];

    rerenderEvents.forEach((name) => {
        const event = sdk?.RoomEvent[name];
        if (event) room?.on(event, renderParticipants);
    });

    const permissionsEvent = sdk.RoomEvent.ParticipantPermissionsChanged;
    if (permissionsEvent) {
        room.on(permissionsEvent, () => {
            const allowed = room?.localParticipant.permissions?.canPublish === true;
            canPublish.value = props.mode === 'host' || allowed;
            if (allowed) stagePending.value = false;
            if (!canPublish.value) {
                cameraEnabled.value = false;
                microphoneEnabled.value = false;
                void room?.localParticipant.setCameraEnabled(false);
                void room?.localParticipant.setMicrophoneEnabled(false);
            }
            renderParticipants();
        });
    }
}

async function connectRoom(): Promise<void> {
    if (connected.value || connecting.value) return;
    connecting.value = true;
    error.value = null;

    try {
        const endpoint =
            props.mode === 'host'
                ? `/advertiser/lives/${props.liveId}/media-token`
                : `/lives/${props.liveId}/media-token`;
        const { data } = await http.post(endpoint, { connection_id: connectionId });
        sdk = await loadLiveKit();
        room = new sdk.Room({ adaptiveStream: true, dynacast: true });
        bindRoomEvents();
        await room.connect(data.media.url, data.media.token);

        connected.value = true;
        connecting.value = false;
        canPublish.value = props.mode === 'host' || data.media.can_publish === true;
        renderParticipants();

        if (props.mode === 'host') {
            startStagePolling();
            void enableCamera();
            void enableMicrophone();
        }
    } catch (cause) {
        if (room) {
            await room.disconnect().catch(() => undefined);
            room = null;
        }
        connected.value = false;
        renderedParticipantCount.value = 0;
        error.value = messageFrom(cause);
    } finally {
        connecting.value = false;
    }
}

async function enableCamera(): Promise<void> {
    if (!room || !canPublish.value) return;
    error.value = null;
    try {
        await room.localParticipant.setCameraEnabled(true);
        cameraEnabled.value = true;
        renderParticipants();
    } catch {
        error.value = 'Autorisez la caméra dans votre navigateur pour apparaître dans le Live.';
    }
}

async function toggleCamera(): Promise<void> {
    if (!room || !canPublish.value) return;
    const next = !cameraEnabled.value;
    error.value = null;
    try {
        await room.localParticipant.setCameraEnabled(next);
        cameraEnabled.value = next;
        renderParticipants();
    } catch {
        error.value = 'Impossible de modifier la caméra.';
    }
}

async function enableMicrophone(): Promise<void> {
    if (!room || !canPublish.value) return;
    error.value = null;
    try {
        await room.localParticipant.setMicrophoneEnabled(true);
        microphoneEnabled.value = true;
    } catch {
        error.value = 'Autorisez le microphone dans votre navigateur pour parler dans le Live.';
    }
}

async function toggleMicrophone(): Promise<void> {
    if (!room || !canPublish.value) return;
    const next = !microphoneEnabled.value;
    error.value = null;
    try {
        await room.localParticipant.setMicrophoneEnabled(next);
        microphoneEnabled.value = next;
    } catch {
        error.value = 'Impossible de modifier le microphone.';
    }
}

async function activateAudio(): Promise<void> {
    if (!room) return;
    error.value = null;
    try {
        await room.startAudio();
        audioEnabled.value = true;
    } catch {
        error.value = 'Touchez de nouveau pour autoriser le son du direct.';
    }
}

async function requestStage(): Promise<void> {
    error.value = null;
    try {
        const { data } = await http.post(`/lives/${props.liveId}/stage-request`, {
            connection_id: connectionId,
        });
        stagePending.value = data.stage_request.status === 'pending';
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function withdrawStage(): Promise<void> {
    try {
        await http.delete(`/lives/${props.liveId}/stage-request`);
        stagePending.value = false;
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function activateStageMedia(): Promise<void> {
    if (!canPublish.value) return;
    await enableCamera();
    await enableMicrophone();
    stagePending.value = false;
}

async function leaveStage(): Promise<void> {
    if (!room) return;
    try {
        await http.post(`/lives/${props.liveId}/stage-request/leave`);
        await room.localParticipant.setCameraEnabled(false);
        await room.localParticipant.setMicrophoneEnabled(false);
        cameraEnabled.value = false;
        microphoneEnabled.value = false;
        canPublish.value = false;
        renderParticipants();
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function loadStageRequests(): Promise<void> {
    if (props.mode !== 'host') return;
    try {
        const { data } = await http.get(`/advertiser/lives/${props.liveId}/stage-requests`);
        stageRequests.value = data.stage_requests ?? [];
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

function startStagePolling(): void {
    if (props.mode !== 'host' || stagePoll) return;
    void loadStageRequests();
    stagePoll = setInterval(() => void loadStageRequests(), 2000);
}

async function stageAction(item: StageRequest, action: 'approve' | 'reject' | 'lower'): Promise<void> {
    stageBusyId.value = item.id;
    error.value = null;
    try {
        await http.post(`/advertiser/lives/${props.liveId}/stage-requests/${item.id}/${action}`);
        await loadStageRequests();
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        stageBusyId.value = null;
    }
}

async function disconnectRoom(): Promise<void> {
    if (stagePoll) {
        clearInterval(stagePoll);
        stagePoll = null;
    }
    if (room) {
        await room.disconnect();
        room = null;
    }
    connected.value = false;
    renderedParticipantCount.value = 0;
    cameraEnabled.value = false;
    microphoneEnabled.value = false;
    audioEnabled.value = false;
    emit('disconnected');
}

onMounted(() => void connectRoom());
onBeforeUnmount(() => void disconnectRoom());
</script>

<template>
    <div class="relative overflow-hidden rounded-3xl bg-black text-white">
        <div class="relative aspect-[9/16] max-h-[72vh] min-h-[420px] w-full bg-black sm:aspect-video sm:max-h-[620px]">
            <div ref="mediaGrid" class="h-full w-full bg-black"></div>

            <div
                class="pointer-events-none absolute inset-x-0 top-0 flex items-center justify-between bg-gradient-to-b from-black/70 to-transparent p-3"
            >
                <div class="flex items-center gap-2">
                    <span class="rounded-md bg-red-600 px-2 py-1 text-[10px] font-black tracking-wide">LIVE</span>
                    <span class="rounded-full bg-black/45 px-2.5 py-1 text-[11px] font-bold">👁 {{ viewerCount }}</span>
                </div>
                <span v-if="connected" class="rounded-full bg-black/45 px-2.5 py-1 text-[10px] font-bold">
                    Temps réel connecté
                </span>
            </div>

            <div
                v-if="connecting"
                class="absolute inset-0 flex items-center justify-center bg-black/80 text-sm font-bold"
            >
                Connexion au direct…
            </div>
            <div
                v-else-if="!connected && error"
                class="absolute inset-0 flex flex-col items-center justify-center bg-black/85 px-6 text-center"
            >
                <p class="text-sm font-bold">{{ error }}</p>
                <button
                    type="button"
                    class="mt-4 rounded-full bg-white px-4 py-2 text-xs font-black text-black"
                    @click="connectRoom"
                >
                    Réessayer
                </button>
            </div>
            <div
                v-else-if="connected && mode === 'viewer' && renderedParticipantCount === 0"
                class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/70 px-6 text-center text-sm font-bold text-white/75"
            >
                En attente de la vidéo de l’hôte…
            </div>
        </div>

        <p
            v-if="connected && error"
            class="bg-red-950/80 px-4 py-2 text-center text-[11px] text-red-100"
            aria-live="polite"
        >
            {{ error }}
        </p>

        <div v-if="connected" class="flex flex-wrap items-center justify-center gap-2 bg-slate-950 px-3 py-3">
            <span class="rounded-full bg-emerald-500/15 px-3 py-2 text-[10px] font-black text-emerald-200">
                ● Connecté
            </span>
            <button type="button" class="rounded-full bg-white/10 px-3 py-2 text-xs font-bold" @click="activateAudio">
                {{ audioEnabled ? '🔊 Son actif' : '🔈 Activer le son' }}
            </button>

            <template v-if="canPublish">
                <button
                    type="button"
                    class="rounded-full bg-white/10 px-3 py-2 text-xs font-bold"
                    @click="toggleMicrophone"
                >
                    {{ microphoneEnabled ? '🎤 Micro actif' : '🔇 Micro coupé' }}
                </button>
                <button
                    type="button"
                    class="rounded-full bg-white/10 px-3 py-2 text-xs font-bold"
                    @click="toggleCamera"
                >
                    {{ cameraEnabled ? '📹 Caméra active' : '🚫 Caméra coupée' }}
                </button>
            </template>

            <template v-if="mode === 'viewer'">
                <button
                    v-if="!canPublish && !stagePending"
                    type="button"
                    class="rounded-full bg-amber-400 px-3 py-2 text-xs font-black text-slate-950"
                    @click="requestStage"
                >
                    ✋ Monter dans le Live
                </button>
                <button
                    v-else-if="stagePending && !canPublish"
                    type="button"
                    class="rounded-full bg-white/10 px-3 py-2 text-xs font-bold"
                    @click="withdrawStage"
                >
                    Demande envoyée · Annuler
                </button>
                <button
                    v-else-if="canPublish && !cameraEnabled && !microphoneEnabled"
                    type="button"
                    class="rounded-full bg-emerald-400 px-3 py-2 text-xs font-black text-slate-950"
                    @click="activateStageMedia"
                >
                    ✅ Invitation acceptée · Activer caméra
                </button>
                <button
                    v-else-if="canPublish"
                    type="button"
                    class="rounded-full bg-red-600/90 px-3 py-2 text-xs font-bold"
                    @click="leaveStage"
                >
                    Descendre du Live
                </button>
            </template>
        </div>

        <div
            v-if="mode === 'host' && connected && (pendingRequests.length || activeSpeakers.length)"
            class="border-t border-white/10 bg-slate-950 p-3"
        >
            <p class="text-xs font-black">Scène du Live</p>
            <div class="mt-2 space-y-2">
                <div
                    v-for="item in pendingRequests"
                    :key="item.id"
                    class="flex items-center justify-between gap-2 rounded-2xl bg-white/5 p-3"
                >
                    <div>
                        <p class="text-xs font-bold">✋ {{ item.participant.display_name }}</p>
                        <p class="mt-0.5 text-[10px] text-white/55">demande à monter</p>
                    </div>
                    <div class="flex gap-1.5">
                        <button
                            type="button"
                            class="rounded-full bg-emerald-400 px-3 py-1.5 text-[10px] font-black text-slate-950"
                            :disabled="stageBusyId === item.id"
                            @click="stageAction(item, 'approve')"
                        >
                            Accepter
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-bold"
                            :disabled="stageBusyId === item.id"
                            @click="stageAction(item, 'reject')"
                        >
                            Refuser
                        </button>
                    </div>
                </div>
                <div
                    v-for="item in activeSpeakers"
                    :key="item.id"
                    class="flex items-center justify-between gap-2 rounded-2xl bg-emerald-500/10 p-3"
                >
                    <p class="text-xs font-bold">🎥 {{ item.participant.display_name }} est sur scène</p>
                    <button
                        type="button"
                        class="rounded-full bg-red-600/85 px-3 py-1.5 text-[10px] font-bold"
                        :disabled="stageBusyId === item.id"
                        @click="stageAction(item, 'lower')"
                    >
                        Faire descendre
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
