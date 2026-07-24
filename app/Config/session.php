<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'name' => Env::get('SESSION_NAME', 'agency_session'),

    /*
     * Session files are written to storage/sessions rather than the system default.
     * On shared hosting /tmp is readable by every other account on the box, which
     * makes the default location a session-hijacking hazard.
     */
    'path' => 'storage/sessions',
];
