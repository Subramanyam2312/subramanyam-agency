<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sitemap;

final class SeoController extends Controller
{
    /**
     * Fallback for sitemap.xml.
     *
     * Apache serves the generated file directly when it exists; this route only
     * runs when it does not, which happens on a fresh deploy before the first
     * publish. Generating on the fly means a crawler never sees a 404, and the
     * file is written as a side effect so the next request is served statically.
     */
    public function sitemap(Request $request): Response
    {
        $xml = Sitemap::build();

        Sitemap::generate();

        return Response::make($xml)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * robots.txt is served dynamically rather than written to disk, because it
     * depends on the maintenance-mode toggle — a cached file would keep inviting
     * crawlers in after the site had been closed.
     */
    public function robots(Request $request): Response
    {
        return Response::make(Sitemap::robots())
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
