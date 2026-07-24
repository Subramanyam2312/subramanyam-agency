<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Setting;

/**
 * Site settings.
 *
 * SMTP credentials are deliberately absent: they live in .env, because a database
 * row containing a mail password is one SQL injection or one careless export away
 * from being someone else's. The same goes for the database credentials.
 */
final class SettingController extends Controller
{
    /** Ordered groups and the human labels for the tabs. */
    private const GROUPS = [
        'general' => 'General',
        'contact' => 'Contact',
        'social'  => 'Social',
        'seo'     => 'SEO and analytics',
    ];

    /** Keys that should render as a textarea rather than a single line. */
    private const MULTILINE = [
        'footer_copy', 'address', 'seo_default_description', 'robots_extra',
    ];

    public function edit(Request $request): Response
    {
        $group = (string) $request->param('group', 'general');

        if (!isset(self::GROUPS[$group])) {
            $group = 'general';
        }

        return $this->view('admin/settings/form', [
            'groups'    => self::GROUPS,
            'group'     => $group,
            'settings'  => Setting::group($group),
            'multiline' => self::MULTILINE,
        ]);
    }

    public function update(Request $request): Response
    {
        $group = (string) $request->param('group', 'general');

        if (!isset(self::GROUPS[$group])) {
            $group = 'general';
        }

        $submitted = $request->input('settings', []);

        if (!is_array($submitted)) {
            $this->error('Nothing was submitted.');

            return $this->redirect('/admin/settings/' . $group);
        }

        // Only keys that already exist in this group are writable, so a crafted POST
        // cannot invent new settings or overwrite another group's values.
        $allowed = array_column(Setting::group($group), 'setting_key');
        $saved   = 0;

        foreach ($submitted as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }

            Setting::set($key, Sanitizer::plain((string) $value), 'text', $group);
            $saved++;
        }

        Setting::flushCache();

        ActivityLogger::log('settings.updated', 'settings', null, ['group' => $group, 'keys' => $saved]);
        $this->success(self::GROUPS[$group] . ' settings saved.');

        return $this->redirect('/admin/settings/' . $group);
    }
}
