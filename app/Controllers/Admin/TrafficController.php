<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Traffic;
use App\Models\Setting;

/**
 * Tools -> Traffic. The Traffic Manager plugin's dashboard.
 *
 * A cookieless, in-house view of visits — served from the site's own database, so
 * it works even if a visitor blocks Google Analytics. Read-only.
 */
final class TrafficController extends Controller
{
    public function index(Request $request): Response
    {
        $days = in_array($request->integer('days', 30), [7, 30, 90], true)
            ? $request->integer('days', 30)
            : 30;

        return $this->view('admin/traffic/index', [
            'enabled' => Setting::bool('plugin_traffic_enabled', true),
            'days'    => $days,
            'data'    => Traffic::summary($days),
        ]);
    }
}
