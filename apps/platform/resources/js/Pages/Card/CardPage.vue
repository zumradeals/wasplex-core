<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import CardPanel from '@/Components/CardPanel.vue';

function close(): void {
    router.visit('/app?tab=espace');
}
</script>

<template>
    <div class="bg-wpx-navy-950 min-h-screen">
        <CardPanel :open="true" @close="close" />
    </div>
</template>
