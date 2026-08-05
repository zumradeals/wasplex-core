<script setup lang="ts">
interface Versions {
    php: string;
    laravel: string;
}

interface Checks {
    database: boolean;
    redis: boolean;
}

defineProps<{
    traceId: string;
    environment: string;
    versions: Versions;
    checks: Checks;
}>();
</script>

<template>
    <main class="mx-auto flex min-h-screen max-w-md flex-col gap-6 px-4 py-10 sm:max-w-2xl">
        <header class="flex items-center gap-3">
            <span class="rounded-wasplex-md bg-wasplex-black text-wasplex-gold px-3 py-1 text-sm font-semibold">
                Wasplex
            </span>
            <h1 class="text-wasplex-black text-lg font-semibold">Socle technique — P000</h1>
        </header>

        <section class="rounded-wasplex-lg shadow-wasplex-card bg-white p-5">
            <h2 class="text-wasplex-black/60 mb-3 text-sm font-semibold tracking-wide uppercase">Environnement</h2>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-wasplex-black/60">Environnement</dt>
                <dd class="text-wasplex-black font-medium">{{ environment }}</dd>

                <dt class="text-wasplex-black/60">PHP</dt>
                <dd class="text-wasplex-black font-medium">{{ versions.php }}</dd>

                <dt class="text-wasplex-black/60">Laravel</dt>
                <dd class="text-wasplex-black font-medium">{{ versions.laravel }}</dd>

                <dt class="text-wasplex-black/60">Trace ID</dt>
                <dd class="text-wasplex-black truncate font-mono text-xs">{{ traceId }}</dd>
            </dl>
        </section>

        <section class="rounded-wasplex-lg shadow-wasplex-card bg-white p-5">
            <h2 class="text-wasplex-black/60 mb-3 text-sm font-semibold tracking-wide uppercase">Connectivité</h2>
            <ul class="flex flex-col gap-2 text-sm">
                <li class="flex items-center justify-between">
                    <span>PostgreSQL</span>
                    <span
                        class="rounded-wasplex-sm px-2 py-0.5 text-xs font-semibold"
                        :class="
                            checks.database
                                ? 'bg-wasplex-success/10 text-wasplex-success'
                                : 'bg-wasplex-danger/10 text-wasplex-danger'
                        "
                    >
                        {{ checks.database ? 'OK' : 'Indisponible' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Redis</span>
                    <span
                        class="rounded-wasplex-sm px-2 py-0.5 text-xs font-semibold"
                        :class="
                            checks.redis
                                ? 'bg-wasplex-success/10 text-wasplex-success'
                                : 'bg-wasplex-danger/10 text-wasplex-danger'
                        "
                    >
                        {{ checks.redis ? 'OK' : 'Indisponible' }}
                    </span>
                </li>
            </ul>
        </section>

        <p class="text-wasplex-black/50 text-xs">
            Aucune règle métier n'est présente à ce stade (P000 — socle du dépôt et stack).
        </p>
    </main>
</template>
