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

const numberFormatter = new Intl.NumberFormat('fr-FR');

const currentPlan = computed(() =>
    subscription.value ? plans.value.find((plan) => plan.plan_version_id === subscription.value?.plan_version_id) ?? null : null,
);

const quotaPercent = computed(() => {
    if (!quota.value || quota.value.allocated === 0) {
        return 0;
    }

    return Math.min(100, Math.round((quota.value.consumed / quota.value.allocated) * 100));
});

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

        // /current provisions the FREE fallback when needed. Read quota
        // afterwards so this request always sees the initialized cycle.
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
    if (subscription.value?.plan_version_id === plan.plan_version_id) {
        return;
    }

    error.value = null;
    subscribing.value = plan.plan_version_id;
    try {
        const endpoint = subscription.value ? `/subscriptions/${subscription.value.id}/upgrade` : '/subscriptions';
        const { data } = await http.post(endpoint, { plan_version_id: plan.plan_version_id });

        if (data.payment?.checkout_url) {
            window.open(data.payment.checkout_url, '_blank', 'noopener,noreferrer');
        }

        await load();
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
    <div class="flex flex-col gap-4">
        <div v-if="loadError" class="rounded-wpx-lg bg-wpx-danger/10 text-wpx-danger-light p-4 text-sm">
            {{ loadError }}
        </div>

        <template v-else>
            <div class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 p-4">
                <p class="text-wpx-muted-dark text-xs font-semibold tracking-wide uppercase">Mon abonnement</p>
                <p v-if="loading" class="text-wpx-muted-dark mt-1 text-sm">Chargement…</p>
                <template v-else-if="subscription">
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <p class="text-wpx-white-soft text-lg font-semibold">{{ currentPlan?.name ?? 'Plan membre' }}</p>
                        <span class="bg-wpx-blue/15 text-wpx-cyan rounded-full px-2 py-0.5 text-[11px] font-semibold">
                            {{ STATUS_LABELS[subscription.status] ?? subscription.status }}
                        </span>
                    </div>
                    <p v-if="currentPlan" class="text-wpx-muted-dark mt-1 text-xs">
                        {{ currentPlan.price_minor === 0 ? 'Gratuit' : `${numberFormatter.format(currentPlan.price_minor)} ${currentPlan.currency}` }}
                        · {{ currentPlan.duration_days }} jours
                    </p>
                    <div v-if="quota" class="mt-3">
                        <div class="bg-wpx-navy-750 h-2 overflow-hidden rounded-full">
                            <div
                                class="from-wpx-blue to-wpx-gold h-full bg-gradient-to-r transition-all"
                                :style="{ width: `${quotaPercent}%` }"
                            />
                        </div>
                        <p class="text-wpx-muted-dark mt-1 text-xs">
                            {{ numberFormatter.format(quota.consumed) }} /
                            {{ numberFormatter.format(quota.allocated) }} publicités ce mois-ci —
                            {{ numberFormatter.format(quota.remaining) }} restantes
                        </p>
                    </div>
                </template>
                <p v-else class="text-wpx-muted-dark mt-1 text-sm">Aucun abonnement actif.</p>
            </div>

            <div class="flex flex-col gap-3">
                <div>
                    <h2 class="text-wpx-white-soft text-sm font-semibold">Plans disponibles</h2>
                    <p class="text-wpx-muted-dark mt-1 text-xs">Comparez votre plan actuel et choisissez une évolution adaptée à vos besoins.</p>
                </div>
                <p v-if="error" class="text-wpx-danger-light text-xs">{{ error }}</p>

                <div
                    v-for="plan in plans"
                    :key="plan.plan_version_id"
                    class="rounded-wpx-lg shadow-wpx-card-dark bg-wpx-navy-850 border-wpx-border-dark flex flex-col gap-2 border p-4"
                    :class="subscription?.plan_version_id === plan.plan_version_id ? 'ring-wpx-cyan/60 ring-1' : ''"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-wpx-white-soft text-base font-semibold">{{ plan.name }}</span>
                        <span class="text-wpx-gold text-sm font-semibold">
                            {{ plan.price_minor === 0 ? 'Gratuit' : `${numberFormatter.format(plan.price_minor)} ${plan.currency}` }}
                        </span>
                    </div>
                    <p class="text-wpx-muted-dark text-xs">
                        Jusqu'à {{ numberFormatter.format(plan.quota_monthly ?? 0) }} publicités / mois · {{ plan.duration_days }} jours
                    </p>
                    <p class="text-wpx-muted-dark text-xs">Accès Fonds : {{ plan.fonds_eligible ? 'oui' : 'non' }}</p>
                    <p class="text-wpx-muted-dark text-[11px] italic">
                        Maximum, pas une garantie — la disponibilité dépend des campagnes actives.
                    </p>
                    <button
                        type="button"
                        class="rounded-wpx-md from-wpx-blue to-wpx-cyan text-wpx-navy-950 mt-1 bg-gradient-to-br px-4 py-2 text-sm font-semibold disabled:opacity-50"
                        :disabled="subscribing === plan.plan_version_id || subscription?.plan_version_id === plan.plan_version_id"
                        @click="subscribeTo(plan)"
                    >
                        {{
                            subscription?.plan_version_id === plan.plan_version_id
                                ? 'Plan actuel'
                                : subscribing === plan.plan_version_id
                                  ? 'Traitement…'
                                  : subscription
                                    ? 'Choisir ce plan'
                                    : 'Souscrire'
                        }}
                    </button>
                </div>

                <p v-if="!loading && plans.length === 0" class="text-wpx-muted-dark text-sm">
                    Aucun plan publié pour le moment.
                </p>
            </div>
        </template>
    </div>
</template>
