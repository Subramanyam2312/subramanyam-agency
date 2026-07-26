<?php

declare(strict_types=1);

use App\Controllers\Admin\ApiTokenController;
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
use App\Controllers\Api\V1\MediaController as ApiMediaController;
use App\Controllers\Site\BlogController;
use App\Controllers\Site\ContactController;
use App\Controllers\Site\HomeController;
use App\Controllers\Site\NewsletterController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\SeoController;
use App\Controllers\Api\V1\PostController as ApiPostController;
use App\Controllers\Api\V1\TaxonomyController as ApiTaxonomyController;
use App\Core\Router;
use App\Middleware\AuthenticateApiToken;
use App\Middleware\RequireAdmin;
use App\Middleware\RequireAuth;

/**
 * Every URL the application answers, in one file, read top to bottom.
 *
 * Public site routes arrive in Phase 5 and the REST API in Phase 4; until then the
 * site root correctly returns a 404 rather than a placeholder page.
 */
return function (Router $router): void {

    // ---- Public site --------------------------------------------------------
    $router->get('/', [HomeController::class, 'index']);

    $router->get('/services', [PageController::class, 'services']);
    $router->get('/services/{slug:[a-z0-9-]+}', [PageController::class, 'service']);

    $router->get('/work', [PageController::class, 'work']);
    $router->get('/work/{slug:[a-z0-9-]+}', [PageController::class, 'caseStudy']);

    $router->get('/about', [PageController::class, 'about']);
    $router->get('/faq', [PageController::class, 'faq']);

    /*
     * Blog. The category route is declared BEFORE the post route: '/blog/category'
     * would otherwise be swallowed by '/blog/{slug}' and 404 as a missing post,
     * since the router takes the first pattern that matches.
     */
    $router->get('/blog', [BlogController::class, 'index']);
    $router->get('/blog/category/{slug:[a-z0-9-]+}', [BlogController::class, 'index']);
    $router->get('/blog/{slug:[a-z0-9-]+}', [BlogController::class, 'show']);
    $router->get('/feed.xml', [BlogController::class, 'feed']);

    $router->get('/contact', [ContactController::class, 'show']);
    $router->post('/contact', [ContactController::class, 'submit']);

    $router->post('/newsletter', [NewsletterController::class, 'subscribe']);

    // Privacy and terms, both stored as editable page copy.
    $router->get('/{page:privacy|terms}', [PageController::class, 'legal']);

    /*
     * Apache serves the generated sitemap.xml directly when the file exists, so
     * this route only runs on a fresh deploy before the first publish. robots.txt
     * is always dynamic because it depends on the maintenance-mode toggle.
     */
    $router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
    $router->get('/robots.txt', [SeoController::class, 'robots']);


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

            // Live RankMath-style SEO analysis for the editor (read-only, JSON).
            $router->post('/seo/analyze', [\App\Controllers\Admin\SeoController::class, 'analyze']);

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

                // Tools -> Security (firewall).
                $router->get('/security', [\App\Controllers\Admin\SecurityController::class, 'index']);
                $router->patch('/security/settings', [\App\Controllers\Admin\SecurityController::class, 'updateSettings']);
                $router->post('/security/block', [\App\Controllers\Admin\SecurityController::class, 'block']);
                $router->delete('/security/block/{id:\d+}', [\App\Controllers\Admin\SecurityController::class, 'unblock']);

                $resource($router, '/users', UserController::class);

                $router->get('/api-tokens', [ApiTokenController::class, 'index']);
                $router->post('/api-tokens', [ApiTokenController::class, 'store']);
                $router->delete('/api-tokens/{id:\d+}', [ApiTokenController::class, 'destroy']);
            });
        });
    });

    /**
     * REST API v1.
     *
     * Bearer-token authenticated, so it is exempt from CSRF — there is no cookie
     * for a cross-site request to borrow. Every route sits behind
     * AuthenticateApiToken, which also applies the per-token rate limit.
     *
     * No DELETE anywhere: an automated client that can create and update can
     * unpublish by patching status, and a loop in someone's script should not be
     * able to destroy content.
     */
    $router->group([
        'prefix'     => '/api/v1',
        'middleware' => [AuthenticateApiToken::class],
    ], function (Router $router): void {
        $router->get('/me', [ApiTaxonomyController::class, 'me']);

        $router->get('/posts', [ApiPostController::class, 'index']);
        $router->post('/posts', [ApiPostController::class, 'store']);
        $router->get('/posts/{id:\d+}', [ApiPostController::class, 'show']);
        $router->patch('/posts/{id:\d+}', [ApiPostController::class, 'update']);

        $router->get('/media', [ApiMediaController::class, 'index']);
        $router->post('/media', [ApiMediaController::class, 'store']);
        $router->patch('/media/{id:\d+}', [ApiMediaController::class, 'update']);

        $router->get('/categories', [ApiTaxonomyController::class, 'categories']);
        $router->get('/tags', [ApiTaxonomyController::class, 'tags']);
    });
};
