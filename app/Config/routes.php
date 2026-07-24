<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CaseStudyController;
use App\Controllers\Admin\CategoryController;
use App\Controllers\Admin\ClientLogoController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\FaqController;
use App\Controllers\Admin\MediaController;
use App\Controllers\Admin\PageBlockController;
use App\Controllers\Admin\PostController;
use App\Controllers\Admin\ServiceController;
use App\Controllers\Admin\SettingController;
use App\Controllers\Admin\SubmissionController;
use App\Controllers\Admin\SubscriberController;
use App\Controllers\Admin\TestimonialController;
use App\Controllers\Admin\TimelineController;
use App\Controllers\Admin\UserController;
use App\Core\Router;
use App\Middleware\RequireAdmin;
use App\Middleware\RequireAuth;

/**
 * Every URL the application answers, in one file, read top to bottom.
 *
 * Public site routes arrive in Phase 5 and the REST API in Phase 4; until then the
 * site root correctly returns a 404 rather than a placeholder page.
 */
return function (Router $router): void {

    /**
     * The seven routes every content module needs. Declaring them by hand eleven
     * times would be pure noise — and a missed one would be a silent 404.
     */
    $resource = static function (Router $router, string $path, string $controller, bool $sortable = false): void {
        $router->get($path, [$controller, 'index']);
        $router->get($path . '/create', [$controller, 'create']);
        $router->post($path, [$controller, 'store']);
        $router->get($path . '/{id:\d+}/edit', [$controller, 'edit']);
        $router->patch($path . '/{id:\d+}', [$controller, 'update']);
        $router->delete($path . '/{id:\d+}', [$controller, 'destroy']);

        if ($sortable) {
            $router->post($path . '/reorder', [$controller, 'reorder']);
        }
    };

    $router->group(['prefix' => '/admin'], function (Router $router) use ($resource): void {

        // ---- Guest ----------------------------------------------------------
        // Outside RequireAuth: gating the login form behind the login check is a
        // classic redirect loop.
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);

        $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
        $router->post('/forgot-password', [AuthController::class, 'sendResetLink']);

        $router->get('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'showResetPassword']);
        $router->post('/reset-password', [AuthController::class, 'resetPassword']);

        // ---- Authenticated: content -----------------------------------------
        $router->group(['middleware' => [RequireAuth::class]], function (Router $router) use ($resource): void {
            $router->post('/logout', [AuthController::class, 'logout']);
            $router->get('', [DashboardController::class, 'index']);

            $resource($router, '/posts', PostController::class);
            $resource($router, '/categories', CategoryController::class);
            $resource($router, '/services', ServiceController::class, true);
            $resource($router, '/faqs', FaqController::class, true);
            $resource($router, '/testimonials', TestimonialController::class, true);
            $resource($router, '/case-studies', CaseStudyController::class, true);
            $resource($router, '/timeline', TimelineController::class, true);
            $resource($router, '/client-logos', ClientLogoController::class, true);

            // Page copy: values only. Blocks are defined by the templates that use
            // them, so there is no create or delete.
            $router->get('/page-content', [PageBlockController::class, 'index']);
            $router->get('/page-content/{page:[a-z0-9-]+}', [PageBlockController::class, 'edit']);
            $router->patch('/page-content/{page:[a-z0-9-]+}', [PageBlockController::class, 'update']);

            // Media library.
            $router->get('/media', [MediaController::class, 'index']);
            $router->post('/media', [MediaController::class, 'store']);
            $router->get('/media/picker', [MediaController::class, 'picker']);
            $router->patch('/media/{id:\d+}', [MediaController::class, 'update']);
            $router->delete('/media/{id:\d+}', [MediaController::class, 'destroy']);

            // Enquiries: read-only inbox plus state changes.
            $router->get('/submissions', [SubmissionController::class, 'index']);
            $router->get('/submissions/export', [SubmissionController::class, 'export']);
            $router->get('/submissions/{id:\d+}', [SubmissionController::class, 'show']);
            $router->patch('/submissions/{id:\d+}/read', [SubmissionController::class, 'toggleRead']);
            $router->patch('/submissions/{id:\d+}/spam', [SubmissionController::class, 'toggleSpam']);
            $router->delete('/submissions/{id:\d+}', [SubmissionController::class, 'destroy']);

            $router->get('/subscribers', [SubscriberController::class, 'index']);
            $router->get('/subscribers/export', [SubscriberController::class, 'export']);
            $router->delete('/subscribers/{id:\d+}', [SubscriberController::class, 'destroy']);

            // ---- Administrators only ----------------------------------------
            $router->group(['middleware' => [RequireAdmin::class]], function (Router $router) use ($resource): void {
                $router->get('/settings', [SettingController::class, 'edit']);
                $router->get('/settings/{group:[a-z]+}', [SettingController::class, 'edit']);
                $router->patch('/settings/{group:[a-z]+}', [SettingController::class, 'update']);

                $resource($router, '/users', UserController::class);
            });
        });
    });
};
