<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Spam scoring for form submissions.
 *
 * Layers, cheapest first:
 *   1. The honeypot and rate limiter already run in the controllers.
 *   2. Local heuristics here — too many links, spammy keywords, gibberish — which
 *      catch most bots with no network call.
 *   3. Akismet, if an API key is configured. This is the same service the WordPress
 *      plugin uses; it is only called when the local checks are inconclusive, so a
 *      missing or slow Akismet never blocks a legitimate enquiry.
 *
 * verdict() returns 'ham' (fine), 'spam' (store but flag), or 'block' (refuse).
 * The controller decides what each means; nothing here mutates state.
 */
final class SpamGuard
{
    private const SPAM_TERMS = [
        'viagra', 'cialis', 'casino', 'crypto giveaway', 'forex', 'seo services cheap',
        'buy backlinks', 'loan approved', 'bitcoin doubler', 'work from home guaranteed',
        'porn', 'xxx', 'escort', 'replica watches', 'weight loss pills',
    ];

    /*
     * User-agent fragments that never belong to a person filling in a form.
     *
     * Added after a scanner vendor's pitch arrived through the contact form and
     * scored clean: one link (the cap is four), no listed keyword, ordinary letter
     * ratio. Every local heuristic reads the message, and the message was
     * well-written — so nothing looked at the giveaway, which was the user agent
     * announcing HeadlessChrome.
     *
     * Matched as substrings, lowercased. These are deliberately literal: a real
     * browser never says "headlesschrome" or "python-requests", so the false
     * positive rate is close to zero. Generic words like "bot" are left out —
     * crawlers do not POST forms, and the word appears in enough odd real agents
     * to be worth avoiding.
     */
    private const AUTOMATION_AGENTS = [
        'headlesschrome', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'python-requests', 'python-urllib', 'curl/', 'wget/', 'go-http-client',
        'okhttp', 'scrapy', 'axios/', 'node-fetch', 'libwww-perl', 'httpclient',
        'apache-httpclient', 'postmanruntime', 'insomnia/',
    ];

    public static function enabled(): bool
    {
        try {
            return Setting::bool('plugin_spam_enabled', true);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * @param array{name?:string,email?:string,message?:string} $fields
     * @return array{verdict:string, reason:string}
     */
    public static function check(array $fields, Request $request): array
    {
        if (!self::enabled()) {
            return ['verdict' => 'ham', 'reason' => 'checks disabled'];
        }

        $message = (string) ($fields['message'] ?? '');
        $email   = (string) ($fields['email'] ?? '');

        // --- Automation ---------------------------------------------------
        // Cheapest signal available, and independent of how well the message reads.
        // Flagged rather than blocked: the enquiry is still stored and visible in
        // the admin, it just does not trigger a notification email. A false
        // positive costs a delayed reply, never a lost lead.
        $agent = mb_strtolower(trim($request->userAgent()));

        if ($agent === '') {
            return ['verdict' => 'spam', 'reason' => 'no user agent'];
        }

        foreach (self::AUTOMATION_AGENTS as $marker) {
            if (str_contains($agent, $marker)) {
                return ['verdict' => 'spam', 'reason' => 'automated client: ' . $marker];
            }
        }

        // --- Local heuristics ---------------------------------------------
        $maxLinks = (int) Setting::get('spam_max_links', 4);
        $links    = preg_match_all('~https?://|www\.~i', $message);

        if ($links > $maxLinks) {
            return ['verdict' => 'block', 'reason' => 'too many links'];
        }

        $haystack = mb_strtolower($message . ' ' . $email);
        foreach (self::SPAM_TERMS as $term) {
            if (str_contains($haystack, $term)) {
                return ['verdict' => 'spam', 'reason' => 'spam keyword'];
            }
        }

        // A message that is one long word, or almost no letters, is bot noise.
        $letters = preg_match_all('/\p{L}/u', $message);
        if (mb_strlen($message) > 20 && $letters < mb_strlen($message) * 0.4) {
            return ['verdict' => 'spam', 'reason' => 'low letter ratio'];
        }

        // --- Akismet (optional) -------------------------------------------
        $key = trim((string) Setting::get('akismet_key', ''));

        if ($key !== '') {
            $akismet = self::akismet($key, $fields, $request);

            if ($akismet === true) {
                return ['verdict' => 'spam', 'reason' => 'akismet'];
            }
        }

        return ['verdict' => 'ham', 'reason' => 'passed'];
    }

    /**
     * Calls the Akismet comment-check API. Returns true (spam), false (ham) or null
     * (could not reach Akismet — treated as ham by the caller).
     *
     * @param array<string,mixed> $fields
     */
    private static function akismet(string $key, array $fields, Request $request): ?bool
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        try {
            $params = http_build_query([
                'blog'                 => (string) config('app.url'),
                'user_ip'              => $request->ip(),
                'user_agent'           => $request->userAgent(),
                'referrer'             => $request->referer(),
                'comment_type'         => 'contact-form',
                'comment_author'       => (string) ($fields['name'] ?? ''),
                'comment_author_email' => (string) ($fields['email'] ?? ''),
                'comment_content'      => (string) ($fields['message'] ?? ''),
                'blog_lang'            => 'en',
            ]);

            $ch = curl_init("https://{$key}.rest.akismet.com/1.1/comment-check");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $params,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 4,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $body = curl_exec($ch);
            curl_close($ch);

            if (!is_string($body)) {
                return null;
            }

            return trim($body) === 'true';
        } catch (Throwable $e) {
            error_log('Akismet check failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Verifies an Akismet key so the plugin screen can confirm it works before the
     * owner relies on it.
     */
    public static function verifyKey(string $key): bool
    {
        if ($key === '' || !function_exists('curl_init')) {
            return false;
        }

        try {
            $ch = curl_init('https://rest.akismet.com/1.1/verify-key');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query(['key' => $key, 'blog' => (string) config('app.url')]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);

            return is_string($body) && trim($body) === 'valid';
        } catch (Throwable) {
            return false;
        }
    }
}
