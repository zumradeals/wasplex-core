<script setup lang="ts">
import Admin from './Admin.vue';

type EconomicVersion = {
    id: string;
    version: number;
    state: 'draft' | 'approved' | 'published' | 'suspended';
    publicName: string;
    quotaMonthly: number;
    weightBasisPoints: number;
    targetingCoefficientBasisPoints: number;
    fundEligible: boolean;
    effectiveFrom: string | null;
    effectiveTo: string | null;
    approvedAt: string | null;
    publishedAt: string | null;
    createdAt: string | null;
    suspensionReason: string | null;
};

type EconomicClass = {
    id: string;
    code: 'FREE' | 'PREMIUM' | 'GOLD' | 'PLATINUM';
    status: string;
    latest: EconomicVersion | null;
    published: EconomicVersion | null;
    versions: EconomicVersion[];
};

type Simulation = {
    total_basis_points: number;
    valid: boolean;
};

defineProps<{
    classes: EconomicClass[];
    flash: {
        success: string | null;
        simulation: Simulation | null;
    };
}>();
</script>

<template>
    <Admin :classes="classes" :flash="flash" />
</template>
