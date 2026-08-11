<script setup lang="ts">
import { computed, ref } from 'vue';
import http from '@/lib/http';

interface Plan {
    plan_version_id: string;
    code: string;
    name: string;
    price_minor: number;
    currency: string;
    duration_days: number;
    economic_class: string | null;
    quota_monthly: number | null;
    fonds_eligible: boolean;
}

interface Subscription {
    id: string;
    status: string;
    plan_version_id: string;
    economic_class_id: string;
    current_period_end: string | null;
}

interface Quota {
    allocated: number;
    consumed: number;
    remaining: number;
}

const STATUS_LABELS: Record<string, string> = {
    pending_payment: 'Paiement en attente',
    active: 'Actif',
    scheduled_downgrade: 'Changement programmé',
    expired: 'Expiré',
    cancelled: 'Annulé',
};

const plans = ref<Plan[]>([]);
const subscription = ref<Subscription | null>(null);
const quota = ref<Quota | null>(null);
const loading = ref(true);
const loadError = ref<string | null>(null);
const subscribing = ref<string | null>(null);
const error = ref<string | null>(null);
const showPlans = ref(false);

const numberFormatter = new Intl.NumberFormat('fr-FR');

const currentPlan = computed(() =>
    subscription.value
        ? (plans.value.find((plan) => plan.plan_version_id === subscription.value?.plan_version_id) ?? null)
        : null,
);

const alternativePlans = computed(() =>
    plans.value
        .filter((plan) => plan.plan_version_id !== subscription.value?.plan_version_id)
        .sort((a, b) => a.price_minor - b.price_minor),
);

const quotaPercent = computed(() => {
    if (!quota.value || quota.value.allocated === 0) return 0;
    return Math.min(100, Math.round((quota.value.consumed / quota.value.allocated) * 100));
});

function planPrice(plan: Plan): string {
    return plan.price_minor === 0 ? 'Gratuit' : `${numberFormatter.format(plan.price_minor)} ${plan.currency}`;
}

async function load(): Promise<void> {
    loading.value = true;
    loadError.value = null;
    try {
        const [plansRes, currentRes] = await Promise.all([
            http.get('/subscriptions/plans'),
            http.get('/subscriptions/current'),
        ]);
        plans.value = plansRes.data.plans;
        subscription.value = currentRes.data.subscription;

        const quotaRes = await http.get('/subscriptions/quota');
        quota.value = quotaRes.data.quota;
    } catch (e) {
        const message = (e as { response?: { data?: { message?: string } } }).response?.data?.message;
        loadError.value = message ?? "L'abonnement est momentanément indisponible.";
    } finally {
        loading.value = false;
    }
}

async function subscribeTo(plan: Plan): Promise<void> {
    if (subscription.value?.plan_version_id === plan.plan_version_id) return;

    error.value = null;
    subscribing.value = plan.plan_version_id;
    try {
        const endpoint = subscription.value ? `/subscriptions/${subscription.value.id}/upgrade` : '/subscriptions';
        const { data } = await http.post(endpoint, { plan_version_id: plan.plan_version_id });

        if (data.payment?.checkout_url) {
            window.open(data.payment.checkout_url, '_blank', 'noopener,noreferrer');
        }

        await load();
        if (!data.payment?.checkout_url) showPlans.value = false;
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data?.message ??
            "Le changement d'abonnement a échoué.";
    } finally {
        subscribing.value = null;
    }
}

void load();
</script>

<template>
    <div>
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <section
            v-else
            class="rounded-wpx-xl border-wpx-border-dark bg-wpx-navy-850 shadow-wpx-card-dark overflow-hidden border"
        >
            <div class="from-wpx-navy-750 to-wpx-navy-850 bg-gradient-to-br p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-wpx-muted-dark text-[11px] font-bold tracking-wide uppercase">Mon abonnement</p>
                        <div v-if="loading" class="text-wpx-muted-dark mt-2 text-sm">Chargement…</div>
                        <template v-else-if="subscription">
                            <div class="mt-1 flex items-center gap-2">
                                <h3 class="text-wpx-white-soft text-lg font-bold">
                                    {{ currentPlan?.name ?? 'Plan membre' }}
                                </h3>
                                <span
                                    class="bg-wpx-success/12 text-wpx-success-light rounded-full px-2 py-0.5 text-[10px] font-bold"
                                >
                                    {{ STATUS_LABELS[subscription.status] ?? subscription.status }}
                                </span>
                            </div>
                            <p v-if="currentPlan" class="text-wpx-muted-dark mt-1 text-xs">
                                {{ planPrice(currentPlan) }} · {{ currentPlan.duration_days }} jours
                            </p>
                        </template>
                    </div>
                    <span class="bg-wpx-gold/15 text-wpx-gold rounded-wpx-md px-2.5 py-1.5 text-xs font-bold">
                        {{ quota ? `${numberFormatter.format(quota.remaining)} restantes` : '—' }}
                    </span>
                </div>

                <div v-if="quota && !loading" class="mt-3">
                    <div class="bg-wpx-navy-950/60 h-1.5 overflow-hidden rounded-full">
                        <div
                            class="from-wpx-blue to-wpx-gold h-full rounded-full bg-gradient-to-r transition-all"
                            :style="{ width: `${quotaPercent}%` }"
                        />
                    </div>
                    <p class="text-wpx-muted-dark mt-1.5 text-[11px]">
                        {{ numberFormatter.format(quota.consumed) }} /
                        {{ numberFormatter.format(quota.allocated) }} publicités utilisées ce cycle
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 p-4 pt-3">
                <div>
                    <p class="text-wpx-white-soft text-sm font-semibold">Envie de plus d’avantages ?</p>
                    <p class="text-wpx-muted-dark mt-0.5 text-[11px]">
                        Comparez les offres publiées sans quitter Mon Espace.
                    </p>
                </div>
                <button
                    type="button"
                    class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md shrink-0 bg-gradient-to-br px-3.5 py-2 text-xs font-bold"
                    @click="showPlans = true"
                >
                    Voir les offres
                </button>
            </div>
        </section>

        <Teleport to="body">
            <div
                v-if="showPlans"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 sm:items-center"
                @click.self="showPlans = false"
            >
                <div
                    class="bg-wpx-navy-950 border-wpx-border-dark max-h-[88vh] w-full max-w-md overflow-y-auto rounded-t-3xl border p-4 shadow-2xl sm:rounded-3xl"
                >
                    <div class="bg-wpx-navy-950 sticky top-0 z-10 flex items-start justify-between gap-3 pb-3">
                        <div>
                            <p class="text-wpx-white-soft text-lg font-bold">Choisir mon abonnement</p>
                            <p class="text-wpx-muted-dark mt-1 text-xs">
                                Seules les offres validées et publiées par Wasplex sont proposées ici.
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Fermer"
                            class="bg-wpx-navy-750 text-wpx-white-soft flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                            @click="showPlans = false"
                        >
                            ×
                        </button>
                    </div>

                    <div v-if="currentPlan" class="border-wpx-cyan/40 bg-wpx-cyan/5 rounded-wpx-lg mb-3 border p-3">
                        <p class="text-wpx-cyan text-[10px] font-bold tracking-wide uppercase">Votre plan actuel</p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="text-wpx-white-soft font-bold">{{ currentPlan.name }}</p>
                            <p class="text-wpx-gold text-sm font-bold">{{ planPrice(currentPlan) }}</p>
                        </div>
                    </div>

                    <p v-if="error" class="bg-wpx-danger/10 text-wpx-danger-light rounded-wpx-md mb-3 p-3 text-xs">
                        {{ error }}
                    </p>

                    <div v-if="alternativePlans.length > 0" class="flex flex-col gap-3">
                        <article
                            v-for="plan in alternativePlans"
                            :key="plan.plan_version_id"
                            class="border-wpx-border-dark bg-wpx-navy-850 rounded-wpx-xl border p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h4 class="text-wpx-white-soft text-base font-bold">{{ plan.name }}</h4>
                                    <p class="text-wpx-muted-dark mt-1 text-xs">{{ plan.duration_days }} jours</p>
                                </div>
                                <p class="text-wpx-gold text-sm font-bold">{{ planPrice(plan) }}</p>
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="bg-wpx-navy-750 rounded-wpx-md p-2.5">
                                    <p class="text-wpx-muted-dark text-[10px] uppercase">Publicités / mois</p>
                                    <p class="text-wpx-white-soft mt-0.5 text-sm font-bold">
                                        {{ numberFormatter.format(plan.quota_monthly ?? 0) }}
                                    </p>
                                </div>
                                <div class="bg-wpx-navy-750 rounded-wpx-md p-2.5">
                                    <p class="text-wpx-muted-dark text-[10px] uppercase">Fonds Wasplex</p>
                                    <p class="text-wpx-white-soft mt-0.5 text-sm font-bold">
                                        {{ plan.fonds_eligible ? 'Inclus' : 'Non inclus' }}
                                    </p>
                                </div>
                            </div>
                            <p class="text-wpx-muted-dark mt-2 text-[10px] italic">
                                La quantité de publicités dépend toujours des campagnes compatibles disponibles.
                            </p>
                            <button
                                type="button"
                                class="from-wpx-blue to-wpx-cyan text-wpx-navy-950 rounded-wpx-md mt-3 w-full bg-gradient-to-br px-4 py-2.5 text-sm font-bold disabled:opacity-50"
                                :disabled="subscribing !== null"
                                @click="subscribeTo(plan)"
                            >
                                {{
                                    subscribing === plan.plan_version_id
                                        ? 'Traitement…'
                                        : plan.price_minor === 0
                                          ? 'Choisir ce plan'
                                          : 'Passer à ce plan'
                                }}
                            </button>
                        </article>
                    </div>

                    <div v-else class="bg-wpx-navy-850 border-wpx-border-dark rounded-wpx-xl border p-5 text-center">
                        <p class="text-wpx-white-soft text-sm font-bold">Aucune autre offre publiée pour le moment</p>
                        <p class="text-wpx-muted-dark mt-2 text-xs leading-relaxed">
                            Les offres Premium, Gold ou Platine apparaîtront automatiquement ici dès qu’une version
                            tarifée sera publiée.
                        </p>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
