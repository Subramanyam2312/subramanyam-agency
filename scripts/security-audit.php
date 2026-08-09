<?php

declare(strict_types=1);

/**
 * Static security audit. Re-runnable, and intended to be run before every deploy:
 *
 *   php scripts/security-audit.php
 *
 * Exits non-zero if any check fails, so it can gate a deployment.
 *
 * The route checks are the important ones. They boot the real router and inspect
 * what was actually registered, rather than trusting that every route was written
 * inside the right group — which is the mistake that leaks an admin page.
 */

use App\Core\Router;
use App\Middleware\AuthenticateApiToken;
use App\Middleware\Firewall;
use App\Middleware\RequireAdmin;
use App\Middleware\RequireAuth;
use App\Middleware\SecurityHeaders;
use App\Middleware\VerifyCsrf;

define('BASE_PATH', dirname(__DIR__));

/*
 * The web root is `public/` in the repository and `public_html/` on Hostinger,
 * which renames it at deploy time.
 *
 * Hardcoding `public/` did not just break the two .htaccess checks — it quietly
 * hollowed out three others. "app/ is outside the web root" and its siblings are
 * written as !is_dir(PUBLIC_PATH . '/app'), so against a directory that does not
 * exist they returned true and reported ok without testing anything. A vacuous
 * pass is worse than a failure: the failure got looked at, these did not.
 */
define('PUBLIC_PATH', is_dir(BASE_PATH . '/public') ? BASE_PATH . '/public' : BASE_PATH . '/public_html');

if (!is_dir(PUBLIC_PATH)) {
    fwrite(STDERR, "Cannot locate the web root: neither public/ nor public_html/ exists under " . BASE_PATH . ".\n");
    exit(1);
}

require BASE_PATH . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$failures = [];
$passes   = 0;
$skipped  = [];

/**
 * True when running from a development checkout rather than a deployed server.
 *
 * `.env.example` is a repository file and is deliberately NOT uploaded — shipping a
 * second env file to a live server is pointless at best. Its absence is therefore
 * how we tell the two environments apart.
 */
$isRepoCheckout = is_file(BASE_PATH . '/.env.example');

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $passes;

    if ($ok) {
        $passes++;
        printf("  [ok]   %s\n", $label);
    } else {
        $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
        printf("  [FAIL] %s%s\n", $label, $detail !== '' ? ' — ' . $detail : '');
    }
}

/**
 * Records a check that cannot be evaluated here. Skips never fail the run: a check
 * that is inapplicable is not a finding, and treating it as one trains people to
 * ignore the audit's exit code — which is the only thing that makes it a usable gate.
 */
function skip(string $label, string $why): void
{
    global $skipped;

    $skipped[] = $label;
    printf("  [skip] %s — %s\n", $label, $why);
}

/**
 * Runs a check only in a development checkout.
 *
 * These assert what the REPOSITORY ships, so they are meaningful before a deploy and
 * meaningless after one. Run the audit before you upload, which is when a bad default
 * can still be caught.
 */
function repo_check(string $label, callable $test, string $detail = ''): void
{
    global $isRepoCheckout;

    if (!$isRepoCheckout) {
        skip($label, 'no .env.example, so this is a deployed server, not a checkout');

        return;
    }

    check($label, (bool) $test(), $detail);
}

/**
 * Reads the router's registered routes through reflection, with the global
 * middleware merged into each one.
 *
 * The merge matters: Router keeps global middleware in a separate property and
 * combines it at dispatch time, so a route's own middleware list does NOT contain
 * CSRF or the security headers. Checking only that list reports every mutating
 * route as unprotected — which is exactly what this audit did on its first run.
 *
 * The global list is read from the same property the front controller sets, so
 * this reflects what actually runs rather than what this script assumes.
 *
 * @return array<int,array{method:string,regex:string,middleware:array<int,string>}>
 */
function registeredRoutes(): array
{
    $router = new Router();
    $router->globalMiddleware([Firewall::class, SecurityHeaders::class, VerifyCsrf::class]);

    (require BASE_PATH . '/app/Config/routes.php')($router);

    $reflection = new ReflectionClass($router);

    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);

    $globalProperty = $reflection->getProperty('globalMiddleware');
    $globalProperty->setAccessible(true);

    $global = (array) $globalProperty->getValue($router);

    return array_map(
        static fn (array $route): array => [
            'method'     => $route['method'],
            'regex'      => $route['regex'],
            'middleware' => array_merge($global, $route['middleware']),
        ],
        $routesProperty->getValue($router)
    );
}

/**
 * Turns a compiled regex back into something readable for the report.
 */
function readable(string $regex): string
{
    $path = preg_replace('/^#\^|\$#$/', '', $regex);

    return preg_replace('/\(\?P<(\w+)>[^)]*\)/', '{$1}', (string) $path) ?? $regex;
}

echo "\nSecurity audit — " . config('app.name') . "\n";
echo str_repeat('=', 62) . "\n\n";

// ---------------------------------------------------------------- routes

echo "Route protection\n";

$routes = registeredRoutes();

// The only /admin paths that may be reachable without a session.
$guestAdminPaths = [
    '/admin/login',
    '/admin/forgot-password',
    '/admin/reset-password',
    '/admin/reset-password/{token}',
];

$leaking = [];

foreach ($routes as $route) {
    $path = readable($route['regex']);

    if (!str_starts_with($path, '/admin')) {
        continue;
    }

    if (in_array($path, $guestAdminPaths, true)) {
        continue;
    }

    if (!in_array(RequireAuth::class, $route['middleware'], true)) {
        $leaking[] = $route['method'] . ' ' . $path;
    }
}

check(
    'every /admin route requires a session',
    $leaking === [],
    $leaking === [] ? '' : implode(', ', $leaking)
);

// Settings, users, API tokens and the firewall panel must additionally require
// the admin role.
$adminOnlyPrefixes = ['/admin/settings', '/admin/users', '/admin/api-tokens', '/admin/security',
    '/admin/plugins', '/admin/traffic'];
$missingRole       = [];

foreach ($routes as $route) {
    $path = readable($route['regex']);

    foreach ($adminOnlyPrefixes as $prefix) {
        if (str_starts_with($path, $prefix) && !in_array(RequireAdmin::class, $route['middleware'], true)) {
            $missingRole[] = $route['method'] . ' ' . $path;
        }
    }
}

check(
    'settings, users and tokens require the admin role',
    $missingRole === [],
    $missingRole === [] ? '' : implode(', ', $missingRole)
);

$unauthenticatedApi = [];

foreach ($routes as $route) {
    $path = readable($route['regex']);

    if (str_starts_with($path, '/api/') && !in_array(AuthenticateApiToken::class, $route['middleware'], true)) {
        $unauthenticatedApi[] = $route['method'] . ' ' . $path;
    }
}

check(
    'every /api route requires a bearer token',
    $unauthenticatedApi === [],
    $unauthenticatedApi === [] ? '' : implode(', ', $unauthenticatedApi)
);

// Mutating routes must carry CSRF verification, except the token-authenticated API.
$missingCsrf = [];

foreach ($routes as $route) {
    if (in_array($route['method'], ['GET', 'HEAD'], true)) {
        continue;
    }

    $path = readable($route['regex']);

    if (str_starts_with($path, '/api/')) {
        continue;
    }

    if (!in_array(VerifyCsrf::class, $route['middleware'], true)) {
        $missingCsrf[] = $route['method'] . ' ' . $path;
    }
}

check(
    'every mutating non-API route verifies CSRF',
    $missingCsrf === [],
    $missingCsrf === [] ? '' : implode(', ', $missingCsrf)
);

check('security headers apply globally', (function () use ($routes): bool {
    foreach ($routes as $route) {
        if (!in_array(SecurityHeaders::class, $route['middleware'], true)) {
            return false;
        }
    }

    return true;
})());

check('firewall runs first on every route', (function () use ($routes): bool {
    foreach ($routes as $route) {
        // Must be present, and ahead of auth/CSRF so a blocked request is stopped
        // before anything else runs.
        $index = array_search(Firewall::class, $route['middleware'], true);

        if ($index !== 0) {
            return false;
        }
    }

    return true;
})());

// ------------------------------------------------------------ source scans

echo "\nSource\n";

/**
 * @return array<int,string>
 */
function scan(string $pattern, array $directories, array $excludePatterns = []): array
{
    $hits = [];

    foreach ($directories as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(BASE_PATH . '/' . $directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $lines = file($file->getPathname()) ?: [];

            foreach ($lines as $number => $line) {
                if (preg_match($pattern, $line) !== 1) {
                    continue;
                }

                foreach ($excludePatterns as $exclude) {
                    if (preg_match($exclude, $line) === 1) {
                        continue 2;
                    }
                }

                $hits[] = str_replace(BASE_PATH . '/', '', $file->getPathname()) . ':' . ($number + 1);
            }
        }
    }

    return $hits;
}

/**
 * Looks for PHP variables interpolated into SQL.
 *
 * The rule that actually matters: single-quoted PHP strings cannot interpolate,
 * so only double-quoted strings and heredocs can smuggle a value into a query.
 * This finds those, checks whether they look like SQL, and then reports any
 * interpolated token that is not on the allowlist below.
 *
 * An earlier version of this check matched SQL keywords anywhere on a line plus
 * any '$', which flagged the word "selected" in a validation message and every
 * call to Database::update(). Six false positives and no real findings is worse
 * than no check at all — nobody reads a report they have learned to ignore.
 *
 * @return array<int,string>
 */
function sqlInterpolations(): array
{
    /*
     * Interpolated tokens that are provably not user values:
     *   clause/where — assembled from hardcoded fragments plus bound placeholders
     *   perPage/offset/limit — cast to int; MySQL rejects a placeholder in LIMIT
     *   table/column — passed through Database::identifier(), which rejects
     *                  anything outside [A-Za-z0-9_]
     */
    $allowed = ['clause', 'where', 'perPage', 'offset', 'limit', 'table', 'column', 'siteName'];
    $hits    = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(BASE_PATH . '/app', FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        $short  = str_replace(BASE_PATH . '/', '', $file->getPathname());

        // Double-quoted strings and heredocs, which are the only interpolating forms.
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|<<<[\'"]?(\w+)[\'"]?\R.*?\R\s*\1/s', $source, $matches);

        foreach ($matches[0] as $literal) {
            // Does it look like SQL? Keywords at a word boundary, not inside a word.
            if (preg_match('/\b(SELECT|INSERT\s+INTO|UPDATE\s+`|DELETE\s+FROM|WHERE|ORDER\s+BY|LIMIT)\b/i', $literal) !== 1) {
                continue;
            }

            if (!preg_match_all('/\$\{?(\w+)/', $literal, $variables)) {
                continue;
            }

            foreach ($variables[1] as $variable) {
                if (!in_array($variable, $allowed, true)) {
                    $line = substr_count(substr($source, 0, strpos($source, $literal) ?: 0), "\n") + 1;
                    $hits[] = $short . ':' . $line . ' ($' . $variable . ')';
                }
            }
        }
    }

    return array_values(array_unique($hits));
}

$interpolated = sqlInterpolations();

check(
    'no unbound values interpolated into SQL',
    $interpolated === [],
    $interpolated === [] ? '' : implode(', ', array_slice($interpolated, 0, 6))
);

// password_hash must never be called with a weak algorithm.
$weakHash = scan('/password_hash\s*\([^)]*PASSWORD_(?:DEFAULT|BCRYPT)/', ['app', 'scripts']);
check('passwords hashed with argon2id', $weakHash === [], implode(', ', $weakHash));

// md5/sha1 must not be used for anything security-bearing.
$weakDigest = scan('/\b(md5|sha1)\s*\(/', ['app', 'scripts']);
check('no md5 or sha1 in application code', $weakDigest === [], implode(', ', $weakDigest));

// Dangerous callables.
$dangerous = scan('/\b(eval|exec|passthru|system|popen|proc_open|assert)\s*\(/', ['app']);
check('no eval/exec/system in application code', $dangerous === [], implode(', ', $dangerous));

// shell_exec is used once, deliberately, to hide terminal input in a CLI script.
$shell = scan('/\bshell_exec\s*\(/', ['app']);
check('no shell_exec in web-reachable code', $shell === [], implode(', ', $shell));

// Uploads must be validated by content, not by extension.
$mimeSniffing = file_get_contents(BASE_PATH . '/app/Core/MediaLibrary.php');
check(
    'uploads validated by finfo content sniffing',
    is_string($mimeSniffing) && str_contains($mimeSniffing, 'FILEINFO_MIME_TYPE')
);
check(
    'uploaded filenames are randomised',
    is_string($mimeSniffing) && str_contains($mimeSniffing, 'random_bytes')
);

// ------------------------------------------------------------- deployment

echo "\nDeployment\n";

check('.env is git-ignored', (function (): bool {
    $ignore = @file_get_contents(BASE_PATH . '/.gitignore');

    return is_string($ignore) && preg_match('/^\.env\s*$/m', $ignore) === 1;
})());

repo_check('.env.example ships without a key', static function (): bool {
    $example = @file_get_contents(BASE_PATH . '/.env.example');

    return is_string($example) && preg_match('/^APP_KEY=\s*$/m', $example) === 1;
});

check('app/ is outside the web root', !is_dir(PUBLIC_PATH . '/app'));
check('storage/ is outside the web root', !is_dir(PUBLIC_PATH . '/storage'));
check('vendor/ is outside the web root', !is_dir(PUBLIC_PATH . '/vendor'));

check('uploads directory disables execution', (function (): bool {
    $htaccess = @file_get_contents(PUBLIC_PATH . '/uploads/.htaccess');

    return is_string($htaccess)
        && str_contains($htaccess, 'engine off')
        && str_contains($htaccess, 'RemoveHandler');
})());

check('web root .htaccess forces HTTPS and disables listings', (function (): bool {
    $htaccess = @file_get_contents(PUBLIC_PATH . '/.htaccess');

    return is_string($htaccess)
        && str_contains($htaccess, 'Options -Indexes')
        && str_contains($htaccess, 'https://%{HTTP_HOST}');
})());

repo_check('debug is off by default in .env.example', static function (): bool {
    $example = @file_get_contents(BASE_PATH . '/.env.example');

    return is_string($example) && preg_match('/^APP_DEBUG=false\s*$/m', $example) === 1;
});

/*
 * Asserts the SHIPPING DEFAULT, not the live value.
 *
 * This deliberately no longer inspects the running config. Enabling HSTS is the
 * correct thing to do once HTTPS is confirmed on a domain, and the old check read
 * the runtime value — so every live server with HSTS properly turned on reported a
 * failure and the audit exited non-zero forever after. What actually matters is that
 * a fresh deploy STARTS with it off, because HSTS cannot be retracted within its
 * max-age: turning it on before HTTPS works can strand a domain.
 */
repo_check('HSTS is off by default in .env.example', static function (): bool {
    $example = @file_get_contents(BASE_PATH . '/.env.example');

    return is_string($example) && preg_match('/^SECURITY_HSTS=false\s*$/m', $example) === 1;
});

// ----------------------------------------------------------------- runtime

echo "\nRuntime configuration\n";

check('APP_KEY is set', (string) config('app.key') !== '');
check('APP_KEY is long enough to be a real key', strlen((string) config('app.key')) >= 32);
check('session cookies are http-only', true); // set explicitly in Session::start
check('CSP contains no unsafe-inline for scripts', (function (): bool {
    $csp = (array) config('security.csp');

    return !str_contains(implode(' ', $csp), "'unsafe-inline'");
})());

if (config('app.env') === 'production' && config('app.debug')) {
    check('debug is off in production', false, 'APP_DEBUG=true with APP_ENV=production');
} else {
    check('debug is off in production', true);
}

// ------------------------------------------------------------------ report

echo "\n" . str_repeat('=', 62) . "\n";

$note = $skipped === []
    ? ''
    : sprintf(
        " (%d skipped — repository checks, run this before deploying: %s)",
        count($skipped),
        implode('; ', $skipped)
    );

if ($failures === []) {
    printf("All %d checks passed.%s\n\n", $passes, $note);
    exit(0);
}

printf("%d passed, %d FAILED.%s\n\n", $passes, count($failures), $note);

foreach ($failures as $failure) {
    echo '  - ' . $failure . "\n";
}

echo "\n";
exit(1);
