<script setup lang="ts">
import { router } from '@inertiajs/vue3';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

defineProps<{ spaces: Space[] }>();

const switchSpace = (space: Space): void => {
    if (!space.active) {
        router.post(`/espaces/${space.id}/activer`);
    }
};
</script>

<template>
    <div class="flex flex-wrap gap-2" aria-label="Changer d'espace">
        <button
            v-for="space in spaces"
            :key="space.id"
            type="button"
            class="rounded-full border px-3 py-2 text-xs font-bold transition"
            :class="
                space.active
                    ? 'border-wasplex-cyan/40 bg-wasplex-cyan/15 text-wasplex-cyan'
                    : 'border-white/10 bg-white/5 text-white/55 hover:border-white/25 hover:text-white'
            "
            :aria-current="space.active ? 'page' : undefined"
            @click="switchSpace(space)"
        >
            {{ space.label }}
        </button>
    </div>
</template>
