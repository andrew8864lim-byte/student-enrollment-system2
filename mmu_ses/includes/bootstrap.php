<?php
/**
 * Bootstrap
 * --------------------------------------------------------------------
 * Single entry point that every page in the app loads first.
 * Sets up: config, credentials, autoloader, session, and helpers.
 *
 * Pages do:    require_once __DIR__ . '/../includes/bootstrap.php';
 * Root pages:  require_once __DIR__ . '/includes/bootstrap.php';
 */

define('APP_ROOT', dirname(__DIR__));

// 1. Config + credentials
require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/includes/db_credentials.php';

// 2. Tiny PSR-style autoloader for models and controllers
spl_autoload_register(function (string $cls): void {
    foreach (['models', 'controllers'] as $dir) {
        $path = APP_ROOT . '/' . $dir . '/' . $cls . '.php';
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
});

// 3. Session — secure defaults
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// 4. Helper functions (kept in includes/auth.php for backwards-compat)
require_once APP_ROOT . '/includes/auth.php';
