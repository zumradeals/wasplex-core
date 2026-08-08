<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ section: string; active?: boolean }>(), { active: false });

const color = computed(() => (props.active ? '#2BC4DE' : '#A9B7C8'));
</script>

<template>
    <svg v-if="section === 'dashboard'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="3" width="8" height="8" rx="2" :stroke="color" stroke-width="1.7" />
        <rect x="13" y="3" width="8" height="8" rx="2" :stroke="color" stroke-width="1.7" />
        <rect x="3" y="13" width="8" height="8" rx="2" :stroke="color" stroke-width="1.7" />
        <rect x="13" y="13" width="8" height="8" rx="2" :stroke="color" stroke-width="1.7" />
    </svg>
    <svg v-else-if="section === 'capabilities'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="4" y="10" width="16" height="10" rx="2" :stroke="color" stroke-width="1.7" />
        <path d="M8 10V7a4 4 0 018 0v3" :stroke="color" stroke-width="1.7" />
    </svg>
    <svg v-else-if="section === 'ledger'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="6" width="18" height="13" rx="3" :stroke="color" stroke-width="1.7" />
        <rect x="3" y="10" width="18" height="2.4" :fill="color" />
    </svg>
    <svg v-else-if="section === 'subscriptions'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path
            d="M12 3l2.4 5 5.6.6-4.1 3.8 1.1 5.5L12 15.2 7 17.9l1.1-5.5-4.1-3.8L9.6 8z"
            :stroke="color"
            stroke-width="1.6"
            stroke-linejoin="round"
        />
    </svg>
    <svg v-else-if="section === 'advertisers'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M3 10l14-6-4 16-3-6-6-4z" :stroke="color" stroke-width="1.7" stroke-linejoin="round" />
    </svg>
    <svg v-else-if="section === 'campaign-reviews'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path
            d="M12 3l7 3v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V6l7-3z"
            :stroke="color"
            stroke-width="1.6"
            stroke-linejoin="round"
        />
        <path d="M9 12l2 2 4-4" :stroke="color" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
    <svg v-else-if="section === 'smartprofile'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path
            d="M12 4a5 5 0 015 5v3l1.5 3h-13L7 12V9a5 5 0 015-5z"
            :stroke="color"
            stroke-width="1.6"
            stroke-linejoin="round"
        />
        <path d="M10 18a2 2 0 004 0" :stroke="color" stroke-width="1.6" />
    </svg>
    <svg v-else-if="section === 'matching'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <circle cx="12" cy="12" r="3.4" :stroke="color" stroke-width="1.6" />
        <path
            d="M12 3v3M12 18v3M4.2 7.8l2.6 1.5M17.2 14.7l2.6 1.5M4.2 16.2l2.6-1.5M17.2 9.3l2.6-1.5"
            :stroke="color"
            stroke-width="1.6"
            stroke-linecap="round"
        />
    </svg>
    <svg v-else-if="section === 'feed'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="3" y="4" width="18" height="16" rx="3" :stroke="color" stroke-width="1.7" />
        <path d="M10 9l5 3-5 3z" :fill="color" />
    </svg>
    <svg v-else-if="section === 'reconciliation'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path d="M12 3v18M4 8h16" :stroke="color" stroke-width="1.6" stroke-linecap="round" />
        <path
            d="M4 8l-2.5 5a2.5 2.5 0 005 0zM20 8l-2.5 5a2.5 2.5 0 005 0z"
            :stroke="color"
            stroke-width="1.5"
            stroke-linejoin="round"
        />
    </svg>
    <svg v-else-if="section === 'organizations'" width="16" height="16" viewBox="0 0 24 24" fill="none">
        <rect x="5" y="3" width="14" height="18" rx="1" :stroke="color" stroke-width="1.6" />
        <path
            d="M9 7h1M9 11h1M9 15h1M14 7h1M14 11h1M14 15h1"
            :stroke="color"
            stroke-width="1.6"
            stroke-linecap="round"
        />
    </svg>
    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none">
        <path
            d="M9 4h9a1 1 0 011 1v14a1 1 0 01-1 1H9M9 4L5 8m4-4v16m0 0L5 16"
            :stroke="color"
            stroke-width="1.6"
            stroke-linejoin="round"
        />
    </svg>
</template>
