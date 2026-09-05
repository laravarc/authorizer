<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Ability index cache
    |--------------------------------------------------------------------------
    |
    | Built by `php artisan laravarc:authorizer cache`. Runtime reads this file when
    | present; otherwise abilities are discovered in-memory from scan paths.
    |
    */
    'cache_path' => base_path('bootstrap/cache/authorizer-abilities.php'),

    /*
    |--------------------------------------------------------------------------
    | Discovery (native mode — no laravarc/core)
    |--------------------------------------------------------------------------
    |
    | Authorizer NEVER walks the Composer classmap. Only PSR-4 namespaces
    | under these roots (non-vendor by default) are scanned for *Policy.php.
    |
    */
    'scan_paths' => [
        base_path(),
    ],

    'scan_namespaces' => [
        //
    ],

    'exclude_namespaces' => [
        //
    ],

    'exclude_classes' => [
        //
    ],

    'include_classes' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | User model
    |--------------------------------------------------------------------------
    |
    | Used by morph-style relations and install defaults. Swap to your app's
    | Authenticatable class.
    |
    */
    'user_model' => env('AUTHORIZER_USER_MODEL', 'App\\Models\\User'),

    /*
    |--------------------------------------------------------------------------
    | Install defaults
    |--------------------------------------------------------------------------
    |
    | `laravarc:authorizer install` creates a system super role. The name is only a
    | default label — rename freely; bypass uses is_super, not the name.
    |
    */
    'install' => [
        'super_role_name' => env('AUTHORIZER_SUPER_ROLE', 'Owner'),
    ],

];
