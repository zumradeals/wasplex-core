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
    <main class="bg-wpx-canvas mx-auto flex min-h-screen max-w-md flex-col gap-6 px-4 py-10 sm:max-w-2xl">
        <header class="flex items-center gap-3">
            <span class="rounded-wpx-md bg-wpx-navy-950 text-wpx-gold px-3 py-1 text-sm font-semibold"> Wasplex </span>
            <h1 class="text-wpx-text text-lg font-semibold">Socle technique — P000</h1>
        </header>

        <section class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-5">
            <h2 class="text-wpx-text-muted mb-3 text-sm font-semibold tracking-wide uppercase">Environnement</h2>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-wpx-text-muted">Environnement</dt>
                <dd class="text-wpx-text font-medium">{{ environment }}</dd>

                <dt class="text-wpx-text-muted">PHP</dt>
                <dd class="text-wpx-text font-medium">{{ versions.php }}</dd>

                <dt class="text-wpx-text-muted">Laravel</dt>
                <dd class="text-wpx-text font-medium">{{ versions.laravel }}</dd>

                <dt class="text-wpx-text-muted">Trace ID</dt>
                <dd class="text-wpx-text truncate font-mono text-xs">{{ traceId }}</dd>
            </dl>
        </section>

        <section class="rounded-wpx-lg shadow-wpx-card bg-wpx-surface p-5">
            <h2 class="text-wpx-text-muted mb-3 text-sm font-semibold tracking-wide uppercase">Connectivité</h2>
            <ul class="flex flex-col gap-2 text-sm">
                <li class="flex items-center justify-between">
                    <span>PostgreSQL</span>
                    <span
                        class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                        :class="
                            checks.database
                                ? 'bg-wpx-success/10 text-wpx-success-light'
                                : 'bg-wpx-danger/10 text-wpx-danger-light'
                        "
                    >
                        {{ checks.database ? 'OK' : 'Indisponible' }}
                    </span>
                </li>
                <li class="flex items-center justify-between">
                    <span>Redis</span>
                    <span
                        class="rounded-wpx-sm px-2 py-0.5 text-xs font-semibold"
                        :class="
                            checks.redis
                                ? 'bg-wpx-success/10 text-wpx-success-light'
                                : 'bg-wpx-danger/10 text-wpx-danger-light'
                        "
                    >
                        {{ checks.redis ? 'OK' : 'Indisponible' }}
                    </span>
                </li>
            </ul>
        </section>

        <p class="text-wpx-text-muted text-xs">
            Aucune règle métier n'est présente à ce stade (P000 — socle du dépôt et stack).
        </p>
    </main>
</template>
