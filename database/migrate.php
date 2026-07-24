<?php

declare(strict_types=1);

/**
 * Migration runner.
 *
 *   php database/migrate.php            apply pending migrations
 *   php database/migrate.php --seed     apply, then load seed.sql
 *   php database/migrate.php --status   list applied and pending
 *   php database/migrate.php --fresh    drop every table and re-run (local only)
 *
 * Applied files are recorded by name in a `migrations` table, so re-running is safe.
 * MySQL commits DDL implicitly, so a failed migration cannot be rolled back — the
 * runner stops at the first error and reports which file and statement failed.
 */

use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

require BASE_PATH . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = array_slice($argv, 1);
$seed    = in_array('--seed', $options, true);
$fresh   = in_array('--fresh', $options, true);
$status  = in_array('--status', $options, true);

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `migrations` (
        `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `filename`   VARCHAR(191) NOT NULL,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_migrations_filename` (`filename`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = array_column(Database::select('SELECT `filename` FROM `migrations`'), 'filename');
$files   = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files);

if ($status) {
    echo "Migrations\n----------\n";

    foreach ($files as $file) {
        $name = basename($file);
        printf("  [%s] %s\n", in_array($name, $applied, true) ? 'x' : ' ', $name);
    }

    exit(0);
}

if ($fresh) {
    if (config('app.env') !== 'local') {
        exit("Refusing to run --fresh outside APP_ENV=local.\n");
    }

    echo "Dropping all tables ...\n";

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach (Database::select('SHOW TABLES') as $row) {
        $table = (string) reset($row);
        $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->exec(
        'CREATE TABLE `migrations` (
            `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `filename`   VARCHAR(191) NOT NULL,
            `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_migrations_filename` (`filename`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $applied = [];
}

$ran = 0;

foreach ($files as $file) {
    $name = basename($file);

    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "  applying {$name} ... ";

    $statements = splitSqlStatements((string) file_get_contents($file));

    foreach ($statements as $index => $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            echo "FAILED\n\n";
            echo "Statement #" . ($index + 1) . " of {$name}:\n";
            echo substr($statement, 0, 500) . "\n\n";
            echo $e->getMessage() . "\n";
            exit(1);
        }
    }

    Database::insert('migrations', ['filename' => $name, 'applied_at' => date('Y-m-d H:i:s')]);

    echo "done (" . count($statements) . " statements)\n";
    $ran++;
}

echo $ran === 0 ? "Nothing to migrate.\n" : "Applied {$ran} migration(s).\n";

if ($seed) {
    $seedFile = __DIR__ . '/seed.sql';

    if (!is_file($seedFile)) {
        exit("No seed.sql found.\n");
    }

    echo "Seeding ... ";

    foreach (splitSqlStatements((string) file_get_contents($seedFile)) as $index => $statement) {
        try {
            $pdo->exec($statement);
        } catch (Throwable $e) {
            echo "FAILED\n\nStatement #" . ($index + 1) . ":\n" . substr($statement, 0, 500) . "\n\n";
            echo $e->getMessage() . "\n";
            exit(1);
        }
    }

    echo "done.\n";
}

/**
 * Quote-aware statement splitter.
 *
 * A naive explode(';') breaks the moment seed content contains a semicolon inside
 * a quoted string — which real copy absolutely does. This tracks string literals,
 * back-quoted identifiers, escapes and comments so only genuine statement
 * terminators split the file.
 *
 * @return array<int,string>
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current    = '';
    $length     = strlen($sql);
    $quote      = null;
    $i          = 0;

    while ($i < $length) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';

        if ($quote === null) {
            // Line comment: skip to end of line.
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Block comment.
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $length : $end + 2;

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            } elseif ($char === ';') {
                $trimmed = trim($current);

                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }

                $current = '';
                $i++;

                continue;
            }
        } else {
            // Backslash escape inside a string literal.
            if ($char === '\\' && $quote !== '`') {
                $current .= $char . $next;
                $i += 2;

                continue;
            }

            if ($char === $quote) {
                // A doubled quote is an escaped quote, not a terminator.
                if ($next === $quote) {
                    $current .= $char . $next;
                    $i += 2;

                    continue;
                }

                $quote = null;
            }
        }

        $current .= $char;
        $i++;
    }

    $trimmed = trim($current);

    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}
