<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import SpaceSwitcher from '@/components/SpaceSwitcher.vue';
import WasplexMark from '@/components/WasplexMark.vue';

type Space = {
    id: string;
    kind: 'user' | 'advertiser' | 'administration';
    label: string;
    active: boolean;
};

type QuestionOption = {
    value: string;
    label: string;
};

type Question = {
    code: string;
    category: string;
    taxonomyCode: string;
    taxonomyLabel: string;
    prompt: string;
    helpText: string;
    privacyNote: string;
    optional: boolean;
    options: QuestionOption[];
    purposeCodes: string[];
    answer: string | null;
    answerVersion: number | null;
    answeredAt: string | null;
    expiresAt: string | null;
    status: string | null;
};

type Consent = {
    code: string;
    title: string;
    description: string;
    refusalConsequence: string | null;
    required: boolean;
    version: number;
    decision: 'granted' | 'denied' | 'withdrawn' | null;
    decidedAt: string | null;
    active: boolean;
};

const props = defineProps<{
    account: { id: string; displayName: string };
    activeSpace: { id: string; kind: string; label: string };
    spaces: Space[];
    profile: {
        completion: number;
        answered: number;
        total: number;
        questions: Question[];
    };
    consents: Consent[];
    flash: { success: string | null };
    errors: Record<string, string[]>;
}>();

const saveAnswer = (question: Question, answer: string) => {
    router.post(
        '/mon-espace/profil-intelligent/reponses',
        {
            question_code: question.code,
            answer,
        },
        { preserveScroll: true },
    );
};

const removeAnswer = (question: Question) => {
    if (!window.confirm('Retirer cette information facultative de votre profil intelligent ?')) {
        return;
    }

    router.delete(`/mon-espace/profil-intelligent/reponses/${question.code}`, {
        preserveScroll: true,
    });
};

const decideConsent = (
    consent: Consent,
    status: 'granted' | 'denied' | 'withdrawn',
) => {
    const confirmation =
        status === 'withdrawn'
            ? 'Retirer ce consentement pour tous les nouveaux usages ?'
            : status === 'denied'
              ? 'Confirmer votre refus pour cette finalité ?'
              : 'Autoriser cette finalité ?';

    if (!window.confirm(confirmation)) {
        return;
    }

    router.post(
        '/mon-espace/profil-intelligent/consentements',
        {
            purpose_code: consent.code,
            status,
        },
        { preserveScroll: true },
    );
};

const date = (value: string | null) =>
    value
        ? new Intl.DateTimeFormat('fr-FR', {
              dateStyle: 'medium',
          }).format(new Date(value))
        : null;

const categoryLabel = (category: string) =>
    ({
        usage: 'Mes usages',
        interest: 'Mes intérêts',
        territory: 'Mes zones utiles',
        project: 'Mes projets',
    })[category] ?? 'Mon profil';
</script>

<template>
    <Head title="Profil intelligent" />
    <main class="min-h-screen bg-[#03101e] text-white">
        <div
            class="mx-auto min-h-screen max-w-md border-x border-white/5 bg-wasplex-night shadow-2xl"
        >
            <header
                class="sticky top-0 z-20 border-b border-white/10 bg-wasplex-night/90 px-5 py-4 backdrop-blur-xl"
            >
                <div class="flex items-center justify-between">
                    <WasplexMark />
                    <Link href="/mon-espace" class="text-xs font-black text-wasplex-cyan">
                        Mon Espace
                    </Link>
                </div>
                <div class="mt-4"><SpaceSwitcher :spaces="spaces" /></div>
            </header>

            <section class="px-5 py-7">
                <p class="text-xs font-black tracking-[0.2em] text-wasplex-cyan uppercase">
                    Vos choix, votre contrôle
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight">Profil intelligent</h1>
                <p class="mt-3 text-sm leading-6 text-white/48">
                    Répondez seulement aux questions qui vous conviennent. Vos réponses servent à
                    sélectionner des publicités compatibles sans transmettre votre identité aux
                    annonceurs.
                </p>

                <div
                    v-if="flash.success"
                    class="mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm font-bold text-emerald-200"
                >
                    {{ flash.success }}
                </div>

                <div
                    v-if="errors.answer?.length || errors.status?.length || errors.purpose?.length"
                    class="mt-5 rounded-2xl border border-rose-300/20 bg-rose-300/8 px-4 py-3 text-sm font-bold text-rose-200"
                >
                    {{ errors.answer?.[0] || errors.status?.[0] || errors.purpose?.[0] }}
                </div>

                <article
                    class="mt-7 overflow-hidden rounded-[2rem] border border-wasplex-cyan/15 bg-gradient-to-br from-wasplex-blue/45 to-white/[0.035] p-6"
                >
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-xs font-black tracking-widest text-white/45 uppercase">
                                Complétude facultative
                            </p>
                            <p class="mt-2 text-4xl font-black">{{ profile.completion }} %</p>
                        </div>
                        <p class="text-right text-xs leading-5 text-white/38">
                            {{ profile.answered }} réponse(s)<br />sur {{ profile.total }}
                        </p>
                    </div>
                    <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/8">
                        <div
                            class="h-full rounded-full bg-wasplex-cyan transition-all"
                            :style="{ width: `${profile.completion}%` }"
                        />
                    </div>
                    <p class="mt-4 text-xs leading-5 text-white/36">
                        Ce pourcentage n’est ni un score social ni une promesse de gain. Il indique
                        seulement les catégories volontairement renseignées.
                    </p>
                </article>

                <section class="mt-8">
                    <div class="flex items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                                Questions intelligentes
                            </p>
                            <h2 class="mt-2 text-xl font-black">Ce que vous choisissez de déclarer</h2>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <article
                            v-for="question in profile.questions"
                            :key="question.code"
                            class="rounded-[1.75rem] border border-white/8 bg-white/[0.035] p-5"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-[0.65rem] font-black tracking-[0.16em] text-wasplex-gold uppercase"
                                    >
                                        {{ categoryLabel(question.category) }} · Facultatif
                                    </p>
                                    <h3 class="mt-2 text-lg font-black leading-6">
                                        {{ question.prompt }}
                                    </h3>
                                </div>
                                <span
                                    v-if="question.answer"
                                    class="shrink-0 rounded-full bg-emerald-300/10 px-3 py-1 text-[0.65rem] font-black text-emerald-200"
                                >
                                    Renseigné
                                </span>
                            </div>

                            <p class="mt-3 text-sm leading-6 text-white/45">
                                {{ question.helpText }}
                            </p>
                            <p class="mt-2 text-xs leading-5 text-white/28">
                                {{ question.privacyNote }}
                            </p>

                            <div class="mt-5 grid grid-cols-2 gap-2">
                                <button
                                    v-for="option in question.options"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-2xl border px-3 py-3 text-left text-xs font-black transition"
                                    :class="
                                        question.answer === option.value
                                            ? 'border-wasplex-cyan/45 bg-wasplex-cyan/12 text-wasplex-cyan'
                                            : 'border-white/8 bg-black/15 text-white/55 hover:border-white/20 hover:text-white'
                                    "
                                    @click="saveAnswer(question, option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>

                            <div
                                v-if="question.answer"
                                class="mt-4 flex items-center justify-between gap-3 border-t border-white/6 pt-4"
                            >
                                <p class="text-[0.68rem] text-white/28">
                                    Mise à jour {{ date(question.answeredAt) }}
                                </p>
                                <button
                                    type="button"
                                    class="text-[0.68rem] font-black text-rose-300/75"
                                    @click="removeAnswer(question)"
                                >
                                    Retirer
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-10">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">
                        Centre de consentements
                    </p>
                    <h2 class="mt-2 text-xl font-black">Ce que Wasplex peut utiliser</h2>
                    <p class="mt-2 text-sm leading-6 text-white/42">
                        Chaque autorisation est séparée, versionnée et retirable. Un retrait bloque
                        les nouveaux matchings concernés.
                    </p>

                    <div class="mt-5 space-y-4">
                        <article
                            v-for="consent in consents"
                            :key="consent.code"
                            class="rounded-[1.75rem] border p-5"
                            :class="
                                consent.active
                                    ? 'border-emerald-300/20 bg-emerald-300/[0.055]'
                                    : 'border-white/8 bg-white/[0.03]'
                            "
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <h3 class="font-black">{{ consent.title }}</h3>
                                    <p class="mt-2 text-sm leading-6 text-white/45">
                                        {{ consent.description }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-3 py-1 text-[0.62rem] font-black uppercase"
                                    :class="
                                        consent.active
                                            ? 'bg-emerald-300/12 text-emerald-200'
                                            : 'bg-white/7 text-white/40'
                                    "
                                >
                                    {{ consent.active ? 'Autorisé' : 'Non autorisé' }}
                                </span>
                            </div>

                            <p
                                v-if="consent.refusalConsequence"
                                class="mt-3 rounded-2xl bg-black/15 px-3 py-3 text-xs leading-5 text-white/35"
                            >
                                En cas de refus : {{ consent.refusalConsequence }}
                            </p>

                            <p v-if="consent.decidedAt" class="mt-3 text-[0.68rem] text-white/27">
                                Dernière décision : {{ consent.decision }} ·
                                {{ date(consent.decidedAt) }} · texte v{{ consent.version }}
                            </p>

                            <div class="mt-4 grid grid-cols-2 gap-2">
                                <button
                                    v-if="!consent.active"
                                    type="button"
                                    class="rounded-2xl bg-emerald-300 px-4 py-3 text-xs font-black text-[#062018]"
                                    @click="decideConsent(consent, 'granted')"
                                >
                                    Autoriser
                                </button>
                                <button
                                    v-if="!consent.active"
                                    type="button"
                                    class="rounded-2xl border border-white/10 px-4 py-3 text-xs font-black text-white/55"
                                    @click="decideConsent(consent, 'denied')"
                                >
                                    Refuser
                                </button>
                                <button
                                    v-else
                                    type="button"
                                    class="col-span-2 rounded-2xl border border-rose-300/20 px-4 py-3 text-xs font-black text-rose-200"
                                    @click="decideConsent(consent, 'withdrawn')"
                                >
                                    Retirer mon consentement
                                </button>
                            </div>
                        </article>
                    </div>
                </section>

                <aside
                    class="mt-8 rounded-[1.75rem] border border-wasplex-gold/15 bg-wasplex-gold/[0.045] p-5"
                >
                    <p class="font-black text-wasplex-gold">Données toujours interdites</p>
                    <p class="mt-2 text-xs leading-5 text-white/38">
                        Santé, Alertes, Fonds, KYC, dette, vulnérabilité, religion, politique,
                        orientation sexuelle, grossesse supposée et historique judiciaire ne peuvent
                        jamais alimenter le ciblage commercial.
                    </p>
                </aside>
            </section>

            <nav
                class="sticky bottom-0 mt-6 grid grid-cols-4 border-t border-white/10 bg-wasplex-night/95 px-3 py-3 text-center text-[0.68rem] font-bold text-white/40 backdrop-blur-xl"
            >
                <Link href="/mon-espace">Espace</Link><span>Feed</span
                ><Link href="/wallet">Wallet</Link
                ><span class="text-wasplex-cyan">Profil</span>
            </nav>
        </div>
    </main>
</template>
