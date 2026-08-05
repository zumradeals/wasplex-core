<?php

declare(strict_types=1);

/*
 * NOTE: arch expectations target App\Http and App\Shared explicitly rather
 * than the whole "App" namespace. vendor/laravel/pint's own source code
 * happens to live under the same "App" namespace (Kernel, Providers,
 * Fixers, ...), and pestphp/pest-plugin-arch crashes (uninitialized
 * ObjectDescriptionBase::$path) when a violation must be reported against
 * one of those vendor-sourced "App" classes. Scoping to our real
 * sub-namespaces avoids the collision entirely.
 */

arch('app HTTP and shared kernel code declare strict types')
    ->expect(['App\Http', 'App\Shared'])
    ->toUseStrictTypes();

arch('app HTTP and shared kernel code never leave debugging statements behind')
    ->expect(['App\Http', 'App\Shared'])
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('app HTTP and shared kernel code do not depend on test helpers')
    ->expect(['App\Http', 'App\Shared'])
    ->not->toUse('Tests');
