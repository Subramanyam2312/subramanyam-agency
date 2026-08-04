<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Branding;
use App\Core\Fonts;
use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Media;
use App\Models\Setting;

/**
 * Settings -> Appearance. The site's type pairing, logo and browser icon.
 *
 * The curated pairings are self-hosted and are the recommended path. Google Fonts
 * is offered because it opens the whole library, but it is opt-in and the screen
 * says plainly what it costs: a third-party request on every page load and a
 * wider content-security policy.
 *
 * Logo and icon are media library IDs. Both are optional, and both have a defined
 * empty state rather than a broken one — see Branding.
 */
final class AppearanceController extends Controller
{
    public function index(Request $request): Response
    {
        $logoId = (string) Setting::get('site_logo_media_id', '');
        $iconId = (string) Setting::get('site_icon_media_id', '');

        return $this->view('admin/appearance/index', [
            'pairings' => (array) config('fonts.pairings', []),
            'current'  => Fonts::pairing(),
            'source'   => (string) Setting::get('fonts_source', 'self'),
            'google'   => [
                'display' => (string) Setting::get('font_google_display', ''),
                'body'    => (string) Setting::get('font_google_body', ''),
            ],
            'usesGoogle' => Fonts::usesGoogle(),

            'logoId'    => $logoId,
            'logoMedia' => $logoId === '' ? null : Media::find((int) $logoId),
            'iconId'    => $iconId,
            'iconMedia' => $iconId === '' ? null : Media::find((int) $iconId),
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

        $logoId = $this->mediaId($request->input('site_logo_media_id'));
        $iconId = $this->mediaId($request->input('site_icon_media_id'));

        Setting::set('site_logo_media_id', $logoId, 'text', 'appearance');
        Setting::set('site_icon_media_id', $iconId, 'text', 'appearance');

        Setting::flushCache();
        Branding::flush();

        // Typography and branding are baked into every cached page, so the cache has to go.
        PageCache::purge();

        ActivityLogger::log('appearance.updated', 'settings', null, [
            'pairing' => $pairing,
            'source'  => $source,
            'logo'    => $logoId,
            'icon'    => $iconId,
        ]);

        $this->success('Appearance updated.');

        return $this->redirect('/admin/appearance');
    }

    /**
     * Normalises a media picker value to a storable ID.
     *
     * The picker posts either an ID or an empty string for "no image". Anything
     * that is not a positive integer becomes '', so a hand-crafted POST cannot
     * store a path, a zero, or a negative row reference — the branding is read on
     * every page of the site, which makes it a poor place to trust input.
     */
    private function mediaId(mixed $value): string
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id === false || $id < 1 || Media::find($id) === null) {
            return '';
        }

        return (string) $id;
    }
}
