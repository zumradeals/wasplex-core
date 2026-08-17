<script setup lang="ts">
import { onMounted, reactive, watch } from 'vue';
import http from '@/lib/http';
import { useWalletPrivacy } from '@/lib/walletPrivacy';

type Category = {
    key: string;
    label: string;
    description: string;
    icon: string;
    count: number;
};

type Entry = {
    id: string;
    direction: 'debit' | 'credit';
    amount_minor: number;
    description: string | null;
    type: string | null;
    source_module: string | null;
    created_at: string | null;
};

type Bucket = {
    entries: Entry[];
    page: number;
    lastPage: number;
    loading: boolean;
    loaded: boolean;
};

const props = defineProps<{ refreshKey?: number }>();
const categories = reactive<Category[]>([]);
const open = reactive<Record<string, boolean>>({});
const buckets = reactive<Record<string, Bucket>>({});
const numberFormatter = new Intl.NumberFormat('fr-FR');
const { maskAmount } = useWalletPrivacy();

function amountLabel(entry: Entry): string {
    const sign = entry.direction === 'credit' ? '+' : '−';

    return `${sign}${maskAmount(numberFormatter.format(entry.amount_minor))} WP`;
}

function bucket(key: string): Bucket {
    buckets[key] ??= { entries: [], page: 0, lastPage: 1, loading: false, loaded: false };
    return buckets[key];
}

function describe(entry: Entry): string {
    if (entry.description) return entry.description;
    if (entry.type === 'USER_WALLET_DEPOSIT') return 'Dépôt Wallet';
    if (entry.type === 'USER_TRANSFER') return entry.direction === 'credit' ? 'Transfert reçu' : 'Transfert envoyé';
    if (entry.type === 'FUND_MEMBERSHIP_FEE') return 'Adhésion au programme Fonds';
    return entry.type ?? (entry.direction === 'credit' ? 'Crédit Wallet' : 'Débit Wallet');
}

async function loadCategories(): Promise<void> {
    const { data } = await http.get('/me/wallet/history', { params: { summary: 1 } });
    categories.splice(0, categories.length, ...(data.categories ?? []));
}

async function loadCategory(key: string, reset = false): Promise<void> {
    const state = bucket(key);
    if (state.loading) return;

    state.loading = true;
    try {
        const page = reset ? 1 : state.page + 1;
        const { data } = await http.get('/me/wallet/history', {
            params: { category: key, page, per_page: 5 },
        });
        const pagination = data.history;
        const entries: Entry[] = pagination?.data ?? [];
        state.entries = reset ? entries : [...state.entries, ...entries];
        state.page = pagination?.current_page ?? page;
        state.lastPage = pagination?.last_page ?? state.page;
        state.loaded = true;

        const updated = (data.categories ?? []).find((item: Category) => item.key === key);
        const current = categories.find((item) => item.key === key);
        if (updated && current) current.count = updated.count;
    } finally {
        state.loading = false;
    }
}

async function toggle(category: Category): Promise<void> {
    open[category.key] = !open[category.key];
    if (open[category.key] && !bucket(category.key).loaded) {
        await loadCategory(category.key, true);
    }
}

async function refresh(): Promise<void> {
    await loadCategories();
    await Promise.all(
        categories.filter((category) => open[category.key]).map((category) => loadCategory(category.key, true)),
    );
}

onMounted(loadCategories);
watch(() => props.refreshKey, refresh);
</script>

<template>
    <section class="flex flex-col gap-2.5">
        <div>
            <h2 class="text-wpx-white-soft text-sm font-bold">Historique</h2>
            <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                Chaque module conserve sa vue, alimentée par le même Grand Livre.
            </p>
        </div>

        <article
            v-for="category in categories"
            :key="category.key"
            class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-lg overflow-hidden border"
        >
            <button type="button" class="flex w-full items-center gap-3 p-3.5 text-left" @click="toggle(category)">
                <span
                    class="bg-wpx-navy-750 text-wpx-gold flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-base font-bold"
                >
                    {{ category.icon }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="text-wpx-white-soft block text-sm font-bold">{{ category.label }}</span>
                    <span class="text-wpx-muted-dark mt-0.5 block truncate text-[10px]">{{
                        category.description
                    }}</span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="
                            category.count
                                ? 'bg-wpx-success/12 text-wpx-success-light'
                                : 'text-wpx-muted-dark bg-white/5'
                        "
                        >{{ category.count }}</span
                    >
                    <span class="text-wpx-muted-dark text-sm">{{ open[category.key] ? '⌃' : '⌄' }}</span>
                </span>
            </button>

            <div v-if="open[category.key]" class="border-wpx-border-dark border-t px-3.5 pb-3.5">
                <p
                    v-if="bucket(category.key).loading && !bucket(category.key).loaded"
                    class="text-wpx-muted-dark py-4 text-center text-xs"
                >
                    Chargement…
                </p>
                <ul v-else class="divide-wpx-border-dark divide-y">
                    <li
                        v-for="entry in bucket(category.key).entries"
                        :key="entry.id"
                        class="flex items-center justify-between gap-3 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-wpx-white-soft truncate text-sm font-semibold">{{ describe(entry) }}</p>
                            <p class="text-wpx-muted-dark mt-0.5 text-[10px]">
                                {{ entry.created_at ? new Date(entry.created_at).toLocaleString('fr-FR') : '' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 text-sm font-bold tabular-nums"
                            :class="entry.direction === 'credit' ? 'text-wpx-success-light' : 'text-wpx-orange'"
                        >
                            {{ amountLabel(entry) }}
                        </span>
                    </li>
                    <li
                        v-if="bucket(category.key).entries.length === 0"
                        class="text-wpx-muted-dark py-5 text-center text-xs"
                    >
                        Aucun mouvement dans cette catégorie.
                    </li>
                </ul>
                <button
                    v-if="bucket(category.key).page < bucket(category.key).lastPage"
                    type="button"
                    class="border-wpx-border-dark text-wpx-blue mt-2 w-full rounded-xl border px-3 py-2.5 text-xs font-bold disabled:opacity-50"
                    :disabled="bucket(category.key).loading"
                    @click="loadCategory(category.key)"
                >
                    {{ bucket(category.key).loading ? 'Chargement…' : 'Voir plus' }}
                </button>
            </div>
        </article>
    </section>
</template>
