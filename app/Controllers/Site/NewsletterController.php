<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\NewsletterSubscriber;

final class NewsletterController extends Controller
{
    public function subscribe(Request $request): Response
    {
        // Same honeypot pattern as the contact form.
        if (trim((string) $request->input('website', '')) !== '') {
            return $this->done($request, 'Thanks for subscribing.');
        }

        $throttleKey = 'newsletter:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return $this->done($request, 'Thanks for subscribing.');
        }

        RateLimiter::hit($throttleKey, 3600);

        $validator = Validator::make($request->all(), ['email' => 'required|email|max:191']);

        if ($validator->fails()) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'errors' => $validator->errors()], 422);
            }

            Session::flash('error', 'That email address does not look right.');

            return $this->back($request);
        }

        $email    = strtolower(trim((string) $request->input('email')));
        $existing = NewsletterSubscriber::findBy('email', $email);

        if ($existing !== null) {
            // Re-subscribing after unsubscribing should work, and an address that
            // is already on the list must not produce a duplicate-key error — nor
            // a message revealing that it was already there.
            if ($existing['unsubscribed_at'] !== null) {
                NewsletterSubscriber::updateById((int) $existing['id'], ['unsubscribed_at' => null]);
            }

            return $this->done($request, 'Thanks for subscribing.');
        }

        Database::insert('newsletter_subscribers', [
            'email'      => $email,
            'source'     => str_starts_with($request->referer(), url('/blog')) ? 'blog' : 'footer',
            'ip_hash'    => $request->ipHash(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('newsletter.subscribed', 'newsletter_subscribers', null);

        return $this->done($request, 'Thanks for subscribing.');
    }

    private function done(Request $request, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => true, 'message' => $message]);
        }

        Session::flash('success', $message);

        return $this->back($request);
    }

    private function back(Request $request): Response
    {
        return Response::back($request);
    }
}
