<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';
import { shellPathForSpaceType, type SpaceSummary } from '@/types/identity';

const props = withDefaults(
    defineProps<{
        spaces: SpaceSummary[];
        activeSpaceId: string | null;
        variant?: 'light' | 'dark';
    }>(),
    { variant: 'light' },
);

async function onChange(event: Event): Promise<void> {
    const userSpaceId = (event.target as HTMLSelectElement).value;
    const space = props.spaces.find((s) => s.user_space_id === userSpaceId);

    if (!space) {
        return;
    }

    await http.post(`/me/spaces/${userSpaceId}/switch`);
    router.visit(shellPathForSpaceType(space.space_type));
}

function label(space: SpaceSummary): string {
    if (space.space_type === 'advertiser') {
        return `Annonceur — ${space.organization_name ?? ''}`;
    }

    return space.space_type === 'admin' ? 'Administration' : 'Mon espace';
}
</script>

<template>
    <select
        :value="activeSpaceId ?? ''"
        class="rounded-wpx-full border px-3 py-1.5 text-xs font-bold"
        :class="
            variant === 'dark'
                ? 'border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft'
                : 'border-wpx-border bg-wpx-canvas text-wpx-text'
        "
        @change="onChange"
    >
        <option v-for="space in spaces" :key="space.user_space_id" :value="space.user_space_id">
            {{ label(space) }}
        </option>
    </select>
</template>
