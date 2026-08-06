<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Le chemin par défaut du paquet (`resource_path('js/pages')`, minuscule)
    | ne correspond pas à la convention réelle du dépôt (`resources/js/Pages`,
    | PascalCase — cf. tous les contrôleurs `Inertia::render('Identity/...')`).
    | Sur un système de fichiers sensible à la casse, cela rend
    | `assertInertia()->component()` incapable de vérifier qu'une page existe.
    | Ce fichier corrige uniquement ce chemin ; le reste hérite du paquet.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [

            resource_path('js/Pages'),

        ],

        'extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

];
