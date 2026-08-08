<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import http from '@/lib/http';
import type { AuthShared } from '@/types/identity';

const page = usePage<{ auth: AuthShared }>();

const advertiserSpace = computed(() => page.props.auth.spaces.find((s) => s.space_type === 'advertiser') ?? null);

const name = ref('');
const countryCode = ref('CI');
const error = ref<string | null>(null);
const submitting = ref(false);

async function submit(): Promise<void> {
    error.value = null;
    submitting.value = true;

    try {
        const { data } = await http.post('/organizations', {
            name: name.value,
            type: 'advertiser',
            country_code: countryCode.value,
        });

        const { data: spacesData } = await http.get('/me/spaces');
        const createdSpace = (spacesData.spaces as AuthShared['spaces']).find((s) => s.organization_id === data.id);

        if (createdSpace) {
            await http.post(`/me/spaces/${createdSpace.user_space_id}/switch`);
        }

        router.visit('/studio');
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        error.value = message ?? "La création de l'espace annonceur a échoué.";
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <a
        v-if="advertiserSpace"
        href="/studio"
        class="bg-wpx-navy-750 border-wpx-border-dark rounded-wpx-lg flex items-center gap-3 border p-4"
    >
        <span class="bg-wpx-blue/16 rounded-wpx-sm flex h-9 w-9 shrink-0 items-center justify-center">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <rect x="4" y="8" width="16" height="12" rx="2" stroke="#4FA3FF" stroke-width="1.7" />
                <path d="M8 8V6a4 4 0 018 0v2" stroke="#4FA3FF" stroke-width="1.7" />
            </svg>
        </span>
        <span class="flex-1">
            <span class="text-wpx-white-soft block text-sm font-bold">Studio Annonceur</span>
            <span class="text-wpx-muted-dark block text-xs">{{ advertiserSpace.organization_name }}</span>
        </span>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <path d="M9 6l6 6-6 6" stroke="#A9B7C8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </a>

    <section v-else class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-3 p-4">
        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <div>
                <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Devenir annonceur</p>
                <p class="text-wpx-muted-dark mt-1 text-xs">
                    Crée ton espace annonceur pour financer des campagnes et publier dans le Feed.
                </p>
            </div>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Nom de la marque ou de l'organisation</span>
                <input
                    v-model="name"
                    type="text"
                    maxlength="180"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 focus:ring-2 focus:outline-none"
                />
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-wpx-white-soft font-medium">Pays (code ISO2)</span>
                <input
                    v-model="countryCode"
                    type="text"
                    maxlength="2"
                    required
                    class="rounded-wpx-sm border-wpx-border-dark bg-wpx-navy-750 text-wpx-white-soft focus:ring-wpx-blue border px-3 py-2 uppercase focus:ring-2 focus:outline-none"
                />
            </label>

            <p v-if="error" class="text-wpx-danger-light text-xs">{{ error }}</p>

            <button
                type="submit"
                :disabled="submitting"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 ease-wpx-standard bg-gradient-to-br px-4 py-2 text-sm font-semibold transition duration-200 disabled:opacity-50"
            >
                Créer mon espace annonceur
            </button>
        </form>
    </section>
</template>
