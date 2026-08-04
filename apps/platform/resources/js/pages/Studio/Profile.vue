<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    profile: {
        displayName: string;
        legalName: string | null;
        industry: string | null;
        websiteUrl: string | null;
        countryCode: string;
        city: string | null;
        phone: string | null;
        description: string | null;
        completion: number;
    };
    flash: { success: string | null };
}>();

const form = useForm({
    display_name: props.profile.displayName,
    legal_name: props.profile.legalName ?? '',
    industry: props.profile.industry ?? '',
    website_url: props.profile.websiteUrl ?? '',
    country_code: props.profile.countryCode || 'CI',
    city: props.profile.city ?? '',
    phone: props.profile.phone ?? '',
    description: props.profile.description ?? '',
});

const submit = () => form.patch('/studio/profil', { preserveScroll: true });
</script>

<template>
    <Head title="Profil annonceur" />
    <StudioLayout :account="account" :active-space="activeSpace" :spaces="spaces" active="profile">
        <header class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black tracking-[0.24em] text-orange-400 uppercase">
                    Organisation
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    Profil annonceur
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                    Ces informations représentent votre organisation dans Wasplex. Elles restent
                    séparées de votre profil personnel.
                </p>
            </div>
            <div class="min-w-40 rounded-2xl border border-cyan-400/15 bg-cyan-400/[0.055] p-4">
                <div class="flex items-center justify-between text-xs font-black text-cyan-200">
                    <span>Complété</span><span>{{ profile.completion }} %</span>
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-black/25">
                    <div
                        class="h-full rounded-full bg-cyan-300"
                        :style="{ width: `${profile.completion}%` }"
                    />
                </div>
            </div>
        </header>

        <div
            v-if="flash.success"
            class="mt-6 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <form class="mt-8 grid gap-6 xl:grid-cols-[1fr_0.42fr]" @submit.prevent="submit">
            <section class="rounded-[2rem] border border-white/8 bg-white/[0.04] p-6 sm:p-8">
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55">Nom public du Studio</span>
                        <input
                            v-model="form.display_name"
                            required
                            maxlength="160"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                        <span
                            v-if="form.errors.display_name"
                            class="mt-2 block text-xs text-red-300"
                            >{{ form.errors.display_name }}</span
                        >
                    </label>

                    <label>
                        <span class="text-xs font-black text-white/55">Raison sociale</span>
                        <input
                            v-model="form.legal_name"
                            maxlength="200"
                            placeholder="Ex. GAMAD SARL"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Secteur d’activité</span>
                        <input
                            v-model="form.industry"
                            maxlength="120"
                            placeholder="Commerce, technologie, agriculture…"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Pays</span>
                        <select
                            v-model="form.country_code"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-[#071423] px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                        >
                            <option value="CI">Côte d’Ivoire</option>
                            <option value="ML">Mali</option>
                            <option value="BF">Burkina Faso</option>
                            <option value="SN">Sénégal</option>
                            <option value="GH">Ghana</option>
                            <option value="GN">Guinée</option>
                            <option value="TG">Togo</option>
                            <option value="BJ">Bénin</option>
                        </select>
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Ville</span>
                        <input
                            v-model="form.city"
                            maxlength="120"
                            placeholder="Abidjan"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55"
                            >Téléphone professionnel</span
                        >
                        <input
                            v-model="form.phone"
                            maxlength="40"
                            placeholder="+225…"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                    </label>
                    <label>
                        <span class="text-xs font-black text-white/55">Site web</span>
                        <input
                            v-model="form.website_url"
                            type="url"
                            maxlength="500"
                            placeholder="https://…"
                            class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm transition outline-none focus:border-cyan-300/50"
                        />
                        <span
                            v-if="form.errors.website_url"
                            class="mt-2 block text-xs text-red-300"
                            >{{ form.errors.website_url }}</span
                        >
                    </label>
                    <label class="md:col-span-2">
                        <span class="text-xs font-black text-white/55">Présentation</span>
                        <textarea
                            v-model="form.description"
                            rows="5"
                            maxlength="2000"
                            placeholder="Présentez brièvement votre activité, vos clients et votre positionnement."
                            class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm leading-6 transition outline-none focus:border-cyan-300/50"
                        />
                    </label>
                </div>

                <div
                    class="mt-7 flex flex-col-reverse gap-3 border-t border-white/7 pt-6 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-xs leading-5 text-white/30">
                        Le nom public met aussi à jour le nom affiché dans le sélecteur d’espace.
                    </p>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-2xl bg-cyan-300 px-5 py-3 text-sm font-black text-[#04111e] disabled:opacity-50"
                    >
                        {{ form.processing ? 'Enregistrement…' : 'Enregistrer le profil' }}
                    </button>
                </div>
            </section>

            <aside class="space-y-5">
                <article class="rounded-[2rem] border border-orange-400/15 bg-[#130f0c] p-6">
                    <p class="text-[10px] font-black tracking-[0.18em] text-orange-400 uppercase">
                        Identité distincte
                    </p>
                    <h2 class="mt-4 text-xl font-black">Votre profil personnel reste privé.</h2>
                    <p class="mt-3 text-sm leading-6 text-white/38">
                        Le Studio utilise uniquement les informations professionnelles renseignées
                        ici pour les marques et futures campagnes.
                    </p>
                </article>
                <article class="rounded-[2rem] border border-white/8 bg-white/[0.04] p-6">
                    <p class="text-sm font-black">À compléter en priorité</p>
                    <div class="mt-4 space-y-3 text-xs text-white/40">
                        <p>✓ Nom public clair</p>
                        <p>✓ Secteur d’activité</p>
                        <p>✓ Ville et téléphone</p>
                        <p>✓ Courte présentation</p>
                    </div>
                </article>
            </aside>
        </form>
    </StudioLayout>
</template>
