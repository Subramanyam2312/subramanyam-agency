<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Plain-PHP templates with layouts, sections and partials.
 *
 * Templates are included from inside an instance method, which is what makes
 * $this available to them:
 *
 *     <?php $this->extend('layouts/admin'); ?>
 *     <?php $this->start('scripts'); ?> ... <?php $this->stop(); ?>
 *
 * and in the layout:
 *
 *     <?= $this->yieldSection('content') ?>
 */
final class View
{
    /** @var array<string,mixed> Data available to every template. */
    private static array $shared = [];

    private string $layout = '';

    /** @var array<string,string> */
    private array $sections = [];

    /** @var array<int,string> */
    private array $sectionStack = [];

    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        return (new self())->run($view, $data);
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /**
     * Convenience: render straight into an HTML response.
     *
     * @param array<string,mixed> $data
     */
    public static function response(string $view, array $data = [], int $status = 200): Response
    {
        return Response::html(self::render($view, $data), $status);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function run(string $view, array $data): string
    {
        $data   = array_merge(self::$shared, $data);
        $output = $this->renderFile($view, $data);

        /*
         * A template supplies its body either by wrapping it in a 'content' section
         * or by just echoing it. The explicit section wins — overwriting it with the
         * raw output would discard everything a section-using template produced.
         */
        if (!array_key_exists('content', $this->sections)) {
            $this->sections['content'] = $output;
        }

        // A layout may itself extend another layout, hence the loop.
        while ($this->layout !== '') {
            $layout       = $this->layout;
            $this->layout = '';

            $output = $this->renderFile($layout, $data);

            // Whatever the layout produced becomes the body of the next one out.
            $this->sections['content'] = $output;
        }

        return $output;
    }

    public function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function stop(): void
    {
        if ($this->sectionStack === []) {
            throw new RuntimeException('View::stop() called without a matching start().');
        }

        $name = array_pop($this->sectionStack);

        $this->sections[$name] = (string) ob_get_clean();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    /**
     * Render a partial inline. Partials get the shared data plus whatever is passed.
     *
     * @param array<string,mixed> $data
     */
    public function include(string $view, array $data = []): string
    {
        return $this->renderFile($view, array_merge(self::$shared, $data));
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderFile(string $view, array $data): string
    {
        $file = VIEW_PATH . '/' . str_replace('..', '', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);

        $level = ob_get_level();

        ob_start();

        try {
            include $file;
        } catch (Throwable $e) {
            // Unwind any buffers the failed template opened, or the error page
            // gets rendered inside half a layout.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }

        return (string) ob_get_clean();
    }
}
