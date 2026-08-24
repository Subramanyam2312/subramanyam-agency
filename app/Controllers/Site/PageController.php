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
                'title'       => 'Digital Marketing Services in Chennai',
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
                'title'       => 'Case Studies — SEO & Paid Media Results',
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

        // File-based cover doubles as the social share image when present.
        $coverRel = '/uploads/work/' . $case['slug'] . '.jpg';
        $ogImage  = is_file(PUBLIC_PATH . $coverRel)
            ? rtrim((string) config('app.url'), '/') . $coverRel
            : null;

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
                'og_image'    => $ogImage,
                'noindex'     => (bool) $case['noindex'],
            ],
        ]);
    }

    // ---------------------------------------------------------------- about

    public function about(Request $request): Response
    {
        $ogImage = is_file(PUBLIC_PATH . '/uploads/founder/founder.jpg')
            ? rtrim((string) config('app.url'), '/') . '/uploads/founder/founder.jpg'
            : null;

        return $this->view('site/about', [
            // Timeline section removed from the About page; no longer queried here.
            'logos'    => ClientLogo::withMedia(true),
            'meta'     => [
                /*
                 * This page owns the person, not the service — the homepage owns that.
                 *
                 * Both used to say the same thing. The description here repeated the
                 * homepage's "brand strategy, ad creative, SEO and AI-assisted video"
                 * almost verbatim, and the title carried "Digital Marketing, Chennai"
                 * like /services and /contact did, so Google had four interchangeable
                 * candidates for one query and split impressions between them: about
                 * drew 15 in the 28 days to 21 Aug 2026 and the homepage only five,
                 * neither converting. "who is subramanyam" was one of just two queries
                 * Search Console would disclose in that window, which is the intent
                 * this page should actually answer, so the title leads with the name.
                 *
                 * What is left is what the homepage cannot claim: one person doing both
                 * halves of the job, the Tamil and South Indian market work, and the
                 * named clients. All of it is on the page itself.
                 *
                 * Both fields read from page blocks rather than string literals here.
                 * The description in particular names clients on the live site, and
                 * this repository is public — customer names belong in the database,
                 * which is not published. The defaults below are the fallback for an
                 * install that has not set them, so they stay generic.
                 */
                'title'       => PageBlock::value('about', 'meta_title', 'About Subramanyam M N'),
                'description' => PageBlock::value(
                    'about',
                    'meta_description',
                    'Who I am and how I work: one person doing the strategy and the making, '
                    . 'in Chennai and across Tamil-speaking markets.'
                ),
                'og_image'    => $ogImage,
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
