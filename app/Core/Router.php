<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\Middleware;

/**
 * Regex router with middleware groups.
 *
 * Routes are declared in app/Config/routes.php and read top to bottom, so the whole
 * URL surface of the site is visible in one file.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,handler:mixed,middleware:array<int,string>}> */
    private array $routes = [];

    /** @var array<int,array{prefix:string,middleware:array<int,string>}> */
    private array $groupStack = [];

    /** @var array<int,string> Middleware applied to every route. */
    private array $globalMiddleware = [];

    /**
     * @param array<int,string> $middleware
     */
    public function globalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    /**
     * @param array{prefix?:string,middleware?:array<int,string>} $attributes
     */
    public function group(array $attributes, callable $definitions): void
    {
        $this->groupStack[] = [
            'prefix'     => $attributes['prefix'] ?? '',
            'middleware' => $attributes['middleware'] ?? [],
        ];

        $definitions($this);

        array_pop($this->groupStack);
    }

    public function get(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, mixed $handler, array $middleware): void
    {
        $prefix           = '';
        $groupMiddleware  = [];

        foreach ($this->groupStack as $group) {
            $prefix         .= $group['prefix'];
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $full = $prefix . $path;
        $full = '/' . trim($full, '/');

        if ($full === '/') {
            $full = '/';
        }

        $this->routes[] = [
            'method'     => $method,
            'regex'      => $this->compile($full),
            'handler'    => $handler,
            'middleware' => array_merge($groupMiddleware, $middleware),
        ];
    }

    /**
     * '/blog/{slug}'              => named capture, anything but a slash
     * '/admin/posts/{id:\d+}'     => named capture with an explicit constraint
     * '/reset/{token:[a-f0-9]{64}}' => constraints may contain {n} quantifiers
     *
     * The constraint sub-pattern has to allow a braced quantifier explicitly.
     * A plain [^}]+ stops at the first closing brace, which silently truncates
     * `[a-f0-9]{64}` to `[a-f0-9]{64` and leaves the route permanently unmatched.
     */
    private function compile(string $path): string
    {
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]|\{\d+(?:,\d*)?\})+))?\}/',
            static function (array $matches): string {
                $name       = $matches[1];
                $constraint = ($matches[2] ?? '') !== '' ? $matches[2] : '[^/]+';

                return '(?P<' . $name . '>' . $constraint . ')';
            },
            $path
        );

        return '#^' . $regex . '$#';
    }

    public function dispatch(Request $request): Response
    {
        $path          = $request->path();
        $method        = $request->method();
        $pathMatched   = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            $pathMatched = true;

            // HEAD is handled by the GET route; PHP discards the body itself.
            if ($route['method'] !== $method && !($route['method'] === 'GET' && $method === 'HEAD')) {
                continue;
            }

            $params = [];

            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $request->setParams($params);

            return $this->runPipeline(
                $request,
                array_merge($this->globalMiddleware, $route['middleware']),
                $route['handler']
            );
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }

    /**
     * @param array<int,string> $middleware
     */
    private function runPipeline(Request $request, array $middleware, mixed $handler): Response
    {
        // Build the chain from the inside out so the first listed middleware runs first.
        $next = function (Request $request) use ($handler): Response {
            return $this->callHandler($request, $handler);
        };

        foreach (array_reverse($middleware) as $class) {
            $current = $next;

            $next = function (Request $request) use ($class, $current): Response {
                /** @var Middleware $instance */
                $instance = new $class();

                return $instance->handle($request, $current);
            };
        }

        return $next($request);
    }

    private function callHandler(Request $request, mixed $handler): Response
    {
        if (is_callable($handler)) {
            return $handler($request);
        }

        [$class, $method] = $handler;

        $controller = new $class();

        return $controller->{$method}($request);
    }
}
