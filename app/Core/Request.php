<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish wrapper over the superglobals. Controllers never touch $_GET,
 * $_POST or $_SERVER directly.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;

    /** @var array<string,mixed> */
    private array $post;

    /** @var array<string,mixed> */
    private array $json = [];

    /** @var array<string,mixed> */
    private array $server;

    /** @var array<string,mixed> */
    private array $files;

    /** @var array<string,string> Route parameters, filled in by the Router. */
    private array $params = [];

    private string $method;

    private string $path;

    public function __construct(array $query, array $post, array $server, array $files, string $rawBody = '')
    {
        $this->query  = $query;
        $this->post   = $post;
        $this->server = $server;
        $this->files  = $files;

        $this->method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        // Method spoofing: a browser form POSTs _method=PATCH. Only honoured on POST,
        // so a GET can never be escalated into a mutating verb via the query string.
        if ($this->method === 'POST' && isset($post['_method'])) {
            $spoofed = strtoupper((string) $post['_method']);

            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                $this->method = $spoofed;
            }
        }

        $uri        = (string) ($server['REQUEST_URI'] ?? '/');
        $this->path = '/' . trim((string) parse_url($uri, PHP_URL_PATH), '/');

        if ($rawBody !== '' && str_contains((string) ($server['CONTENT_TYPE'] ?? ''), 'application/json')) {
            $decoded = json_decode($rawBody, true);

            if (is_array($decoded)) {
                $this->json = $decoded;
            }
        }
    }

    public static function capture(): self
    {
        return new self($_GET, $_POST, $_SERVER, $_FILES, (string) file_get_contents('php://input'));
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * Merged input: JSON body, then POST, then query string.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->json[$key] ?? $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    /**
     * @return array<string,mixed>
     */
    public function json(): array
    {
        return $this->json;
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        $all    = $this->all();
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $all)) {
                $result[$key] = $all[$key];
            }
        }

        return $result;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $file;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }

        // CONTENT_TYPE and CONTENT_LENGTH arrive without the HTTP_ prefix.
        $bare = strtoupper(str_replace('-', '_', $name));

        return isset($this->server[$bare]) ? (string) $this->server[$bare] : null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');

        if ($header === null || !preg_match('/^Bearer\s+(\S+)$/i', $header, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * Deliberately REMOTE_ADDR only.
     *
     * X-Forwarded-For is attacker-controlled unless every hop in front of the app is
     * trusted and strips it. Honouring it here would let anyone bypass login and API
     * rate limiting by rotating a header value.
     */
    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * Rate limiting and submission logs store this rather than the raw address,
     * so the database never holds directly identifying network data.
     */
    public function ipHash(): string
    {
        return hash_hmac('sha256', $this->ip(), (string) config('app.key'));
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function referer(): string
    {
        return substr((string) ($this->server['HTTP_REFERER'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        if (($this->server['HTTPS'] ?? '') !== '' && $this->server['HTTPS'] !== 'off') {
            return true;
        }

        // Hostinger terminates TLS at the proxy and forwards this.
        return ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');

        return str_contains($accept, 'application/json')
            || str_starts_with($this->path, '/api/');
    }

    /**
     * @param array<string,string> $params
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * The API token row, set by AuthenticateApiToken once the bearer token
     * resolves. Controllers read it to check abilities.
     *
     * @var array<string,mixed>|null
     */
    private ?array $apiToken = null;

    /**
     * @param array<string,mixed> $token
     */
    public function setApiToken(array $token): void
    {
        $this->apiToken = $token;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function apiToken(): ?array
    {
        return $this->apiToken;
    }

    public function param(string $key, ?string $default = null): ?string
    {
        return $this->params[$key] ?? $default;
    }

    public function paramInt(string $key): int
    {
        return (int) ($this->params[$key] ?? 0);
    }
}
