<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Columns
    |--------------------------------------------------------------------------
    */

    'column.name' => 'Nome',
    'column.guard_name' => 'Ambito',
    'column.roles' => 'Ruoli',
    'column.permissions' => 'Permessi',
    'column.updated_at' => 'Aggiornato il',

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */

    'field.name' => 'Nome',
    'field.guard_name' => 'Ambito',
    'field.permissions' => 'Permessi',
    'field.select_all.name' => 'Seleziona Tutto',
    'field.select_all.message' => 'Abilita tutti i Permessi attualmente <span class="text-primary font-medium">Abilitati</span> per questo ruolo',

    /*
    |--------------------------------------------------------------------------
    | Navigation & Resource
    |--------------------------------------------------------------------------
    */

    'nav.group' => 'Permessi e Ruoli',
    'nav.role.label' => 'Ruoli',
    'nav.role.icon' => 'heroicon-o-shield-check',
    'resource.label.role' => 'Ruolo',
    'resource.label.roles' => 'Ruoli',

    /*
    |--------------------------------------------------------------------------
    | Section & Tabs
    |--------------------------------------------------------------------------
    */

    'section' => 'Entità',
    'resources' => 'Risorse',
    'widgets' => 'Riquadri',
    'pages' => 'Pagine',
    'custom' => 'Permessi Personalizzati',

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */

    'forbidden' => 'Non hai i permessi di accesso',

    /*
    |--------------------------------------------------------------------------
    | Resource Permissions' Labels
    |--------------------------------------------------------------------------
    */

    'resource_permission_prefixes_labels' => [
        'view' => 'Vedere',
        'view_any' => 'Elencare',
        'create' => 'Creare',
        'update' => 'Modificare',
        'delete' => 'Eliminare',
        'delete_any' => 'Eliminare in blocco',
        'force_delete' => 'Eliminare definitivamente',
        'force_delete_any' => 'Eliminare definitivamente in blocco',
        'restore' => 'Ripristinare',
        'restore_any' => 'Ripristinare in blocco',
        'replicate' => 'Duplicare',
        'reorder' => 'Riordinare',
    ],
];
