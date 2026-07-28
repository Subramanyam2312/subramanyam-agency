<?php

declare(strict_types=1);

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Middleware\Firewall;
use App\Middleware\PageOptimise;
use App\Middleware\SecurityHeaders;
use App\Middleware\VerifyCsrf;

/**
 * Front controller. Everything the web server serves dynamically comes through here.
 *
 * BASE_PATH is probed rather than hard-coded, because the deployed layout differs
 * from the repository layout:
 *
 *   local        project/public/index.php      -> app/ is one level up
 *   Hostinger    public_html/index.php         -> app/ is two levels up, outside
 *                                                 the web root
 *
 * That keeps a single codebase working in both places with no edit on deploy.
 */

/*
 * PHP's built-in server (local development only) routes every request through this
 * file, including ones for real files. Returning false hands static assets back to
 * the server. Apache/LiteSpeed never take this branch.
 */
if (PHP_SAPI === 'cli-server') {
    $requested = __DIR__ . urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

    if (is_file($requested)) {
        return false;
    }
}

define('PUBLIC_PATH', __DIR__);

$base = dirname(__DIR__);

if (!is_dir($base . '/app')) {
    $base = dirname($base);
}

if (!is_dir($base . '/app')) {
    http_response_code(500);
    exit('Application directory not found. Check the deployment layout in DEPLOY.md.');
}

define('BASE_PATH', $base);

require BASE_PATH . '/app/bootstrap.php';

$request = Request::capture();

Session::start($request->isSecure());

// Available to every template, so nav partials can mark the active item.
View::share('currentPath', $request->path());

$router = new Router();
// Firewall runs first: a rejected request never reaches CSRF, auth or a controller.
// PageOptimise (cache + traffic) sits after SecurityHeaders so cached responses
// still receive the full header set on the way back out.
$router->globalMiddleware([
    Firewall::class,
    SecurityHeaders::class,
    PageOptimise::class,
    VerifyCsrf::class,
]);

(require BASE_PATH . '/app/Config/routes.php')($router);

try {
    $response = $router->dispatch($request);
} catch (HttpException $e) {
    $response = renderError($request, $e->getStatus(), $e->getMessage(), $e->getHeaders());
} catch (Throwable $e) {
    error_log(sprintf(
        "Unhandled %s: %s in %s:%d\n%s",
        $e::class,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    // The real message is only ever shown with APP_DEBUG on. In production a stack
    // trace would leak absolute paths, the database name and often credentials.
    $message  = config('app.debug')
        ? $e->getMessage() . ' — ' . $e->getFile() . ':' . $e->getLine()
        : 'Something went wrong on our end.';

    $response = renderError($request, 500, $message);
}

$response->send();

/**
 * Builds an error response and pushes it back through the security-header
 * middleware, so a 404 carries the same headers a 200 does.
 *
 * @param array<string,string> $headers
 */
function renderError(Request $request, int $status, string $message, array $headers = []): Response
{
    if ($request->wantsJson()) {
        $response = Response::json([
            'error' => [
                'code'    => $status,
                'message' => $message,
            ],
        ], $status);
    } else {
        $view = is_file(VIEW_PATH . '/errors/' . $status . '.php')
            ? 'errors/' . $status
            : 'errors/generic';

        try {
            $body = View::render($view, ['status' => $status, 'message' => $message]);
        } catch (Throwable) {
            // The error page itself failed — fall back to something that cannot.
            $body = '<!doctype html><meta charset="utf-8"><title>Error ' . $status
                . '</title><h1>Error ' . $status . '</h1><p>' . e($message) . '</p>';
        }

        $response = Response::html($body, $status);
    }

    foreach ($headers as $name => $value) {
        $response->header($name, $value);
    }

    return (new SecurityHeaders())->handle($request, static fn (): Response => $response);
}
