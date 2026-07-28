<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A response is built up and returned by controllers, then sent once by the
 * front controller. Nothing echoes directly, so middleware can still add headers
 * after a controller has run.
 */
final class Response
{
    private string $body = '';

    private int $status = 200;

    /** @var array<string,string> */
    private array $headers = [];

    /** @var array<int,array<string,mixed>> */
    private array $cookies = [];

    public static function make(string $body = '', int $status = 200): self
    {
        $response = new self();
        $response->body   = $body;
        $response->status = $status;

        return $response;
    }

    public static function html(string $body, int $status = 200): self
    {
        return self::make($body, $status)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function json(array $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';

        return self::make($body, $status)->header('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return self::make('', $status)->header('Location', $to);
    }

    /**
     * Redirect back to the submitting page, re-populating the form.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed>  $input
     */
    public static function back(Request $request, array $errors = [], array $input = []): self
    {
        if ($errors !== []) {
            Session::flashErrors($errors);
        }

        if ($input !== []) {
            Session::flashInput($input);
        }

        $referer = $request->referer();
        $target  = $referer !== '' ? $referer : '/';

        // Only ever bounce back to our own host — an open redirect here would be
        // handed straight to phishing campaigns.
        $host = parse_url($target, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            $target = '/';
        }

        return self::redirect($target);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function getHeader(string $name): ?string
    {
        // Case-insensitive lookup, since header names are compared loosely.
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function status(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function cookie(string $name, string $value, int $expires, bool $secure): self
    {
        $this->cookies[] = [
            'name'    => $name,
            'value'   => $value,
            'options' => [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ],
        ];

        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }

        echo $this->body;
    }
}
