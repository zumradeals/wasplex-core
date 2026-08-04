<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import StudioLayout from '@/layouts/StudioLayout.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type Brand = {
    id: string;
    name: string;
    slogan: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
};

type Asset = {
    id: string;
    kind: 'image' | 'video';
    name: string;
    mimeType: string;
    url: string | null;
};

type Campaign = {
    id: string;
    name: string;
    status: 'changes_requested';
    brand: Brand;
    version: {
        objective: string;
        headline: string | null;
        body: string | null;
        callToAction: string | null;
        destinationUrl: string | null;
        startsOn: string | null;
        endsOn: string | null;
        territoryName: string | null;
        radiusKm: number | null;
        selectedClasses: string[];
        budgetMinor: number;
        creatives: Asset[];
    };
    funding: {
        amountMinor: number;
        currency: string;
        reservationStatus: string | null;
    } | null;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    campaign: Campaign;
    brands: Brand[];
    assets: Asset[];
    reviewReason: string | null;
    flash: { success: string | null };
}>();

const version = props.campaign.version;
const form = useForm({
    name: props.campaign.name,
    brand_id: props.campaign.brand.id,
    objective: version.objective,
    headline: version.headline ?? '',
    body: version.body ?? '',
    call_to_action: version.callToAction ?? '',
    destination_url: version.destinationUrl ?? '',
    starts_on: version.startsOn ?? '',
    ends_on: version.endsOn ?? '',
    territory_name: version.territoryName ?? '',
    radius_km: version.radiusKm ?? 10,
    selected_classes: version.selectedClasses,
    budget_minor: props.campaign.funding?.amountMinor ?? version.budgetMinor,
    creative_asset_ids: version.creatives.map((asset) => asset.id),
    current_step: 7,
});

const submit = () => {
    form.patch(`/studio/campagnes/${props.campaign.id}/correction`, {
        preserveScroll: true,
    });
};

const money = (value: number, currency = 'XOF') =>
    new Intl.NumberFormat('fr-FR', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);
</script>

<template>
    <Head :title="`Correction — ${campaign.name}`" />
    <StudioLayout
        :account="account"
        :active-space="activeSpace"
        :spaces="spaces"
        active="campaigns"
    >
        <header>
            <Link href="/studio/campagnes" class="text-xs font-black text-cyan-300/70">
                ← Toutes les campagnes
            </Link>
            <p class="mt-5 text-xs font-black tracking-[0.22em] text-orange-300 uppercase">
                Correction administrative
            </p>
            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                {{ campaign.name }}
            </h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-white/40">
                Modifiez uniquement les éléments demandés. Le budget reste réservé et ne sera pas
                débité une seconde fois.
            </p>
        </header>

        <div
            class="mt-6 rounded-2xl border border-orange-300/20 bg-orange-300/8 px-5 py-4"
        >
            <p class="text-xs font-black text-orange-200">Motif de la revue</p>
            <p class="mt-2 text-sm leading-6 text-white/60">
                {{ reviewReason || 'Des corrections ont été demandées par l’administration.' }}
            </p>
        </div>

        <div
            v-if="flash.success"
            class="mt-4 rounded-2xl border border-emerald-400/20 bg-emerald-400/8 px-5 py-4 text-sm font-bold text-emerald-200"
        >
            {{ flash.success }}
        </div>

        <div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="space-y-6">
                <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                    <h2 class="text-xl font-black">Identité et message</h2>
                    <div class="mt-6 grid gap-5">
                        <label>
                            <span class="text-xs font-black text-white/55">Nom interne</span>
                            <input
                                v-model="form.name"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none focus:border-cyan-300/50"
                            />
                            <span v-if="form.errors.name" class="mt-2 block text-xs text-rose-300">
                                {{ form.errors.name }}
                            </span>
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Marque</span>
                            <select
                                v-model="form.brand_id"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-[#081522] px-4 py-3.5 text-sm outline-none"
                            >
                                <option v-for="brand in brands" :key="brand.id" :value="brand.id">
                                    {{ brand.name }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Titre publicitaire</span>
                            <input
                                v-model="form.headline"
                                maxlength="180"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none"
                            />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Message</span>
                            <textarea
                                v-model="form.body"
                                rows="6"
                                maxlength="2000"
                                class="mt-2 w-full resize-none rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm leading-6 outline-none"
                            />
                        </label>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <label>
                                <span class="text-xs font-black text-white/55">Bouton d’action</span>
                                <input
                                    v-model="form.call_to_action"
                                    maxlength="80"
                                    class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none"
                                />
                            </label>
                            <label>
                                <span class="text-xs font-black text-white/55">Lien de destination</span>
                                <input
                                    v-model="form.destination_url"
                                    type="url"
                                    class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm outline-none"
                                />
                                <span
                                    v-if="form.errors.destination_url"
                                    class="mt-2 block text-xs text-rose-300"
                                >
                                    {{ form.errors.destination_url }}
                                </span>
                            </label>
                        </div>
                    </div>
                </article>

                <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                    <h2 class="text-xl font-black">Médias</h2>
                    <p class="mt-2 text-sm text-white/38">
                        Sélectionnez les contenus corrigés depuis votre médiathèque.
                    </p>
                    <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label
                            v-for="asset in assets"
                            :key="asset.id"
                            class="cursor-pointer overflow-hidden rounded-2xl border transition"
                            :class="
                                form.creative_asset_ids.includes(asset.id)
                                    ? 'border-cyan-300/60 bg-cyan-300/8'
                                    : 'border-white/8 bg-black/10'
                            "
                        >
                            <input
                                v-model="form.creative_asset_ids"
                                type="checkbox"
                                :value="asset.id"
                                class="sr-only"
                            />
                            <div class="grid h-32 place-items-center overflow-hidden bg-black/25">
                                <img
                                    v-if="asset.kind === 'image' && asset.url"
                                    :src="asset.url"
                                    :alt="asset.name"
                                    class="size-full object-cover"
                                />
                                <video
                                    v-else-if="asset.kind === 'video' && asset.url"
                                    :src="asset.url"
                                    muted
                                    class="size-full object-cover"
                                />
                                <span v-else class="text-2xl">{{ asset.kind === 'video' ? '▶' : '▧' }}</span>
                            </div>
                            <p class="truncate p-3 text-xs font-black">{{ asset.name }}</p>
                        </label>
                    </div>
                    <span
                        v-if="form.errors.creative_asset_ids"
                        class="mt-3 block text-xs text-rose-300"
                    >
                        {{ form.errors.creative_asset_ids }}
                    </span>
                </article>

                <article class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-5 sm:p-7">
                    <h2 class="text-xl font-black">Audience, territoire et calendrier</h2>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <label>
                            <span class="text-xs font-black text-white/55">Objectif</span>
                            <select
                                v-model="form.objective"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-[#081522] px-4 py-3.5 text-sm"
                            >
                                <option value="notoriety">Notoriété</option>
                                <option value="traffic">Trafic</option>
                                <option value="conversion">Conversion</option>
                                <option value="engagement">Engagement</option>
                            </select>
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Territoire</span>
                            <input
                                v-model="form.territory_name"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm"
                            />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Rayon en km</span>
                            <input
                                v-model.number="form.radius_km"
                                type="number"
                                min="1"
                                max="100"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm"
                            />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Début</span>
                            <input
                                v-model="form.starts_on"
                                type="date"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm"
                            />
                        </label>
                        <label>
                            <span class="text-xs font-black text-white/55">Fin</span>
                            <input
                                v-model="form.ends_on"
                                type="date"
                                class="mt-2 w-full rounded-2xl border border-white/10 bg-black/20 px-4 py-3.5 text-sm"
                            />
                        </label>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <label
                            v-for="economicClass in ['FREE', 'PREMIUM', 'GOLD', 'PLATINUM']"
                            :key="economicClass"
                            class="cursor-pointer rounded-2xl border p-4 text-center text-xs font-black"
                            :class="
                                form.selected_classes.includes(economicClass)
                                    ? 'border-cyan-300/60 bg-cyan-300/8 text-cyan-100'
                                    : 'border-white/8 bg-black/10 text-white/40'
                            "
                        >
                            <input
                                v-model="form.selected_classes"
                                type="checkbox"
                                :value="economicClass"
                                class="sr-only"
                            />
                            {{ economicClass }}
                        </label>
                    </div>
                </article>
            </section>

            <aside class="space-y-5">
                <div class="rounded-[2rem] border border-cyan-300/15 bg-cyan-300/[0.045] p-6">
                    <p class="text-xs font-black text-cyan-200 uppercase">Budget verrouillé</p>
                    <p class="mt-3 text-3xl font-black">
                        {{ money(form.budget_minor, campaign.funding?.currency) }}
                    </p>
                    <p class="mt-3 text-xs leading-5 text-white/38">
                        Le montant financé ne peut pas être modifié pendant cette correction.
                    </p>
                    <input v-model.number="form.budget_minor" type="hidden" />
                </div>

                <div class="rounded-[2rem] border border-white/8 bg-white/[0.035] p-6">
                    <p class="text-xs font-black text-white/35 uppercase">Avant resoumission</p>
                    <ul class="mt-4 space-y-3 text-sm leading-6 text-white/48">
                        <li>✓ Vérifiez le motif administratif.</li>
                        <li>✓ Corrigez le média, le texte ou le lien concerné.</li>
                        <li>✓ Le budget reste réservé.</li>
                        <li>✓ Une nouvelle version immuable sera créée.</li>
                    </ul>
                </div>

                <button
                    class="w-full rounded-2xl bg-emerald-300 px-5 py-4 text-sm font-black text-[#04111e] disabled:opacity-50"
                    :disabled="form.processing"
                    @click="submit"
                >
                    {{ form.processing ? 'Resoumission…' : 'Enregistrer et resoumettre' }}
                </button>
            </aside>
        </div>
    </StudioLayout>
</template>
