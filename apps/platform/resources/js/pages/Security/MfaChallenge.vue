<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

const form = useForm({ code: '' });
</script>

<template>
    <Head title="Vérification renforcée" />
    <main class="grid min-h-screen place-items-center bg-wasplex-night px-5 py-8 text-white">
        <section
            class="w-full max-w-md rounded-[2rem] border border-white/10 bg-white/[0.055] p-7 sm:p-9"
        >
            <WasplexMark />
            <p class="mt-10 text-xs font-bold tracking-[0.24em] text-wasplex-orange uppercase">
                Action sensible
            </p>
            <h1 class="mt-3 text-3xl font-black">Confirmez que c’est bien vous.</h1>
            <p class="mt-3 leading-7 text-white/50">
                Entrez le code actuel de votre application d’authentification.
            </p>
            <form class="mt-8" @submit.prevent="form.post('/securite/mfa/verification')">
                <input
                    v-model="form.code"
                    class="wasplex-input text-center text-2xl tracking-[0.4em]"
                    inputmode="numeric"
                    maxlength="6"
                    autofocus
                    required
                />
                <span v-if="form.errors.code" class="mt-2 block text-sm text-red-300">{{
                    form.errors.code
                }}</span>
                <button
                    class="mt-5 w-full wasplex-button-primary"
                    type="submit"
                    :disabled="form.processing"
                >
                    Vérifier
                </button>
            </form>
        </section>
    </main>
</template>
