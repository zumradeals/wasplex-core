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
        const createdSpace = (spacesData.spaces as AuthShared['spaces']).find(
            (s) => s.organization_id === data.id,
        );

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
    <section class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 flex flex-col gap-3 p-4">
        <template v-if="advertiserSpace">
            <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Espace annonceur</p>
            <p class="text-wpx-white-soft text-sm">
                {{ advertiserSpace.organization_name }}
            </p>
            <a
                href="/studio"
                class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-1 bg-gradient-to-br px-4 py-2 text-center text-sm font-semibold"
            >
                Aller au Studio Annonceur
            </a>
        </template>

        <form v-else class="flex flex-col gap-3" @submit.prevent="submit">
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
