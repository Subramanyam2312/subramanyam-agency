<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\PageCache;
use App\Core\SpamGuard;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactSubmission;
use App\Models\Setting;

/**
 * Tools -> Plugins. Admin-only (enforced on the routes).
 *
 * A single hub for the site's optional capabilities: SEO, analytics, traffic,
 * spam protection and page caching. Because this is a real server-backed app,
 * these are working features, not just toggles — the difference from a static-site
 * plugins panel that can only offer to inject third-party snippets.
 */
final class PluginController extends Controller
{
    /**
     * Settings this screen owns. Booleans are stored as '0'/'1'; the rest as text.
     *
     * @var array<int,string>
     */
    private const BOOLEANS = [
        'plugin_seo_enabled', 'plugin_analytics_enabled', 'meta_pixel_enabled',
        'custom_head_enabled', 'custom_body_enabled', 'plugin_traffic_enabled',
        'plugin_spam_enabled', 'plugin_cache_enabled',
    ];

    /** @var array<int,string> */
    private const TEXT = [
        'ga_measurement_id', 'gtm_id', 'meta_pixel_id',
        'custom_head_code', 'custom_body_code', 'akismet_key',
        'spam_max_links', 'cache_ttl',
    ];

    public function index(Request $request): Response
    {
        $akismetKey = trim((string) Setting::get('akismet_key', ''));

        return $this->view('admin/plugins/index', [
            's'            => Setting::all(),
            'cacheFiles'   => PageCache::size(),
            'spamBlocked'  => $this->spamBlockedCount(),
            'akismetSet'   => $akismetKey !== '',
        ]);
    }

    public function update(Request $request): Response
    {
        foreach (self::BOOLEANS as $key) {
            Setting::set($key, $request->input($key) ? '1' : '0', 'boolean', 'plugins');
        }

        foreach (self::TEXT as $key) {
            $value = (string) $request->input($key, '');

            // Custom head/body code is a raw injection point and must NOT be
            // stripped; the analytics IDs are trimmed; everything else trimmed too.
            if (!in_array($key, ['custom_head_code', 'custom_body_code'], true)) {
                $value = trim($value);
            }

            // These two live in the 'seo' group historically; keep them there.
            $group = in_array($key, ['ga_measurement_id', 'gtm_id'], true) ? 'seo' : 'plugins';

            Setting::set($key, $value, 'text', $group);
        }

        Setting::flushCache();

        // Turning caching on/off, or changing what analytics injects, means any
        // cached HTML is now stale — clear it.
        PageCache::purge();

        ActivityLogger::log('plugins.updated', 'settings', null);
        $this->success('Plugin settings saved.');

        return $this->redirect('/admin/plugins');
    }

    public function purgeCache(Request $request): Response
    {
        $removed = PageCache::purge();

        ActivityLogger::log('plugins.cache_purged', null, null, ['files' => $removed]);
        $this->success($removed > 0 ? "Cache purged ({$removed} pages)." : 'Cache was already empty.');

        return $this->redirect('/admin/plugins');
    }

    public function verifyAkismet(Request $request): Response
    {
        $key   = trim((string) Setting::get('akismet_key', ''));
        $valid = SpamGuard::verifyKey($key);

        if ($valid) {
            $this->success('Akismet key is valid and active.');
        } else {
            $this->error($key === '' ? 'Enter and save an Akismet key first.' : 'Akismet rejected that key.');
        }

        return $this->redirect('/admin/plugins');
    }

    private function spamBlockedCount(): int
    {
        return ContactSubmission::count(['is_spam' => 1]);
    }
}
