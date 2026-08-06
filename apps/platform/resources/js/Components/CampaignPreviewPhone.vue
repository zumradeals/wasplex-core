<script setup lang="ts">
defineProps<{
    brandName?: string | null;
    title?: string | null;
    ctaLabel?: string | null;
    assetUrl?: string | null;
    assetType?: string | null;
    gainLabel?: string | null;
}>();
</script>

<template>
    <!--
        docs/13-studio-annonceur-wasplex.md §22 : simulation Feed verticale
        fidèle (smartphone, CTA, gain, barre de progression, son,
        sous-titres). Mise à jour réactive locale uniquement — pas de canal
        Reverb, aucun autre utilisateur n'observe cet aperçu.
    -->
    <div class="mx-auto w-full max-w-[220px]">
        <div
            class="border-wpx-navy-950 bg-wpx-navy-950 aspect-[9/19.5] w-full rounded-[2rem] border-[6px] p-1 shadow-xl"
        >
            <div class="bg-wpx-navy-850 relative h-full w-full overflow-hidden rounded-[1.5rem]">
                <div class="absolute inset-0">
                    <video
                        v-if="assetType === 'video' && assetUrl"
                        :src="assetUrl"
                        class="h-full w-full object-cover"
                        muted
                        loop
                        autoplay
                        playsinline
                    />
                    <img v-else-if="assetUrl" :src="assetUrl" alt="" class="h-full w-full object-cover" />
                    <div v-else class="text-wpx-muted-dark flex h-full w-full items-center justify-center text-xs">
                        Aperçu du média
                    </div>
                </div>

                <div class="absolute inset-x-0 top-0 flex items-center gap-1 p-2">
                    <div class="bg-wpx-gold/80 h-1 flex-1 rounded-full" />
                    <span class="text-[9px] text-white/80">🔊</span>
                    <span class="text-[9px] text-white/80">CC</span>
                </div>

                <div
                    class="absolute inset-x-0 bottom-0 flex flex-col gap-2 bg-gradient-to-t from-black/80 to-transparent p-3"
                >
                    <span class="text-[10px] font-semibold text-white">{{ brandName ?? 'Votre marque' }}</span>
                    <p class="line-clamp-2 text-xs text-white">{{ title ?? 'Votre message apparaîtra ici.' }}</p>
                    <span
                        v-if="gainLabel"
                        class="bg-wpx-gold text-wpx-navy-950 self-start rounded-full px-2 py-0.5 text-[10px] font-bold"
                    >
                        {{ gainLabel }}
                    </span>
                    <button
                        type="button"
                        class="rounded-wpx-sm from-wpx-blue to-wpx-cyan text-wpx-navy-950 w-full bg-gradient-to-br py-1.5 text-xs font-semibold"
                    >
                        {{ ctaLabel ?? 'En savoir plus' }}
                    </button>
                </div>
            </div>
        </div>
        <p class="text-wpx-text-muted mt-2 text-center text-[11px]">Aperçu — pas la publicité réelle</p>
    </div>
</template>
