<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'Agency'),
    'url'      => rtrim((string) Env::get('APP_URL', 'http://localhost:8130'), '/'),
    'env'      => Env::get('APP_ENV', 'production'),
    'debug'    => (bool) Env::get('APP_DEBUG', false),

    /*
     * Used as the HMAC key for hashing IP addresses before they are stored, so the
     * database never holds directly identifying network data. Rotating this value
     * invalidates existing rate-limit buckets, which is harmless.
     */
    'key'      => Env::require('APP_KEY'),

    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale'   => 'en_IN',

    /*
     * Serves the maintenance page to everyone except a logged-in admin, so the site
     * can be checked while it is closed to the public. Toggled from CMS settings.
     */
    'maintenance' => false,
];
