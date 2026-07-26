<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Firewall;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Setting;

/**
 * Tools -> Security. Admin-only (enforced on the routes).
 *
 * Everything destructive here has a self-lockout guard: you cannot block the
 * address you are browsing from, and the panel shows that address so it is obvious
 * why. The env allowlist is the harder guarantee behind that.
 */
final class SecurityController extends Controller
{
    private const TOGGLES = [
        'firewall_enabled'    => 'Firewall enabled',
        'firewall_signatures' => 'Block attack signatures (SQLi, XSS, path traversal)',
        'firewall_agents'     => 'Block known scanner user agents',
        'firewall_flood'      => 'Per-IP request flood cap',
    ];

    public function index(Request $request): Response
    {
        return $this->view('admin/security/index', [
            'toggles'    => self::TOGGLES,
            'settings'   => $this->currentToggles(),
            'blocks'     => Firewall::blocks(),
            'events'     => Firewall::recentEvents(40),
            'blocked24h' => Firewall::eventCountSince(date('Y-m-d H:i:s', time() - 86400)),
            'currentIp'  => $request->ip(),
            'allowlist'  => (array) config('security.firewall.allowlist', []),
        ]);
    }

    public function updateSettings(Request $request): Response
    {
        foreach (array_keys(self::TOGGLES) as $key) {
            Setting::set($key, $request->input($key) ? '1' : '0', 'boolean', 'security');
        }

        Setting::flushCache();
        ActivityLogger::log('security.settings_updated', 'settings', null);
        $this->success('Firewall settings saved.');

        return $this->redirect('/admin/security');
    }

    public function block(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'ip'     => 'required|max:45',
            'reason' => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/security', $validator->errors());
        }

        $ip = trim((string) $request->input('ip'));

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $this->error('That is not a valid IP address.');

            return $this->redirect('/admin/security');
        }

        // The self-lockout guard: never let an admin block the address they are on.
        if ($ip === $request->ip()) {
            $this->error('You cannot block the IP address you are currently using.');

            return $this->redirect('/admin/security');
        }

        Firewall::block(
            $ip,
            trim((string) $request->input('reason', '')) ?: 'Blocked manually',
            'manual',
            $this->userId(),
            null
        );

        ActivityLogger::log('security.ip_blocked', 'firewall_blocks', null, ['ip' => $ip]);
        $this->success('Blocked ' . $ip . '.');

        return $this->redirect('/admin/security');
    }

    public function unblock(Request $request): Response
    {
        Firewall::unblock($request->paramInt('id'));

        ActivityLogger::log('security.ip_unblocked', 'firewall_blocks', $request->paramInt('id'));
        $this->success('Block removed.');

        return $this->redirect('/admin/security');
    }

    /**
     * @return array<string,bool>
     */
    private function currentToggles(): array
    {
        $state = [];

        foreach (array_keys(self::TOGGLES) as $key) {
            $state[$key] = Setting::bool($key, true);
        }

        return $state;
    }

    private function userId(): ?int
    {
        return \App\Core\Auth::id();
    }
}
