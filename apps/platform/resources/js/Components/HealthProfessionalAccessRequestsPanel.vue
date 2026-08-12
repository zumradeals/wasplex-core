<script setup lang="ts">
import { onMounted, ref } from 'vue';
import http from '@/lib/http';

type AccessRequest = {
    id: string;
    institution_name: string | null;
    purpose: string;
    status: string;
    justification: string;
    requested_at: string | null;
    decided_at: string | null;
    expires_at: string | null;
    used_at: string | null;
};

const loading = ref(true);
const savingId = ref<string | null>(null);
const error = ref('');
const notice = ref('');
const requests = ref<AccessRequest[]>([]);

function statusLabel(status: string): string {
    return (
        {
            pending: 'Votre décision est attendue',
            approved: 'Autorisé',
            denied: 'Refusé',
            expired: 'Autorisation expirée',
        }[status] ?? status
    );
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/health/me/access-requests');
        requests.value = data.requests ?? [];
    } catch {
        error.value = 'Impossible de charger les demandes professionnelles.';
    } finally {
        loading.value = false;
    }
}

async function decide(id: string, decision: 'approve' | 'deny'): Promise<void> {
    savingId.value = id;
    error.value = '';
    notice.value = '';
    try {
        await http.post(`/health/me/access-requests/${id}/decision`, { decision });
        notice.value =
            decision === 'approve'
                ? 'Accès autorisé pour 24 heures. Chaque consultation sera inscrite dans votre historique.'
                : 'Demande refusée. Aucune donnée médicale n’a été ouverte.';
        await load();
    } catch {
        error.value = 'Cette demande ne peut plus être traitée ou a déjà changé.';
    } finally {
        savingId.value = null;
    }
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

onMounted(load);
</script>

<template>
    <section class="border-wpx-cyan/15 bg-wpx-cyan/5 mb-4 rounded-3xl border p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-wpx-white-soft text-sm font-black">Demandes d’accès professionnelles</p>
                <p class="text-wpx-muted-dark mt-1 text-[10px] leading-relaxed">
                    Un établissement ou professionnel vérifié doit vous demander l’autorisation avant un accès normal à
                    votre capsule. Une autorisation accordée expire après 24 heures.
                </p>
            </div>
            <button type="button" class="text-wpx-cyan text-[10px] font-black" @click="load">Rafraîchir</button>
        </div>

        <p v-if="notice" class="mt-3 rounded-2xl bg-emerald-500/10 px-3 py-2.5 text-[10px] font-bold text-emerald-300">
            {{ notice }}
        </p>
        <p v-if="error" class="mt-3 rounded-2xl bg-red-500/10 px-3 py-2.5 text-[10px] font-bold text-red-300">
            {{ error }}
        </p>

        <p v-if="loading" class="text-wpx-muted-dark py-6 text-center text-xs">Chargement…</p>

        <div v-else-if="requests.length" class="mt-4 space-y-3">
            <article
                v-for="item in requests"
                :key="item.id"
                class="border-wpx-border-dark bg-wpx-navy-950/55 rounded-2xl border p-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-wpx-white-soft text-xs font-black">
                            {{ item.institution_name || 'Acteur Santé vérifié' }}
                        </p>
                        <p class="text-wpx-muted-dark mt-1 text-[9px]">
                            Demandé le {{ formatDate(item.requested_at) }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-1 text-[8px] font-black"
                        :class="
                            item.status === 'pending'
                                ? 'bg-amber-400/10 text-amber-200'
                                : item.status === 'approved'
                                  ? 'bg-emerald-500/10 text-emerald-300'
                                  : 'text-wpx-muted-dark bg-white/5'
                        "
                    >
                        {{ statusLabel(item.status) }}
                    </span>
                </div>

                <div class="mt-3 rounded-xl bg-black/10 px-3 py-2.5">
                    <p class="text-wpx-muted-dark text-[9px] font-bold">Motif déclaré par l’acteur Santé</p>
                    <p class="text-wpx-white-soft mt-1 text-[10px] leading-relaxed">{{ item.justification }}</p>
                </div>

                <div v-if="item.status === 'approved'" class="text-wpx-muted-dark mt-3 text-[9px]">
                    Expire : {{ formatDate(item.expires_at) }}
                    <span v-if="item.used_at"> · Dernier accès : {{ formatDate(item.used_at) }}</span>
                </div>

                <div v-if="item.status === 'pending'" class="mt-3 grid grid-cols-2 gap-2">
                    <button
                        type="button"
                        :disabled="savingId === item.id"
                        class="rounded-xl bg-emerald-500/12 px-3 py-2.5 text-[10px] font-black text-emerald-300 disabled:opacity-50"
                        @click="decide(item.id, 'approve')"
                    >
                        Autoriser 24 h
                    </button>
                    <button
                        type="button"
                        :disabled="savingId === item.id"
                        class="rounded-xl bg-red-500/10 px-3 py-2.5 text-[10px] font-black text-red-300 disabled:opacity-50"
                        @click="decide(item.id, 'deny')"
                    >
                        Refuser
                    </button>
                </div>
            </article>
        </div>

        <p v-else class="text-wpx-muted-dark mt-3 rounded-2xl bg-black/10 py-6 text-center text-xs">
            Aucune demande d’accès professionnelle.
        </p>
    </section>
</template>
