<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Csv;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ContactSubmission;

/**
 * Read-only inbox for contact form submissions.
 *
 * There is no create or edit: these are records of what someone actually sent, and
 * an editable enquiry is not evidence of anything.
 */
final class SubmissionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'state'  => (string) $request->query('state', ''),
        ];

        $result = ContactSubmission::inbox($filters, max(1, $request->integer('page', 1)));

        return $this->view('admin/submissions/index', [
            'rows'       => $result['data'],
            'pagination' => $result,
            'filters'    => $filters,
            'unread'     => ContactSubmission::unreadCount(),
        ]);
    }

    public function show(Request $request): Response
    {
        $submission = $this->findOrFail($request->paramInt('id'));

        // Opening it is what marks it read.
        if ((int) $submission['is_read'] === 0) {
            ContactSubmission::updateById((int) $submission['id'], ['is_read' => 1]);
            $submission['is_read'] = 1;
        }

        return $this->view('admin/submissions/show', ['submission' => $submission]);
    }

    public function toggleRead(Request $request): Response
    {
        $submission = $this->findOrFail($request->paramInt('id'));
        $isRead     = (int) $submission['is_read'] === 1 ? 0 : 1;

        ContactSubmission::updateById((int) $submission['id'], ['is_read' => $isRead]);

        $this->success($isRead === 1 ? 'Marked as read.' : 'Marked as unread.');

        return $this->redirect('/admin/submissions');
    }

    public function toggleSpam(Request $request): Response
    {
        $submission = $this->findOrFail($request->paramInt('id'));
        $isSpam     = (int) $submission['is_spam'] === 1 ? 0 : 1;

        ContactSubmission::updateById((int) $submission['id'], ['is_spam' => $isSpam]);

        ActivityLogger::log('submission.spam_toggled', 'contact_submissions', (int) $submission['id'], [
            'is_spam' => $isSpam,
        ]);

        $this->success($isSpam === 1 ? 'Moved to spam.' : 'Restored from spam.');

        return $this->redirect('/admin/submissions');
    }

    public function destroy(Request $request): Response
    {
        $submission = $this->findOrFail($request->paramInt('id'));

        ContactSubmission::deleteById((int) $submission['id']);

        ActivityLogger::log('submission.deleted', 'contact_submissions', (int) $submission['id']);
        $this->success('Enquiry deleted.');

        return $this->redirect('/admin/submissions');
    }

    public function export(Request $request): Response
    {
        $rows = ContactSubmission::forExport();

        ActivityLogger::log('submission.exported', 'contact_submissions', null, ['count' => count($rows)]);

        return Csv::download('enquiries-' . date('Y-m-d') . '.csv', $rows, [
            'id'           => 'ID',
            'name'         => 'Name',
            'email'        => 'Email',
            'phone'        => 'Phone',
            'company'      => 'Company',
            'service'      => 'Service',
            'budget_range' => 'Budget',
            'message'      => 'Message',
            'is_read'      => 'Read',
            'is_spam'      => 'Spam',
            'created_at'   => 'Received',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function findOrFail(int $id): array
    {
        $submission = ContactSubmission::find($id);

        if ($submission === null) {
            throw new HttpException(404, 'That enquiry no longer exists.');
        }

        return $submission;
    }
}
