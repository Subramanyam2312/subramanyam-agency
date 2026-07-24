<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Csv;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\NewsletterSubscriber;

final class SubscriberController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $result = NewsletterSubscriber::list($search, max(1, $request->integer('page', 1)));

        return $this->view('admin/subscribers/index', [
            'rows'       => $result['data'],
            'pagination' => $result,
            'search'     => $search,
            'active'     => NewsletterSubscriber::activeCount(),
        ]);
    }

    /**
     * Hard delete, deliberately.
     *
     * Someone who asks to be removed from a mailing list should actually be
     * removed, not soft-deleted into a table that still holds their address.
     */
    public function destroy(Request $request): Response
    {
        $id         = $request->paramInt('id');
        $subscriber = NewsletterSubscriber::find($id);

        if ($subscriber === null) {
            throw new HttpException(404, 'That subscriber no longer exists.');
        }

        NewsletterSubscriber::forceDelete($id);

        ActivityLogger::log('subscriber.deleted', 'newsletter_subscribers', $id);
        $this->success('Subscriber removed.');

        return $this->redirect('/admin/subscribers');
    }

    public function export(Request $request): Response
    {
        $rows = NewsletterSubscriber::forExport();

        ActivityLogger::log('subscriber.exported', 'newsletter_subscribers', null, ['count' => count($rows)]);

        return Csv::download('subscribers-' . date('Y-m-d') . '.csv', $rows, [
            'email'           => 'Email',
            'source'          => 'Source',
            'confirmed_at'    => 'Confirmed',
            'unsubscribed_at' => 'Unsubscribed',
            'created_at'      => 'Signed up',
        ]);
    }
}
