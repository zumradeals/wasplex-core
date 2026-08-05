<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import http from '@/lib/http';
import { shellPathForSpaceType, type SpaceSummary } from '@/types/identity';

const props = defineProps<{
    spaces: SpaceSummary[];
    activeSpaceId: string | null;
}>();

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
        class="rounded-wasplex-sm border border-black/10 bg-white px-2 py-1 text-sm"
        @change="onChange"
    >
        <option v-for="space in spaces" :key="space.user_space_id" :value="space.user_space_id">
            {{ label(space) }}
        </option>
    </select>
</template>
