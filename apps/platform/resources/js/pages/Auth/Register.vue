<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.post('/inscription', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Créer un compte" />
    <main class="min-h-screen bg-wasplex-night px-5 py-8 text-white">
        <div class="mx-auto max-w-md">
            <header class="flex items-center justify-between">
                <WasplexMark />
                <Link href="/connexion" class="text-sm text-white/50 hover:text-white"
                    >Connexion</Link
                >
            </header>

            <section class="py-14">
                <p class="text-sm font-bold tracking-[0.24em] text-wasplex-orange uppercase">
                    Première entrée
                </p>
                <h1 class="mt-4 text-4xl font-black tracking-tight">Votre identité Wasplex.</h1>
                <p class="mt-3 leading-7 text-white/50">
                    Commencez par votre espace personnel. Le Studio Annonceur pourra être activé
                    ensuite.
                </p>

                <form class="mt-9 space-y-5" @submit.prevent="submit">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70"
                            >Nom affiché</span
                        >
                        <input
                            v-model="form.name"
                            required
                            autocomplete="name"
                            class="wasplex-input"
                        />
                        <span v-if="form.errors.name" class="mt-2 block text-sm text-red-300">{{
                            form.errors.name
                        }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70">E-mail</span>
                        <input
                            v-model="form.email"
                            required
                            type="email"
                            autocomplete="email"
                            class="wasplex-input"
                        />
                        <span v-if="form.errors.email" class="mt-2 block text-sm text-red-300">{{
                            form.errors.email
                        }}</span>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70"
                            >Mot de passe</span
                        >
                        <input
                            v-model="form.password"
                            required
                            type="password"
                            autocomplete="new-password"
                            class="wasplex-input"
                        />
                        <span v-if="form.errors.password" class="mt-2 block text-sm text-red-300">{{
                            form.errors.password
                        }}</span>
                        <span class="mt-2 block text-xs leading-5 text-white/35">
                            12 caractères minimum, avec majuscule, minuscule et chiffre.
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70"
                            >Confirmer le mot de passe</span
                        >
                        <input
                            v-model="form.password_confirmation"
                            required
                            type="password"
                            autocomplete="new-password"
                            class="wasplex-input"
                        />
                    </label>

                    <button
                        type="submit"
                        class="w-full wasplex-button-primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Création…' : 'Créer mon compte universel' }}
                    </button>
                </form>
            </section>
        </div>
    </main>
</template>
