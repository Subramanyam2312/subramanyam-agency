<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class Controller
{
    /**
     * @param array<string,mixed> $data
     */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return View::response($template, $data, $status);
    }

    protected function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    /**
     * Redirect to a fixed path, re-populating the form and showing why it failed.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed>  $input
     */
    protected function redirectWithErrors(string $to, array $errors, array $input = []): Response
    {
        Session::flashErrors($errors);

        if ($input !== []) {
            Session::flashInput($input);
        }

        return Response::redirect($to);
    }

    protected function success(string $message): void
    {
        Session::flash('success', $message);
    }

    protected function error(string $message): void
    {
        Session::flash('error', $message);
    }

    /**
     * Only allows redirects to a path on this site. Anything else — a full URL, a
     * protocol-relative //evil.com, a backslash trick — falls back to $fallback.
     */
    protected function safeInternalPath(?string $path, string $fallback = '/admin'): string
    {
        if (!is_string($path) || $path === '') {
            return $fallback;
        }

        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return $fallback;
        }

        return $path;
    }

    /**
     * @return array<string,mixed>
     */
    protected function inputFor(Request $request, array $keys): array
    {
        return $request->only($keys);
    }
}
