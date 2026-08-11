<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Session;
use App\Core\SpamGuard;
use App\Core\Validator;
use App\Models\Service;
use App\Models\Setting;

final class ContactController extends Controller
{
    public function show(Request $request): Response
    {
        $ogImage = is_file(PUBLIC_PATH . '/uploads/founder/founder.jpg')
            ? rtrim((string) config('app.url'), '/') . '/uploads/founder/founder.jpg'
            : null;

        /*
         * The form is deliberately three fields (55a22b5, "a short form"), so the
         * service and budget lists it once fed are gone: the template never read
         * either, and Service::options() was running a query on every page view to
         * build a list nobody saw.
         *
         * The validation rules for phone, company, service_id and budget_range stay.
         * They are all nullable, they cost nothing on a request that omits them, and
         * they are what stops a hand-crafted POST writing junk into those columns.
         * Service is still imported because notify() resolves the service name for
         * the notification email, and the admin submission view still shows Budget.
         */
        return $this->view('site/contact', [
            'meta'     => [
                // Shortened for the same reason as About: the old title plus the site-name
                // suffix was 77 characters and got cut off in the results.
                'title'       => 'Contact — Digital Marketing, Chennai',
                'description' => "Get in touch with Subramanyam M N — digital marketing strategist and content creator in Chennai. A straight conversation about strategy, ad creative, video and SEO. No obligation.",
                'og_image'    => $ogImage,
            ],
        ]);
    }

    public function submit(Request $request): Response
    {
        /*
         * Honeypot first, before anything expensive.
         *
         * A field hidden from people but present in the DOM: a real visitor never
         * fills it, most bots fill everything. Answering with the normal success
         * response rather than an error means the bot has no signal to adapt to.
         */
        if (trim((string) $request->input('website', '')) !== '') {
            return $this->done($request, 'Thanks — we will be in touch shortly.');
        }

        $throttleKey = 'contact:' . $request->ip();
        $maxAttempts = (int) config('security.contact.max_submissions', 5);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            return $this->fail(
                $request,
                ['message' => 'You have sent several messages already. Please email us directly instead.'],
                429
            );
        }

        $validator = Validator::make($request->all(), [
            'name'         => 'required|max:150',
            'email'        => 'required|email|max:191',
            'phone'        => 'nullable|phone',
            'company'      => 'nullable|max:150',
            'service_id'   => 'nullable|integer|exists:services,id',
            'budget_range' => 'nullable|max:60',
            'message'      => 'required|min:20|max:5000',
        ], [
            'message' => 'Message',
        ]);

        if ($validator->fails()) {
            return $this->fail($request, $validator->errors(), 422);
        }

        RateLimiter::hit($throttleKey, (int) config('security.contact.decay_seconds', 3600));

        // Spam scoring (honeypot + rate limit already passed above). A hard block
        // is answered with the normal success message so a bot gets no signal; a
        // 'spam' verdict is stored but flagged and never emailed onward.
        $spam = SpamGuard::check([
            'name'    => (string) $request->input('name'),
            'email'   => (string) $request->input('email'),
            'message' => (string) $request->input('message'),
        ], $request);

        if ($spam['verdict'] === 'block') {
            ActivityLogger::log('contact.spam_blocked', 'contact_submissions', null, ['reason' => $spam['reason']]);

            return $this->done($request, 'Thanks — your message is with us. We reply to everything within one working day.');
        }

        $isSpam = $spam['verdict'] === 'spam';

        $serviceId = $request->input('service_id');

        $id = Database::insert('contact_submissions', [
            'name'         => trim((string) $request->input('name')),
            'email'        => strtolower(trim((string) $request->input('email'))),
            'phone'        => $this->blankToNull((string) $request->input('phone', '')),
            'company'      => $this->blankToNull((string) $request->input('company', '')),
            'service_id'   => $serviceId === null || $serviceId === '' ? null : (int) $serviceId,
            'budget_range' => $this->blankToNull((string) $request->input('budget_range', '')),
            // Stored as plain text: this is a record of what someone typed, and it
            // is rendered escaped. Nothing here should ever be interpreted as markup.
            'message'      => Sanitizer::plain((string) $request->input('message')),
            'is_spam'      => $isSpam ? 1 : 0,
            'ip_hash'      => $request->ipHash(),
            'user_agent'   => $request->userAgent(),
            'referrer'     => $request->referer(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log($isSpam ? 'contact.spam_flagged' : 'contact.received', 'contact_submissions', $id);

        // Only a genuine enquiry is emailed onward.
        if (!$isSpam) {
            $this->notify($request, $id);
        }

        return $this->done($request, 'Thanks — your message is with us. We reply to everything within one working day.');
    }

    /**
     * Emails the enquiry onward. Failure is logged, never surfaced: the message is
     * already saved, so telling the visitor it failed would be both alarming and
     * wrong — and would tempt them to send it again.
     */
    private function notify(Request $request, int $id): void
    {
        $to = (string) (config('mail.to.address') ?: Setting::get('contact_email', ''));

        if ($to === '') {
            return;
        }

        $service = $request->input('service_id')
            ? Service::find((int) $request->input('service_id'))
            : null;

        Mailer::send(
            $to,
            'New enquiry from ' . trim((string) $request->input('name')),
            'contact-notification',
            [
                'name'    => trim((string) $request->input('name')),
                'email'   => trim((string) $request->input('email')),
                'phone'   => (string) $request->input('phone', ''),
                'company' => (string) $request->input('company', ''),
                'service' => $service['title'] ?? '',
                'budget'  => (string) $request->input('budget_range', ''),
                'message' => Sanitizer::plain((string) $request->input('message')),
                'link'    => url('/admin/submissions/' . $id),
            ],
            [
                // Reply-To is the sender; From stays on our own domain so the mail
                // passes DMARC. Hitting reply still reaches the enquirer.
                'reply_to'      => trim((string) $request->input('email')),
                'reply_to_name' => trim((string) $request->input('name')),
            ]
        );
    }

    /**
     * Thank-you state without a full page reload when JS is available, and a
     * normal redirect when it is not.
     */
    private function done(Request $request, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $message]);
        }

        Session::flash('success', $message);

        return $this->redirect('/contact');
    }

    /**
     * @param array<string,string> $errors
     */
    private function fail(Request $request, array $errors, int $status): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'errors' => $errors], $status);
        }

        return $this->redirectWithErrors('/contact', $errors, $request->only([
            'name', 'email', 'phone', 'company', 'service_id', 'budget_range', 'message',
        ]));
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
