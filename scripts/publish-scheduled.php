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
use App\Core\RateLimiter;
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

printf(
    "[%s] published %d scheduled post(s), swept %d expired rate-limit row(s)%s",
    date('Y-m-d H:i:s'),
    $published,
    $swept,
    PHP_EOL
);
