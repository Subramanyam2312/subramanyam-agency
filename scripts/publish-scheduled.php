<?php

declare(strict_types=1);

/**
 * Publishes posts whose scheduled time has arrived, and sweeps expired rate-limit rows.
 *
 * Intended for cron. On Hostinger shared hosting the minimum interval is five
 * minutes (one minute on Business and above), so scheduling is accurate to the
 * cron interval, not to the second:
 *
 *   *\/5 * * * * /usr/bin/php /home/USER/domains/DOMAIN/scripts/publish-scheduled.php
 *
 * This is a sweeper, not the only mechanism. The public blog also resolves any
 * overdue scheduled post on read, so the site stays correct even if cron is
 * misconfigured or silently stops — which on shared hosting it eventually does.
 */

use App\Core\ActivityLogger;
use App\Core\Firewall;
use App\Core\RateLimiter;
use App\Core\Sitemap;
use App\Models\Post;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

require BASE_PATH . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$published = Post::publishDue();

if ($published > 0) {
    ActivityLogger::log('posts.auto_published', 'posts', null, ['count' => $published]);
}

$swept = RateLimiter::sweep();

// Drop expired firewall bans and trim the old event log.
Firewall::sweep();

// Prune old traffic visitor hashes. If posts went live this run, drop cached pages.
\App\Core\Traffic::sweep();

if ($published > 0) {
    \App\Core\PageCache::purge();
}

/*
 * Rebuild the sitemap whenever this run actually published something, and on the
 * nightly run regardless — that second case is the safety net for a write that
 * failed silently at publish time.
 */
$rebuild = $published > 0 || in_array('--sitemap', $argv, true);
$sitemap = $rebuild ? Sitemap::generate() : ['ok' => true, 'count' => 0];

printf(
    "[%s] published %d scheduled post(s), swept %d expired rate-limit row(s), sitemap: %s%s",
    date('Y-m-d H:i:s'),
    $published,
    $swept,
    $rebuild ? ($sitemap['ok'] ? $sitemap['count'] . ' urls' : 'FAILED — ' . ($sitemap['message'] ?? '')) : 'skipped',
    PHP_EOL
);
