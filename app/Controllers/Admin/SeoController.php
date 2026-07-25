<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Api;
use App\Core\Request;
use App\Core\Response;
use App\Core\SeoAnalyzer;

/**
 * Live SEO analysis for the editor.
 *
 * The post form posts its current field values here as the author types (debounced)
 * and renders the returned score and checklist. It is read-only — it computes and
 * returns, it never writes — so there is nothing to guard beyond the admin session
 * that already gates every route in this group. CSRF still applies (the editor
 * sends the token header), because it is a POST.
 */
final class SeoController extends Controller
{
    public function analyze(Request $request): Response
    {
        $result = SeoAnalyzer::analyze([
            'focus_keyword'    => (string) $request->input('focus_keyword', ''),
            'title'            => (string) $request->input('title', ''),
            'meta_title'       => (string) $request->input('meta_title', ''),
            'excerpt'          => (string) $request->input('excerpt', ''),
            'meta_description' => (string) $request->input('meta_description', ''),
            'slug'             => (string) $request->input('slug', ''),
            'content'          => (string) $request->input('content', ''),
        ]);

        return Response::json($result);
    }
}
