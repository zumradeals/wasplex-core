<?php

arch('application code uses strict domain namespaces')
    ->expect('App\Modules')
    ->toOnlyUse([
        'App\Modules',
        'Illuminate',
        'Psr',
        'Carbon',
    ]);

arch('module infrastructure is not a model namespace')
    ->expect('App\Modules\Platform\Infrastructure')
    ->not->toHaveSuffix('Model');
