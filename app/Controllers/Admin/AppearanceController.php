<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Fonts;
use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Setting;

/**
 * Settings -> Appearance. Chooses the site's type pairing.
 *
 * The curated pairings are self-hosted and are the recommended path. Google Fonts
 * is offered because it opens the whole library, but it is opt-in and the screen
 * says plainly what it costs: a third-party request on every page load and a
 * wider content-security policy.
 */
final class AppearanceController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/appearance/index', [
            'pairings' => (array) config('fonts.pairings', []),
            'current'  => Fonts::pairing(),
            'source'   => (string) Setting::get('fonts_source', 'self'),
            'google'   => [
                'display' => (string) Setting::get('font_google_display', ''),
                'body'    => (string) Setting::get('font_google_body', ''),
            ],
            'usesGoogle' => Fonts::usesGoogle(),
        ]);
    }

    public function update(Request $request): Response
    {
        $pairings = (array) config('fonts.pairings', []);
        $pairing  = (string) $request->input('font_pairing', '');
        $source   = $request->input('fonts_source') === 'google' ? 'google' : 'self';

        if (!isset($pairings[$pairing])) {
            $pairing = (string) config('fonts.default', 'instrument');
        }

        Setting::set('font_pairing', $pairing, 'text', 'appearance');
        Setting::set('fonts_source', $source, 'text', 'appearance');
        Setting::set('font_google_display', Sanitizer::plain((string) $request->input('font_google_display', '')), 'text', 'appearance');
        Setting::set('font_google_body', Sanitizer::plain((string) $request->input('font_google_body', '')), 'text', 'appearance');

        Setting::flushCache();

        // Typography is baked into every cached page, so the cache has to go.
        PageCache::purge();

        ActivityLogger::log('appearance.updated', 'settings', null, [
            'pairing' => $pairing,
            'source'  => $source,
        ]);

        $this->success('Typography updated.');

        return $this->redirect('/admin/appearance');
    }
}
