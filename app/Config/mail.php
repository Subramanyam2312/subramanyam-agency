<?php

declare(strict_types=1);

use App\Core\Env;

return [
    // 'smtp' in production, 'log' locally (writes to storage/logs/mail.log).
    'driver'     => Env::get('MAIL_DRIVER', 'smtp'),

    'host'       => Env::get('MAIL_HOST', ''),
    'port'       => (int) Env::get('MAIL_PORT', 587),
    'username'   => Env::get('MAIL_USERNAME', ''),
    'password'   => Env::get('MAIL_PASSWORD', ''),
    'encryption' => Env::get('MAIL_ENCRYPTION', 'tls'),

    /*
     * MUST be an address on the site's own domain with matching SPF/DKIM records.
     * A gmail.com From address sent through this server fails DMARC and disappears.
     */
    'from' => [
        'address' => Env::get('MAIL_FROM_ADDRESS', 'noreply@example.com'),
        'name'    => Env::get('MAIL_FROM_NAME', Env::get('APP_NAME', 'Agency')),
    ],

    // Where contact-form notifications land. Any inbox is fine here.
    'to' => [
        'address' => Env::get('MAIL_TO_ADDRESS', ''),
        'name'    => Env::get('MAIL_TO_NAME', ''),
    ],
];
