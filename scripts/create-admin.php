<?php

declare(strict_types=1);

/**
 * Creates an admin or editor account from the command line.
 *
 *   php scripts/create-admin.php
 *   php scripts/create-admin.php --name="Subramanyam" --email=you@example.com --role=admin
 *
 * This is how the very first account is made — there is deliberately no public
 * sign-up route anywhere in the application.
 */

use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use App\Models\User;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

require BASE_PATH . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const MIN_PASSWORD_LENGTH = 12;

/**
 * @return array<string,string>
 */
function parseArguments(array $argv): array
{
    $parsed = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--([a-z-]+)=(.*)$/', $argument, $matches) === 1) {
            $parsed[$matches[1]] = $matches[2];
        }
    }

    return $parsed;
}

function prompt(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';

    echo $question . $suffix . ': ';

    $answer = trim((string) fgets(STDIN));

    return $answer === '' ? $default : $answer;
}

/**
 * Reads without echoing. Falls back to a visible prompt with a warning if the
 * terminal cannot be put into no-echo mode.
 */
function promptSecret(string $question): string
{
    echo $question . ': ';

    $stty = shell_exec('command -v stty 2>/dev/null');

    if (is_string($stty) && trim($stty) !== '') {
        $previous = shell_exec('stty -g 2>/dev/null');
        shell_exec('stty -echo 2>/dev/null');

        $value = trim((string) fgets(STDIN));

        if (is_string($previous) && trim($previous) !== '') {
            shell_exec('stty ' . trim($previous) . ' 2>/dev/null');
        } else {
            shell_exec('stty echo 2>/dev/null');
        }

        echo PHP_EOL;

        return $value;
    }

    echo PHP_EOL . '  (warning: this terminal cannot hide input — the password will be visible)' . PHP_EOL;

    return trim((string) fgets(STDIN));
}

$arguments = parseArguments($argv);

echo PHP_EOL . 'Create an account for ' . config('app.name') . PHP_EOL;
echo str_repeat('-', 40) . PHP_EOL;

$name  = $arguments['name']  ?? prompt('Full name');
$email = strtolower($arguments['email'] ?? prompt('Email'));
$role  = $arguments['role']  ?? prompt('Role (admin/editor)', 'admin');

$password = $arguments['password'] ?? '';

if ($password === '') {
    $password = promptSecret('Password (min ' . MIN_PASSWORD_LENGTH . ' characters)');
    $confirm  = promptSecret('Confirm password');

    if ($password !== $confirm) {
        exit('Passwords do not match.' . PHP_EOL);
    }
}

$validator = Validator::make([
    'name'     => $name,
    'email'    => $email,
    'role'     => $role,
    'password' => $password,
], [
    'name'     => 'required|max:120',
    'email'    => 'required|email|max:191|unique:users,email',
    'role'     => 'required|in:admin,editor',
    'password' => 'required|min:' . MIN_PASSWORD_LENGTH,
]);

if ($validator->fails()) {
    echo PHP_EOL . 'Could not create the account:' . PHP_EOL;

    foreach ($validator->errors() as $field => $message) {
        echo '  - ' . $message . PHP_EOL;
    }

    exit(1);
}

$id = Database::insert('users', [
    'name'          => $name,
    'email'         => $email,
    'password_hash' => Auth::hash($password),
    'role'          => $role,
    'is_active'     => 1,
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
]);

Database::insert('activity_log', [
    'user_id'     => $id,
    'action'      => 'user.created',
    'entity_type' => 'user',
    'entity_id'   => $id,
    'meta'        => json_encode(['via' => 'cli', 'role' => $role]),
    'created_at'  => date('Y-m-d H:i:s'),
]);

echo PHP_EOL . "Created {$role} account #{$id} for {$email}." . PHP_EOL;

$isFirstAdmin = $role === 'admin' && User::isLastAdmin($id);

/*
 * seed.sql inserts its posts with no author, because on a fresh install it runs
 * before any user exists. The first admin created afterwards adopts them, so the
 * seeded blog does not render with an empty byline.
 *
 * Scoped to the first admin only: later accounts must never silently acquire
 * authorship of content they did not write.
 */
if ($isFirstAdmin) {
    $adopted = Database::update('posts', ['author_id' => $id], ['author_id' => null]);

    if ($adopted > 0) {
        echo "Attributed {$adopted} seeded post(s) to this account." . PHP_EOL;
    }
}

echo 'Sign in at ' . config('app.url') . '/admin/login' . PHP_EOL . PHP_EOL;

if ($isFirstAdmin) {
    echo 'Note: this is currently the only administrator account.' . PHP_EOL . PHP_EOL;
}
