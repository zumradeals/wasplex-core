<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useEcho } from '@laravel/echo-vue';
import { usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

type ReactionType = 'heart' | 'clap' | 'fire' | 'smile';

interface LiveComment {
    id: string;
    body: string;
    status: 'visible' | 'hidden';
    is_pinned: boolean;
    created_at: string | null;
    author: { account_id: string; display_name: string };
    reply_to: { id: string; display_name: string; excerpt: string } | null;
}

interface InteractionSettings {
    comments_enabled: boolean;
    slow_mode_seconds: number;
}

interface ApiError {
    response?: { data?: { message?: string } };
}

const props = defineProps<{
    liveId: string;
    mode: 'host' | 'viewer';
}>();

const emit = defineEmits<{ viewerCount: [count: number] }>();
const page = usePage<{ auth: AuthShared }>();
const comments = ref<LiveComment[]>([]);
const settings = ref<InteractionSettings>({ comments_enabled: true, slow_mode_seconds: 0 });
const silencedAccountIds = ref<string[]>([]);
const canComment = ref(true);
const restrictionMessage = ref<string | null>(null);
const draft = ref('');
const replyingTo = ref<LiveComment | null>(null);
const busy = ref(false);
const error = ref<string | null>(null);
const notice = ref<string | null>(null);
const reactionBursts = ref<Array<{ id: number; emoji: string }>>([]);
let reactionSequence = 0;
let syncPoll: ReturnType<typeof setInterval> | null = null;
const reactionTimers = new Set<ReturnType<typeof setTimeout>>();

const isHost = computed(() => props.mode === 'host');
const currentAccountId = computed(() => page.props.auth.account.id);
const pinnedComment = computed(() =>
    comments.value.find((comment) => comment.is_pinned && comment.status === 'visible'),
);
const recentComments = computed(() => comments.value.slice(-8));
const composerEnabled = computed(() => isHost.value || (settings.value.comments_enabled && canComment.value));

function messageFrom(cause: unknown): string {
    return (cause as ApiError)?.response?.data?.message ?? 'Impossible de mettre à jour les interactions du Live.';
}

function stateEndpoint(): string {
    return isHost.value ? `/advertiser/lives/${props.liveId}/comments` : `/lives/${props.liveId}/comments`;
}

function commentEndpoint(): string {
    return stateEndpoint();
}

function reactionEndpoint(): string {
    return isHost.value ? `/advertiser/lives/${props.liveId}/reactions` : `/lives/${props.liveId}/reactions`;
}

function upsertComment(comment: LiveComment): void {
    if (!isHost.value && comment.status === 'hidden') {
        comments.value = comments.value.filter((item) => item.id !== comment.id);
        if (replyingTo.value?.id === comment.id) replyingTo.value = null;
        return;
    }

    const index = comments.value.findIndex((item) => item.id === comment.id);
    if (index >= 0) {
        comments.value.splice(index, 1, comment);
    } else {
        comments.value.push(comment);
    }

    if (comments.value.length > 80) comments.value = comments.value.slice(-80);
}

function addReactionBurst(emoji: string): void {
    const id = ++reactionSequence;
    reactionBursts.value.push({ id, emoji });
    const timer = setTimeout(() => {
        reactionBursts.value = reactionBursts.value.filter((item) => item.id !== id);
        reactionTimers.delete(timer);
    }, 1800);
    reactionTimers.add(timer);
}

function formatTime(value: string | null): string {
    if (!value) return '';

    return new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(new Date(value));
}

async function load(): Promise<void> {
    error.value = null;
    try {
        const { data } = await http.get(stateEndpoint());
        comments.value = data.comments ?? [];
        settings.value = data.settings ?? settings.value;
        if (typeof data.viewer_count === 'number') emit('viewerCount', data.viewer_count);
        if (isHost.value) {
            silencedAccountIds.value = data.moderation?.silenced_account_ids ?? [];
        } else {
            canComment.value = data.viewer?.can_comment ?? true;
            restrictionMessage.value = data.viewer?.restriction_message ?? null;
        }
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function sendComment(): Promise<void> {
    const body = draft.value.trim();
    if (!body || !composerEnabled.value || busy.value) return;

    busy.value = true;
    error.value = null;
    try {
        const { data } = await http.post(commentEndpoint(), {
            body,
            parent_comment_id: replyingTo.value?.id ?? null,
        });
        upsertComment(data.comment);
        draft.value = '';
        replyingTo.value = null;
    } catch (cause) {
        error.value = messageFrom(cause);
    } finally {
        busy.value = false;
    }
}

async function react(reaction: ReactionType, emoji: string): Promise<void> {
    addReactionBurst(emoji);
    try {
        await http.post(reactionEndpoint(), { reaction });
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function report(comment: LiveComment): Promise<void> {
    if (isHost.value || comment.author.account_id === currentAccountId.value) return;
    error.value = null;
    try {
        await http.post(`/lives/${props.liveId}/comments/${comment.id}/report`, {
            category: 'other',
        });
        notice.value = 'Signalement envoyé à Wasplex.';
        setTimeout(() => {
            notice.value = null;
        }, 2500);
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function togglePin(comment: LiveComment): Promise<void> {
    error.value = null;
    try {
        const { data } = await http.post(`/advertiser/lives/${props.liveId}/comments/${comment.id}/pin`);
        comments.value = comments.value.map((item) => ({ ...item, is_pinned: false }));
        upsertComment(data.comment);
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function hide(comment: LiveComment): Promise<void> {
    error.value = null;
    try {
        const { data } = await http.post(`/advertiser/lives/${props.liveId}/comments/${comment.id}/hide`);
        upsertComment(data.comment);
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function toggleComments(): Promise<void> {
    await updateSettings({ comments_enabled: !settings.value.comments_enabled });
}

async function changeSlowMode(event: Event): Promise<void> {
    const target = event.target as HTMLSelectElement;
    await updateSettings({ slow_mode_seconds: Number(target.value) });
}

async function updateSettings(attributes: Partial<InteractionSettings>): Promise<void> {
    error.value = null;
    try {
        const { data } = await http.patch(`/advertiser/lives/${props.liveId}/interactions`, attributes);
        settings.value = data.settings;
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

async function toggleSilence(comment: LiveComment): Promise<void> {
    const accountId = comment.author.account_id;
    error.value = null;
    try {
        if (silencedAccountIds.value.includes(accountId)) {
            await http.delete(`/advertiser/lives/${props.liveId}/participants/${accountId}/silence`);
            silencedAccountIds.value = silencedAccountIds.value.filter((id) => id !== accountId);
        } else {
            await http.post(`/advertiser/lives/${props.liveId}/participants/${accountId}/silence`, {});
            silencedAccountIds.value = [...silencedAccountIds.value, accountId];
        }
    } catch (cause) {
        error.value = messageFrom(cause);
    }
}

useEcho(`live.${props.liveId}`, '.live.comment.created', (payload: { comment: LiveComment }) => {
    upsertComment(payload.comment);
});

useEcho(
    `live.${props.liveId}`,
    '.live.comment.updated',
    (payload: { comment: LiveComment; clear_pinned?: boolean }) => {
        if (payload.clear_pinned) {
            comments.value = comments.value.map((item) => ({ ...item, is_pinned: false }));
        }
        upsertComment(payload.comment);
    },
);

useEcho(`live.${props.liveId}`, '.live.settings.updated', (payload: { settings: InteractionSettings }) => {
    settings.value = payload.settings;
});

useEcho(
    `live.${props.liveId}`,
    '.live.restriction.updated',
    (payload: { account_id: string; can_comment: boolean; message: string | null }) => {
        if (payload.can_comment) {
            silencedAccountIds.value = silencedAccountIds.value.filter((id) => id !== payload.account_id);
        } else if (!silencedAccountIds.value.includes(payload.account_id)) {
            silencedAccountIds.value = [...silencedAccountIds.value, payload.account_id];
        }

        if (payload.account_id === currentAccountId.value) {
            canComment.value = payload.can_comment;
            restrictionMessage.value = payload.message;
        }
    },
);

useEcho(`live.${props.liveId}`, '.live.viewer-count.changed', (payload: { viewer_count: number }) => {
    emit('viewerCount', payload.viewer_count);
});

useEcho(`live.${props.liveId}`, '.live.reaction', (payload: { emoji: string; account_id: string }) => {
    if (payload.account_id !== currentAccountId.value) addReactionBurst(payload.emoji);
});

onMounted(() => {
    void load();
    syncPoll = setInterval(() => void load(), 30000);
});
onBeforeUnmount(() => {
    if (syncPoll) clearInterval(syncPoll);
    reactionTimers.forEach((timer) => clearTimeout(timer));
});
</script>

<template>
    <div class="pointer-events-none absolute inset-x-0 bottom-0 z-20 flex flex-col justify-end p-3 pt-16">
        <div class="pointer-events-none absolute right-3 bottom-16 flex flex-col-reverse items-center gap-1">
            <span v-for="burst in reactionBursts" :key="burst.id" class="animate-bounce text-3xl drop-shadow-lg">
                {{ burst.emoji }}
            </span>
        </div>

        <div
            v-if="pinnedComment"
            class="pointer-events-auto mb-2 rounded-xl bg-amber-300/95 px-3 py-2 text-slate-950 shadow-lg"
        >
            <p class="text-[9px] font-black tracking-wide uppercase">📌 Épinglé par l’hôte</p>
            <p class="mt-0.5 text-[11px] font-bold">
                {{ pinnedComment.author.display_name }} · {{ pinnedComment.body }}
            </p>
        </div>

        <div class="pointer-events-auto max-h-40 [scrollbar-width:none] space-y-1.5 overflow-y-auto pr-1">
            <div
                v-for="comment in recentComments"
                :key="comment.id"
                class="rounded-xl bg-black/55 px-2.5 py-2 backdrop-blur-sm"
                :class="comment.status === 'hidden' ? 'opacity-45' : ''"
            >
                <p v-if="comment.reply_to" class="mb-0.5 text-[9px] text-white/55">
                    ↪ {{ comment.reply_to.display_name }} · {{ comment.reply_to.excerpt }}
                </p>
                <div class="flex items-start justify-between gap-2">
                    <p class="min-w-0 text-[11px] leading-snug">
                        <span class="font-black">{{ comment.author.display_name }}</span>
                        <span class="ml-1 text-white/90" :class="comment.status === 'hidden' ? 'line-through' : ''">
                            {{ comment.body }}
                        </span>
                    </p>
                    <span class="shrink-0 text-[8px] text-white/40">{{ formatTime(comment.created_at) }}</span>
                </div>

                <div
                    v-if="comment.status === 'visible'"
                    class="mt-1 flex flex-wrap items-center gap-2 text-[9px] font-bold text-white/55"
                >
                    <button type="button" @click="replyingTo = comment">Répondre</button>
                    <button
                        v-if="!isHost && comment.author.account_id !== currentAccountId"
                        type="button"
                        @click="report(comment)"
                    >
                        Signaler
                    </button>
                    <template v-if="isHost">
                        <button type="button" class="text-amber-200" @click="togglePin(comment)">
                            {{ comment.is_pinned ? 'Désépingler' : 'Épingler' }}
                        </button>
                        <button type="button" class="text-red-200" @click="hide(comment)">Masquer</button>
                        <button
                            v-if="comment.author.account_id !== currentAccountId"
                            type="button"
                            class="text-orange-200"
                            @click="toggleSilence(comment)"
                        >
                            {{ silencedAccountIds.includes(comment.author.account_id) ? 'Réautoriser' : 'Silence' }}
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <p
            v-if="notice"
            class="pointer-events-auto mt-2 rounded-lg bg-emerald-500/90 px-2.5 py-1.5 text-[10px] font-bold text-white"
        >
            {{ notice }}
        </p>
        <p
            v-if="error"
            class="pointer-events-auto mt-2 rounded-lg bg-red-700/90 px-2.5 py-1.5 text-[10px] font-bold text-white"
        >
            {{ error }}
        </p>

        <div
            v-if="isHost"
            class="pointer-events-auto mt-2 flex items-center gap-2 rounded-xl bg-black/65 p-2 backdrop-blur-sm"
        >
            <button
                type="button"
                class="rounded-full px-2.5 py-1.5 text-[9px] font-black"
                :class="settings.comments_enabled ? 'bg-emerald-400 text-slate-950' : 'bg-red-600 text-white'"
                @click="toggleComments"
            >
                {{ settings.comments_enabled ? '💬 Chat ouvert' : '🔒 Chat fermé' }}
            </button>
            <label class="ml-auto flex items-center gap-1 text-[9px] text-white/65">
                Mode lent
                <select
                    :value="settings.slow_mode_seconds"
                    class="rounded-lg bg-white/10 px-1.5 py-1 text-[9px] font-bold text-white outline-none"
                    @change="changeSlowMode"
                >
                    <option :value="0">Off</option>
                    <option :value="2">2 s</option>
                    <option :value="5">5 s</option>
                    <option :value="10">10 s</option>
                </select>
            </label>
        </div>

        <div
            v-if="replyingTo"
            class="pointer-events-auto mt-2 flex items-center justify-between rounded-lg bg-white/10 px-2.5 py-1.5 text-[9px]"
        >
            <span
                >Réponse à <strong>{{ replyingTo.author.display_name }}</strong></span
            >
            <button type="button" class="font-black" @click="replyingTo = null">×</button>
        </div>

        <div
            v-if="!isHost && !settings.comments_enabled"
            class="pointer-events-auto mt-2 rounded-xl bg-black/70 px-3 py-2 text-center text-[10px] font-bold"
        >
            🔒 Les commentaires sont temporairement fermés
        </div>
        <div
            v-else-if="!isHost && !canComment"
            class="pointer-events-auto mt-2 rounded-xl bg-black/70 px-3 py-2 text-center text-[10px] font-bold"
        >
            {{ restrictionMessage ?? 'Vous pouvez regarder ce Live, mais vous ne pouvez plus commenter.' }}
        </div>

        <div class="pointer-events-auto mt-2 flex items-center gap-1.5">
            <div
                v-if="composerEnabled"
                class="flex min-w-0 flex-1 items-center rounded-full bg-black/70 pr-1 pl-3 backdrop-blur-sm"
            >
                <input
                    v-model="draft"
                    maxlength="300"
                    placeholder="Écrire un commentaire…"
                    class="min-w-0 flex-1 bg-transparent py-2.5 text-[11px] text-white outline-none placeholder:text-white/45"
                    @keydown.enter.exact.prevent="sendComment"
                />
                <button
                    type="button"
                    class="rounded-full bg-white px-3 py-2 text-[10px] font-black text-slate-950 disabled:opacity-40"
                    :disabled="busy || draft.trim() === ''"
                    @click="sendComment"
                >
                    Envoyer
                </button>
            </div>

            <button
                type="button"
                class="rounded-full bg-black/70 p-2 text-base"
                aria-label="Envoyer un cœur"
                @click="react('heart', '❤️')"
            >
                ❤️
            </button>
            <button
                type="button"
                class="rounded-full bg-black/70 p-2 text-base"
                aria-label="Applaudir"
                @click="react('clap', '👏')"
            >
                👏
            </button>
        </div>
    </div>
</template>
