<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaseStudy;
use App\Models\ClientLogo;
use App\Models\PageBlock;
use App\Models\Post;
use App\Models\Service;
use App\Models\Testimonial;

final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        /*
         * Resolve any post whose scheduled time has passed before reading the list.
         *
         * Cron does this every five minutes, but shared-hosting cron fails quietly
         * and often. Doing it here as well means a post is never stuck showing as
         * scheduled on a page that is being served right now.
         */
        Post::publishDue();

        return $this->view('site/home', [
            'services'     => Service::all(['is_active' => 1], 'sort_order ASC, title ASC', 6),
            'caseStudies'  => CaseStudy::all(
                ['status' => CaseStudy::STATUS_PUBLISHED],
                'is_featured DESC, sort_order ASC',
                3
            ),
            'testimonials' => Testimonial::all(['is_active' => 1], 'is_featured DESC, sort_order ASC', 6),
            'logos'        => ClientLogo::withMedia(true),
            'posts'        => $this->latestPosts(),
            'meta'         => [
                'title'       => PageBlock::value('home', 'hero_headline', (string) config('app.name')),
                'description' => PageBlock::value('home', 'hero_subheadline'),
            ],
        ]);
    }

    /**
     * Latest three published posts with their category name.
     *
     * @return array<int,array<string,mixed>>
     */
    private function latestPosts(): array
    {
        return Database::select(
            "SELECT p.id, p.title, p.slug, p.excerpt, p.published_at, p.reading_time,
                    c.name AS category_name, c.slug AS category_slug
             FROM `posts` p
             LEFT JOIN `categories` c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND p.status = 'published'
               AND p.published_at <= NOW()
             ORDER BY p.published_at DESC
             LIMIT 3"
        );
    }
}
