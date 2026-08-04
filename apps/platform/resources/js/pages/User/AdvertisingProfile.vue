<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
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
    signalKind: string;
    sectorCode: string;
    sectorLabel: string;
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

type Sector = {
    code: string;
    name: string;
    icon: string | null;
    questions: number;
    answered: number;
    explored: boolean;
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
        market: { code: string; name: string };
        summary: {
            activeInformation: number;
            sectorsExplored: number;
            availableSectors: number;
            pendingSuggestions: number;
        };
        sectors: Sector[];
        completion: number;
        answered: number;
        total: number;
        questions: Question[];
    };
    consents: Consent[];
    flash: { success: string | null };
    errors: Record<string, string[]>;
}>();

const groupedQuestions = computed(() => {
    const groups = new Map<string, { code: string; label: string; questions: Question[] }>();

    for (const question of props.profile.questions) {
        const group = groups.get(question.sectorCode) ?? {
            code: question.sectorCode,
            label: question.sectorLabel,
            questions: [],
        };
        group.questions.push(question);
        groups.set(question.sectorCode, group);
    }

    return [...groups.values()];
});

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

const decideConsent = (consent: Consent, status: 'granted' | 'denied' | 'withdrawn') => {
    const confirmation =
        status === 'withdrawn'
            ? 'Retirer cette autorisation pour tous les nouveaux usages ?'
            : status === 'denied'
              ? 'Confirmer votre refus pour cette utilisation ?'
              : 'Autoriser cette utilisation de votre profil ?';

    if (!window.confirm(confirmation)) return;

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
        ? new Intl.DateTimeFormat('fr-ML', {
              dateStyle: 'medium',
          }).format(new Date(value))
        : null;

const signalLabel = (kind: string) =>
    ({
        interest: 'Centre d’intérêt',
        habit: 'Habitude',
        preference: 'Préférence',
        project: 'Projet',
        need: 'Besoin actuel',
        status: 'Situation déclarée',
        territory: 'Zone utile',
    })[kind] ?? 'Information facultative';
</script>

<template>
    <Head title="Profil intelligent" />
    <main class="min-h-screen bg-[#03101e] text-white">
        <div class="mx-auto min-h-screen max-w-3xl border-x border-white/5 bg-wasplex-night shadow-2xl">
            <header class="sticky top-0 z-20 border-b border-white/10 bg-wasplex-night/90 px-5 py-4 backdrop-blur-xl">
                <div class="flex items-center justify-between">
                    <WasplexMark />
                    <Link href="/mon-espace" class="text-xs font-black text-wasplex-cyan">Mon Espace</Link>
                </div>
                <div class="mt-4"><SpaceSwitcher :spaces="spaces" /></div>
            </header>

            <section class="px-5 py-7 sm:px-8">
                <div class="rounded-[2rem] border border-wasplex-cyan/15 bg-gradient-to-br from-wasplex-blue/55 to-white/[0.035] p-6 sm:p-8">
                    <div class="flex flex-wrap items-start justify-between gap-5">
                        <div class="max-w-xl">
                            <p class="text-xs font-black tracking-[0.2em] text-wasplex-cyan uppercase">Vos choix évoluent avec vous</p>
                            <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Mon Profil intelligent</h1>
                            <p class="mt-4 text-sm leading-6 text-white/52">
                                Indiquez ce qui vous intéresse, vos projets, vos habitudes et ce que vous ne souhaitez pas voir. Wasplex utilise uniquement les informations autorisées pour sélectionner des publicités plus pertinentes.
                            </p>
                        </div>
                        <div class="rounded-2xl border border-white/10 bg-black/20 px-4 py-3 text-right">
                            <p class="text-[0.65rem] font-black tracking-widest text-white/35 uppercase">Marché initial</p>
                            <p class="mt-1 font-black text-wasplex-gold">{{ profile.market.name }}</p>
                            <p class="mt-1 text-[0.65rem] text-white/30">Architecture internationale</p>
                        </div>
                    </div>
                </div>

                <div v-if="flash.success" class="mt-5 rounded-2xl border border-emerald-300/20 bg-emerald-300/8 px-4 py-3 text-sm font-bold text-emerald-200">
                    {{ flash.success }}
                </div>

                <div v-if="errors.answer?.length || errors.status?.length || errors.purpose?.length || errors.question?.length" class="mt-5 rounded-2xl border border-rose-300/20 bg-rose-300/8 px-4 py-3 text-sm font-bold text-rose-200">
                    {{ errors.answer?.[0] || errors.status?.[0] || errors.purpose?.[0] || errors.question?.[0] }}
                </div>

                <section class="mt-7 grid gap-3 sm:grid-cols-3">
                    <article class="rounded-2xl border border-white/8 bg-white/[0.035] p-5">
                        <p class="text-xs font-black tracking-widest text-white/35 uppercase">Informations actives</p>
                        <p class="mt-3 text-3xl font-black">{{ profile.summary.activeInformation }}</p>
                        <p class="mt-2 text-xs leading-5 text-white/32">Déclarées ou confirmées par vous.</p>
                    </article>
                    <article class="rounded-2xl border border-wasplex-gold/15 bg-wasplex-gold/[0.045] p-5">
                        <p class="text-xs font-black tracking-widest text-wasplex-gold/70 uppercase">Secteurs explorés</p>
                        <p class="mt-3 text-3xl font-black text-wasplex-gold">{{ profile.summary.sectorsExplored }}</p>
                        <p class="mt-2 text-xs leading-5 text-white/32">Sur {{ profile.summary.availableSectors }} secteurs disponibles.</p>
                    </article>
                    <article class="rounded-2xl border border-wasplex-cyan/15 bg-wasplex-cyan/[0.045] p-5">
                        <p class="text-xs font-black tracking-widest text-wasplex-cyan/70 uppercase">Suggestions à confirmer</p>
                        <p class="mt-3 text-3xl font-black text-wasplex-cyan">{{ profile.summary.pendingSuggestions }}</p>
                        <p class="mt-2 text-xs leading-5 text-white/32">Une proposition IA n’est jamais utilisée sans confirmation.</p>
                    </article>
                </section>

                <section class="mt-9">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">Carte de mes intérêts et projets</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <article v-for="sector in profile.sectors" :key="sector.code" class="rounded-2xl border p-4" :class="sector.explored ? 'border-emerald-300/20 bg-emerald-300/[0.05]' : 'border-white/7 bg-white/[0.025]'">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-black">{{ sector.name }}</p>
                                    <p class="mt-2 text-xs text-white/32">
                                        {{ sector.questions > 0 ? `${sector.questions} question(s) disponible(s)` : 'Catalogue prêt à être enrichi' }}
                                    </p>
                                </div>
                                <span v-if="sector.explored" class="rounded-full bg-emerald-300/12 px-2 py-1 text-[0.6rem] font-black text-emerald-200">Exploré</span>
                            </div>
                        </article>
                    </div>
                    <p class="mt-4 text-xs leading-5 text-white/30">
                        Aucun secteur n’est principal. L’administration Wasplex peut ajouter de nouveaux secteurs, centres d’intérêt et questions sans reconstruire l’application.
                    </p>
                </section>

                <section class="mt-10">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">Questions facultatives et progressives</p>
                    <h2 class="mt-2 text-xl font-black">Précisez seulement ce qui vous est utile</h2>
                    <p class="mt-2 text-sm leading-6 text-white/42">
                        Un intérêt, une possession, une habitude et un projet sont traités comme des informations différentes. Vous pouvez ignorer, modifier ou retirer chaque réponse.
                    </p>

                    <div class="mt-6 space-y-8">
                        <section v-for="group in groupedQuestions" :key="group.code">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-black">{{ group.label }}</h3>
                                <span class="h-px flex-1 bg-white/8" />
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <article v-for="question in group.questions" :key="question.code" class="rounded-[1.75rem] border border-white/8 bg-white/[0.035] p-5">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <p class="text-[0.65rem] font-black tracking-[0.16em] text-wasplex-gold uppercase">{{ signalLabel(question.signalKind) }} · Facultatif</p>
                                            <h4 class="mt-2 text-lg leading-6 font-black">{{ question.prompt }}</h4>
                                        </div>
                                        <span v-if="question.answer" class="shrink-0 rounded-full bg-emerald-300/10 px-3 py-1 text-[0.65rem] font-black text-emerald-200">Renseigné</span>
                                    </div>

                                    <p class="mt-3 text-sm leading-6 text-white/45">{{ question.helpText }}</p>
                                    <p class="mt-2 text-xs leading-5 text-white/28">{{ question.privacyNote }}</p>

                                    <div class="mt-5 grid grid-cols-2 gap-2">
                                        <button v-for="option in question.options" :key="option.value" type="button" class="rounded-2xl border px-3 py-3 text-left text-xs font-black transition" :class="question.answer === option.value ? 'border-wasplex-cyan/45 bg-wasplex-cyan/12 text-wasplex-cyan' : 'border-white/8 bg-black/15 text-white/55 hover:border-white/20 hover:text-white'" @click="saveAnswer(question, option.value)">
                                            {{ option.label }}
                                        </button>
                                    </div>

                                    <div v-if="question.answer" class="mt-4 flex items-center justify-between gap-3 border-t border-white/6 pt-4">
                                        <p class="text-[0.68rem] text-white/28">Mise à jour {{ date(question.answeredAt) }}</p>
                                        <button type="button" class="text-[0.68rem] font-black text-rose-300/75" @click="removeAnswer(question)">Retirer</button>
                                    </div>
                                </article>
                            </div>
                        </section>
                    </div>
                </section>

                <section class="mt-10 rounded-[2rem] border border-white/8 bg-white/[0.025] p-5 sm:p-7">
                    <p class="text-xs font-black tracking-widest text-white/35 uppercase">Mes autorisations</p>
                    <h2 class="mt-2 text-xl font-black">Je garde le contrôle</h2>
                    <p class="mt-2 text-sm leading-6 text-white/42">
                        Les autorisations restent séparées, versionnées et retitables. Un retrait bloque immédiatement les nouveaux matchings concernés.
                    </p>

                    <div class="mt-5 space-y-3">
                        <article v-for="consent in consents" :key="consent.code" class="rounded-2xl border p-4" :class="consent.active ? 'border-emerald-300/20 bg-emerald-300/[0.055]' : 'border-white/8 bg-black/15'">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                <div class="max-w-xl">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-black">{{ consent.title }}</h3>
                                        <span class="rounded-full px-2 py-1 text-[0.6rem] font-black uppercase" :class="consent.active ? 'bg-emerald-300/12 text-emerald-200' : 'bg-white/7 text-white/40'">{{ consent.active ? 'Autorisé' : 'Non autorisé' }}</span>
                                    </div>
                                    <p class="mt-2 text-sm leading-6 text-white/45">{{ consent.description }}</p>
                                    <p v-if="consent.refusalConsequence && !consent.active" class="mt-2 text-xs leading-5 text-white/30">Sans autorisation : {{ consent.refusalConsequence }}</p>
                                </div>
                                <div class="flex shrink-0 gap-2">
                                    <button v-if="!consent.active" type="button" class="rounded-xl bg-emerald-300 px-4 py-2 text-xs font-black text-[#062018]" @click="decideConsent(consent, 'granted')">Autoriser</button>
                                    <button v-if="!consent.active" type="button" class="rounded-xl border border-white/10 px-4 py-2 text-xs font-black text-white/55" @click="decideConsent(consent, 'denied')">Refuser</button>
                                    <button v-else type="button" class="rounded-xl border border-rose-300/20 px-4 py-2 text-xs font-black text-rose-200" @click="decideConsent(consent, 'withdrawn')">Retirer</button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <aside class="mt-8 rounded-[1.75rem] border border-wasplex-gold/15 bg-wasplex-gold/[0.045] p-5">
                    <p class="font-black text-wasplex-gold">Données toujours interdites</p>
                    <p class="mt-2 text-xs leading-5 text-white/38">
                        Santé, Alertes, Fonds, KYC, dette, vulnérabilité, religion, politique, orientation sexuelle, grossesse supposée et historique judiciaire ne peuvent jamais alimenter le ciblage commercial.
                    </p>
                </aside>
            </section>

            <nav class="sticky bottom-0 mt-6 grid grid-cols-4 border-t border-white/10 bg-wasplex-night/95 px-3 py-3 text-center text-[0.68rem] font-bold text-white/40 backdrop-blur-xl">
                <Link href="/mon-espace">Espace</Link><span>Feed</span><Link href="/wallet">Wallet</Link><span class="text-wasplex-cyan">Profil</span>
            </nav>
        </div>
    </main>
</template>
