<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

defineProps<{
    configured: boolean;
    secret: string | null;
    uri: string | null;
}>();

const beginForm = useForm({});
const confirmForm = useForm({ code: '' });
</script>

<template>
    <Head title="Sécurité MFA" />
    <main class="min-h-screen bg-wasplex-night px-5 py-8 text-white">
        <div class="mx-auto max-w-xl">
            <header class="flex items-center justify-between">
                <WasplexMark />
                <Link href="/espace" class="text-sm text-white/50 hover:text-white"
                    >Retour à l’espace</Link
                >
            </header>

            <section
                class="mt-14 rounded-[2rem] border border-white/10 bg-white/[0.055] p-6 sm:p-9"
            >
                <span
                    class="rounded-full bg-wasplex-cyan/10 px-3 py-1 text-xs font-bold text-wasplex-cyan"
                >
                    PROTECTION RENFORCÉE
                </span>
                <h1 class="mt-5 text-3xl font-black">Authentification multifacteur</h1>
                <p class="mt-3 leading-7 text-white/50">
                    Elle est obligatoire pour accéder à la console fondateur et aux actions
                    sensibles.
                </p>

                <div
                    v-if="configured"
                    class="mt-8 rounded-2xl border border-emerald-400/20 bg-emerald-400/10 p-5"
                >
                    <p class="font-bold text-emerald-300">MFA configurée et active</p>
                    <p class="mt-2 text-sm text-white/45">
                        Votre compte peut répondre aux demandes de confirmation renforcée.
                    </p>
                </div>

                <form
                    v-else-if="!secret"
                    class="mt-8"
                    @submit.prevent="beginForm.post('/securite/mfa')"
                >
                    <button
                        class="w-full wasplex-button-primary"
                        type="submit"
                        :disabled="beginForm.processing"
                    >
                        Générer ma clé de sécurité
                    </button>
                </form>

                <div v-else class="mt-8">
                    <ol class="space-y-5 text-sm leading-6 text-white/60">
                        <li>
                            <strong class="text-white">1.</strong> Ajoutez un compte dans votre
                            application d’authentification.
                        </li>
                        <li>
                            <strong class="text-white">2.</strong> Saisissez cette clé, conservée
                            chiffrée par Wasplex.
                        </li>
                    </ol>
                    <code
                        class="mt-4 block overflow-x-auto rounded-2xl bg-black/30 p-4 text-sm font-bold tracking-widest text-wasplex-gold"
                    >
                        {{ secret }}
                    </code>
                    <details class="mt-4 text-xs text-white/35">
                        <summary class="cursor-pointer">Adresse technique TOTP</summary>
                        <p class="mt-2 break-all">{{ uri }}</p>
                    </details>

                    <form
                        class="mt-7"
                        @submit.prevent="confirmForm.post('/securite/mfa/confirmer')"
                    >
                        <label class="block">
                            <span class="mb-2 block text-sm font-semibold">Code à 6 chiffres</span>
                            <input
                                v-model="confirmForm.code"
                                class="wasplex-input text-center text-2xl tracking-[0.4em]"
                                inputmode="numeric"
                                maxlength="6"
                                required
                            />
                            <span
                                v-if="confirmForm.errors.code"
                                class="mt-2 block text-sm text-red-300"
                                >{{ confirmForm.errors.code }}</span
                            >
                        </label>
                        <button
                            class="mt-5 w-full wasplex-button-primary"
                            type="submit"
                            :disabled="confirmForm.processing"
                        >
                            Confirmer et protéger mon compte
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </main>
</template>
