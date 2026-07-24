<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/dashboard', [
            'counts'      => $this->counts(),
            'submissions' => $this->recentSubmissions(),
            'activity'    => ActivityLogger::recent(8),
        ]);
    }

    /**
     * One grouped query per table rather than one per status — the dashboard is the
     * most-loaded admin page and this keeps it at four round trips.
     *
     * @return array<string,int>
     */
    private function counts(): array
    {
        $counts = [
            'posts_draft'       => 0,
            'posts_scheduled'   => 0,
            'posts_published'   => 0,
            'submissions_unread' => 0,
            'subscribers'       => 0,
            'media'             => 0,
        ];

        $rows = Database::select(
            "SELECT `status`, COUNT(*) AS total
             FROM `posts`
             WHERE `deleted_at` IS NULL
             GROUP BY `status`"
        );

        foreach ($rows as $row) {
            $counts['posts_' . $row['status']] = (int) $row['total'];
        }

        $counts['submissions_unread'] = (int) Database::scalar(
            'SELECT COUNT(*) FROM `contact_submissions` WHERE `is_read` = 0 AND `is_spam` = 0 AND `deleted_at` IS NULL'
        );

        $counts['subscribers'] = (int) Database::scalar(
            'SELECT COUNT(*) FROM `newsletter_subscribers` WHERE `unsubscribed_at` IS NULL'
        );

        $counts['media'] = (int) Database::scalar(
            'SELECT COUNT(*) FROM `media` WHERE `deleted_at` IS NULL'
        );

        return $counts;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function recentSubmissions(): array
    {
        return Database::select(
            'SELECT cs.id, cs.name, cs.email, cs.message, cs.is_read, cs.created_at, s.title AS service_title
             FROM `contact_submissions` cs
             LEFT JOIN `services` s ON s.id = cs.service_id
             WHERE cs.deleted_at IS NULL AND cs.is_spam = 0
             ORDER BY cs.created_at DESC
             LIMIT 5'
        );
    }
}
