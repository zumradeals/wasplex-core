<script setup lang="ts">
/**
 * Geste décoratif hérité de l'ancien design Wasplex ("Sécurité · Appuie sur
 * la même image"). AUCUN document `docs/` ne spécifie de CAPTCHA ni de
 * contrôle antibot par reconnaissance d'image — ce composant n'en est pas un.
 * C'est un frein ergonomique doux et une signature visuelle, pas une mesure
 * de sécurité. La sécurité réelle (rate limiting, sessions, MFA, audit) est
 * assurée côté serveur indépendamment de ce geste. Voir docs/chantiers/
 * P001-C-CHANTIER.md §5.1.
 */
import { computed, ref } from 'vue';

const EMOJI_POOL = ['🍎', '🍌', '🍇', '🍉', '🍒', '🍍', '🥝', '🍑', '🍋'] as const;
const GRID_SIZE = 6;

const emit = defineEmits<{ solved: [] }>();

const target = ref<string>('');
const grid = ref<string[]>([]);
const solved = ref(false);
const shake = ref(false);

function shuffle<T>(items: T[]): T[] {
    const copy = [...items];
    for (let i = copy.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

function roll(): void {
    const pool = shuffle([...EMOJI_POOL]);
    target.value = pool[0];
    grid.value = shuffle([pool[0], ...pool.slice(1, GRID_SIZE)]);
}

function pick(emoji: string): void {
    if (solved.value) {
        return;
    }

    if (emoji === target.value) {
        solved.value = true;
        emit('solved');
        return;
    }

    shake.value = true;
    setTimeout(() => {
        shake.value = false;
        roll();
    }, 350);
}

const helperText = computed(() => (solved.value ? 'Vérifié ✓' : `Sécurité · Appuie sur ${target.value}`));

roll();

defineExpose({ reset: roll });
</script>

<template>
    <div class="flex flex-col gap-2">
        <p class="text-wpx-muted-dark text-center text-xs font-medium tracking-wide uppercase">
            {{ helperText }}
        </p>
        <div
            class="grid grid-cols-3 gap-2 transition-transform"
            :class="{ 'animate-[shake_0.35s_ease-in-out]': shake }"
        >
            <button
                v-for="(emoji, index) in grid"
                :key="`${emoji}-${index}`"
                type="button"
                :disabled="solved"
                class="rounded-wpx-md border-wpx-border-dark bg-wpx-navy-750 flex h-14 items-center justify-center border text-2xl transition disabled:opacity-40"
                :class="
                    solved && emoji === target ? 'border-wpx-success ring-wpx-success ring-2' : 'hover:border-wpx-blue'
                "
                @click="pick(emoji)"
            >
                {{ emoji }}
            </button>
        </div>
    </div>
</template>

<style scoped>
@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-6px);
    }
    75% {
        transform: translateX(6px);
    }
}
</style>
