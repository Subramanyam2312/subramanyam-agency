<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaseStudy;
use App\Models\ClientLogo;
use App\Models\Faq;
use App\Models\PageBlock;
use App\Models\Service;
use App\Models\TimelineEntry;

/**
 * The pages that are essentially "render this content": services, work, about,
 * FAQ and the legal pages. Grouped rather than split into a controller each,
 * because each one is a query and a view and nothing more.
 */
final class PageController extends Controller
{
    // ------------------------------------------------------------- services

    public function services(Request $request): Response
    {
        return $this->view('site/services/index', [
            'services' => Service::all(['is_active' => 1], 'sort_order ASC, title ASC'),
            'faqs'     => Faq::grouped(),
            'meta'     => [
                'title'       => 'Services',
                'description' => 'SEO, paid media, content, web build and analytics — measured against pipeline rather than impressions.',
            ],
        ]);
    }

    public function service(Request $request): Response
    {
        $service = Service::first([
            'slug'      => (string) $request->param('slug'),
            'is_active' => 1,
        ]);

        if ($service === null) {
            throw new HttpException(404, 'That service does not exist.');
        }

        return $this->view('site/services/show', [
            'service' => $service,
            'faqs'    => Service::faqs((int) $service['id']),
            'related' => Service::all(
                ['is_active' => 1, 'id !=' => $service['id']],
                'sort_order ASC',
                3
            ),
            'cases'   => CaseStudy::all(
                ['status' => CaseStudy::STATUS_PUBLISHED, 'service_id' => $service['id']],
                'sort_order ASC',
                2
            ),
            'meta'    => [
                'title'       => $service['meta_title'] ?: $service['title'],
                'description' => $service['meta_description'] ?: $service['short_description'],
                'canonical'   => $service['canonical_url'] ?: url('/services/' . $service['slug']),
                'noindex'     => (bool) $service['noindex'],
            ],
        ]);
    }

    // ----------------------------------------------------------------- work

    public function work(Request $request): Response
    {
        return $this->view('site/work/index', [
            'cases' => CaseStudy::all(
                ['status' => CaseStudy::STATUS_PUBLISHED],
                'is_featured DESC, sort_order ASC'
            ),
            'meta'  => [
                'title'       => 'Work',
                'description' => 'Selected engagements where the numbers moved enough to be worth writing up.',
            ],
        ]);
    }

    public function caseStudy(Request $request): Response
    {
        $case = CaseStudy::first([
            'slug'   => (string) $request->param('slug'),
            'status' => CaseStudy::STATUS_PUBLISHED,
        ]);

        if ($case === null) {
            throw new HttpException(404, 'That case study does not exist.');
        }

        return $this->view('site/work/show', [
            'case'    => $case,
            'service' => $case['service_id'] ? Service::find((int) $case['service_id']) : null,
            'more'    => CaseStudy::all(
                ['status' => CaseStudy::STATUS_PUBLISHED, 'id !=' => $case['id']],
                'sort_order ASC',
                2
            ),
            'meta'    => [
                'title'       => $case['meta_title'] ?: $case['title'],
                'description' => $case['meta_description'] ?: $case['challenge'],
                'noindex'     => (bool) $case['noindex'],
            ],
        ]);
    }

    // ---------------------------------------------------------------- about

    public function about(Request $request): Response
    {
        return $this->view('site/about', [
            'timeline' => TimelineEntry::all(['is_active' => 1], 'sort_order ASC, id ASC'),
            'logos'    => ClientLogo::withMedia(true),
            'meta'     => [
                'title'       => 'About',
                'description' => PageBlock::value('about', 'story_heading', 'About the studio'),
            ],
        ]);
    }

    // ------------------------------------------------------------------ FAQ

    public function faq(Request $request): Response
    {
        return $this->view('site/faq', [
            'groups' => Faq::grouped(),
            'meta'   => [
                'title'       => 'Frequently asked questions',
                'description' => 'How engagements start, how reporting works, and what things cost.',
            ],
        ]);
    }

    // ---------------------------------------------------------------- legal

    /**
     * Privacy and terms, both stored as editable page_blocks so they can be
     * changed without a deploy.
     */
    public function legal(Request $request): Response
    {
        $page = (string) $request->param('page');

        if (!in_array($page, ['privacy', 'terms'], true)) {
            throw new HttpException(404);
        }

        $body = PageBlock::value($page, 'body');

        if ($body === '') {
            throw new HttpException(404, 'That page has not been written yet.');
        }

        return $this->view('site/legal', [
            'heading' => PageBlock::value($page, 'heading', ucfirst($page)),
            'updated' => PageBlock::value($page, 'updated'),
            'body'    => $body,
            'meta'    => [
                'title'       => PageBlock::value($page, 'heading', ucfirst($page)),
                'description' => 'Legal information for ' . config('app.name') . '.',
            ],
        ]);
    }
}
