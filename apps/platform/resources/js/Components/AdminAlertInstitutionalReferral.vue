<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import http from '@/lib/http';

const props = defineProps<{ alertId: string }>();
const emit = defineEmits<{ referred: [] }>();

type Destination = {
    id: string;
    organization_id: string;
    name: string;
    territory: Record<string, string> | null;
};

const destinations = ref<Destination[]>([]);
const destinationId = ref('');
const reason = ref('');
const reference = ref('');
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');

function territoryLabel(territory: Record<string, string> | null): string {
    if (!territory) return '';
    return [territory.area_label, territory.city, territory.country_code].filter(Boolean).join(' · ');
}

async function loadDestinations(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const { data } = await http.get('/admin/alerts/institutional-destinations');
        destinations.value = data.destinations ?? [];
        if (destinations.value.length === 1) destinationId.value = destinations.value[0].id;
    } catch {
        error.value = 'Impossible de charger les institutions de sécurité vérifiées.';
    } finally {
        loading.value = false;
    }
}

async function refer(): Promise<void> {
    if (!destinationId.value || reason.value.trim().length < 5) {
        error.value = 'Choisissez une institution et indiquez pourquoi ce dossier lui est transmis.';
        return;
    }

    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        await http.post(`/admin/alerts/${props.alertId}/refer`, {
            professional_space_id: destinationId.value,
            reason: reason.value.trim(),
            institutional_reference: reference.value.trim() || null,
        });
        notice.value =
            'Dossier transmis. Il apparaîtra comme “pris en charge” seulement après action réelle de l’institution.';
        reason.value = '';
        reference.value = '';
        emit('referred');
    } catch {
        error.value = 'Transmission impossible. Vérifiez l’institution, le dossier ou votre session MFA.';
    } finally {
        saving.value = false;
    }
}

watch(
    () => props.alertId,
    () => {
        notice.value = '';
        error.value = '';
        reason.value = '';
        reference.value = '';
    },
);

onMounted(loadDestinations);
</script>

<template>
    <section class="border-wpx-cyan/15 bg-wpx-cyan/5 mt-4 rounded-2xl border p-4">
        <p class="text-wpx-white-soft text-xs font-black">Transmettre à une institution vérifiée</p>
        <p class="text-wpx-muted-dark mt-1 text-[9px] leading-relaxed">
            La transmission ne signifie pas “prise en charge”. Ce statut apparaîtra uniquement lorsqu’un agent de
            l’institution aura accusé réception ou démarré l’intervention.
        </p>

        <p v-if="notice" class="mt-3 rounded-xl bg-emerald-500/10 px-3 py-2 text-[9px] font-bold text-emerald-300">
            {{ notice }}
        </p>
        <p v-if="error" class="mt-3 rounded-xl bg-red-500/10 px-3 py-2 text-[9px] font-bold text-red-300">
            {{ error }}
        </p>

        <p v-if="loading" class="text-wpx-muted-dark mt-3 text-[10px]">Chargement des institutions…</p>

        <form v-else-if="destinations.length" class="mt-3 space-y-2" @submit.prevent="refer">
            <select
                v-model="destinationId"
                required
                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-2.5 text-xs"
            >
                <option value="" disabled>Choisir une institution de sécurité</option>
                <option v-for="destination in destinations" :key="destination.id" :value="destination.id">
                    {{ destination.name
                    }}{{ territoryLabel(destination.territory) ? ` · ${territoryLabel(destination.territory)}` : '' }}
                </option>
            </select>
            <textarea
                v-model="reason"
                required
                minlength="5"
                rows="3"
                placeholder="Pourquoi ce dossier est-il transmis à cette institution ?"
                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-2.5 text-xs"
            />
            <input
                v-model="reference"
                maxlength="120"
                placeholder="Référence institutionnelle (facultatif)"
                class="bg-wpx-navy-950 border-wpx-border-dark text-wpx-white-soft w-full rounded-xl border px-3 py-2.5 text-xs"
            />
            <button
                type="submit"
                :disabled="saving"
                class="from-wpx-orange to-wpx-gold text-wpx-navy-950 w-full rounded-xl bg-gradient-to-r px-3 py-2.5 text-xs font-black disabled:opacity-50"
            >
                {{ saving ? 'Transmission…' : 'Transmettre le dossier' }}
            </button>
        </form>

        <p v-else class="text-wpx-muted-dark mt-3 rounded-xl bg-black/10 px-3 py-3 text-[10px]">
            Aucune institution de sécurité vérifiée et active n’est disponible pour le moment.
        </p>
    </section>
</template>
