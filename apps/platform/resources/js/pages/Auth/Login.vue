<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import WasplexMark from '@/components/WasplexMark.vue';

const form = useForm({
    identifier: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/connexion', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Connexion" />
    <main class="min-h-screen bg-wasplex-night px-5 py-8 text-white">
        <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md flex-col">
            <header class="flex items-center justify-between">
                <WasplexMark />
                <Link href="/" class="text-sm text-white/50 hover:text-white">Accueil</Link>
            </header>

            <section class="my-auto py-14">
                <p class="text-sm font-bold tracking-[0.24em] text-wasplex-cyan uppercase">
                    Compte universel
                </p>
                <h1 class="mt-4 text-4xl font-black tracking-tight">Bon retour.</h1>
                <p class="mt-3 leading-7 text-white/50">
                    Une seule connexion pour tous vos espaces Wasplex.
                </p>

                <form class="mt-9 space-y-5" @submit.prevent="submit">
                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70">E-mail</span>
                        <input
                            v-model="form.identifier"
                            type="email"
                            autocomplete="username"
                            required
                            class="wasplex-input"
                            placeholder="vous@exemple.com"
                        />
                        <span v-if="form.errors.identifier" class="mt-2 block text-sm text-red-300">
                            {{ form.errors.identifier }}
                        </span>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm font-semibold text-white/70"
                            >Mot de passe</span
                        >
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="wasplex-input"
                        />
                        <span v-if="form.errors.password" class="mt-2 block text-sm text-red-300">
                            {{ form.errors.password }}
                        </span>
                    </label>

                    <label class="flex items-center gap-3 text-sm text-white/55">
                        <input v-model="form.remember" type="checkbox" class="size-4 rounded" />
                        Garder ma session ouverte
                    </label>

                    <button
                        type="submit"
                        class="w-full wasplex-button-primary"
                        :disabled="form.processing"
                    >
                        {{ form.processing ? 'Connexion…' : 'Ouvrir mon espace' }}
                    </button>
                </form>

                <p class="mt-7 text-center text-sm text-white/45">
                    Nouveau sur Wasplex ?
                    <Link href="/inscription" class="font-bold text-wasplex-cyan hover:text-white">
                        Créer un compte
                    </Link>
                </p>
            </section>
        </div>
    </main>
</template>
