<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'host'     => Env::get('DB_HOST', '127.0.0.1'),
    'port'     => (int) Env::get('DB_PORT', 3306),
    'name'     => Env::require('DB_DATABASE'),
    'user'     => Env::require('DB_USERNAME'),
    'password' => (string) Env::get('DB_PASSWORD', ''),

    /*
     * utf8mb4 throughout. The collation is set per-table in the migrations rather
     * than here, because MySQL 8 and MariaDB disagree on the default
     * (utf8mb4_0900_ai_ci vs utf8mb4_general_ci) and the migrations pick explicitly.
     */
    'charset'  => 'utf8mb4',
];
