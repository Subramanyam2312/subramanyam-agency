<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Env;

/**
 * Shared boot sequence for the front controller and every CLI script.
 *
 * The entry point is responsible for defining BASE_PATH and PUBLIC_PATH before
 * including this file, because those differ between local development (where the
 * web root is public/) and Hostinger (where it is public_html/).
 */

if (!defined('BASE_PATH')) {
    exit('BASE_PATH must be defined before bootstrapping.');
}

define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEW_PATH', BASE_PATH . '/app/Views');

require BASE_PATH . '/vendor/autoload.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/app/Config');

date_default_timezone_set((string) config('app.timezone', 'UTC'));

mb_internal_encoding('UTF-8');

/*
 * Errors are never rendered to the browser in production — a stack trace on a
 * 500 page hands an attacker the absolute filesystem path and the database name.
 */
$debug = (bool) config('app.debug');

ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');
error_reporting(E_ALL);

foreach ([STORAGE_PATH . '/logs', STORAGE_PATH . '/cache', STORAGE_PATH . '/sessions'] as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0770, true);
    }
}
