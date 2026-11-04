<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Everything runs in a closure so no helper variables leak into the global scope.
(static function (): void {
    // Resolve the application root from the REAL location of this directory. When public/ is exposed
    // through a symlink, __DIR__ keeps the unresolved link path under Apache + PHP-FPM, so relative
    // '/../' lookups would point next to the link instead of next to the application.
    $appRoot = dirname(realpath(__DIR__) ?: __DIR__);

    // Determine if the application is in maintenance mode...
    if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
        require $maintenance;
    }

    // Register the Composer autoloader...
    require $appRoot.'/vendor/autoload.php';

    // Bootstrap Laravel and handle the request...
    (require_once $appRoot.'/bootstrap/app.php')
        ->handleRequest(Request::capture());
})();
