<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Core\Router;
use App\Middleware\RequireAuth;

/**
 * Every URL the application answers, in one file, read top to bottom.
 *
 * Phase 2 covers the admin shell only. Public site routes arrive in Phase 5 and the
 * REST API in Phase 4; until then the site root correctly returns a 404 rather than
 * a placeholder page.
 */
return function (Router $router): void {
    $router->group(['prefix' => '/admin'], function (Router $router): void {

        // ---- Guest ----------------------------------------------------------
        // Deliberately outside RequireAuth: locking the login form behind the
        // login check is a classic redirect loop.
        $router->get('/login', [AuthController::class, 'showLogin']);
        $router->post('/login', [AuthController::class, 'login']);

        $router->get('/forgot-password', [AuthController::class, 'showForgotPassword']);
        $router->post('/forgot-password', [AuthController::class, 'sendResetLink']);

        $router->get('/reset-password/{token:[a-f0-9]{64}}', [AuthController::class, 'showResetPassword']);
        $router->post('/reset-password', [AuthController::class, 'resetPassword']);

        // ---- Authenticated --------------------------------------------------
        $router->group(['middleware' => [RequireAuth::class]], function (Router $router): void {
            $router->post('/logout', [AuthController::class, 'logout']);

            $router->get('', [DashboardController::class, 'index']);
        });
    });
};
