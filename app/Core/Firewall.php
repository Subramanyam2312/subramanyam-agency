<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Application-layer firewall: attack-signature detection, scanner-agent blocking,
 * a per-IP flood cap, and a DB-backed blocklist with temporary auto-bans.
 *
 * Two principles run through the whole class:
 *
 *  1. Fail OPEN. Every database touch is wrapped so that a transient DB error
 *     lets the request through rather than taking the site down. A firewall that
 *     bricks the site when its own storage hiccups is a worse outage than the
 *     attacks it prevents.
 *
 *  2. Inspect the request line, never the body. The path, query string and
 *     user agent are scanned; the POST body is not. This CMS legitimately submits
 *     HTML and SQL-shaped text in post bodies, so body scanning would block an
 *     editor writing an article about SQL injection — a classic WAF own-goal.
 *
 * This is an application firewall, not a network one. It stops the application-layer
 * junk (scanners, injection probes, credential-stuffing bots) that reaches PHP. It
 * is NOT a defence against volumetric DDoS — that belongs at Cloudflare or the host.
 */
final class Firewall
{
    private const TABLE_BLOCKS = 'firewall_blocks';
    private const TABLE_EVENTS = 'firewall_events';

    /**
     * Attack signatures, checked against the decoded path + query string only.
     * Kept deliberately tight — each pattern targets payloads that essentially
     * never appear in a legitimate URL, to keep false positives near zero.
     *
     * @var array<string,string>
     */
    private const SIGNATURES = [
        'sqli'      => '/\b(union[\s\/*]+select|select[\s\/*].+\bfrom\b|insert[\s\/*]+into|drop[\s\/*]+table|update[\s\/*].+\bset\b|delete[\s\/*]+from|information_schema|\bsleep\s*\(|\bbenchmark\s*\(|\bwaitfor\s+delay|\bor\b\s+1\s*=\s*1|\'\s*or\s*\'1\'\s*=\s*\'1)/i',
        'xss'       => '/(<script\b|<\/script>|javascript:|vbscript:|onerror\s*=|onload\s*=|onmouseover\s*=|<iframe\b|<svg\b[^>]*onload|document\.cookie|%3cscript)/i',
        'traversal' => '/(\.\.[\/\\\\]|%2e%2e[\/\\\\]|%2e%2e%2f|\/etc\/passwd|\/proc\/self\/|\bphp:\/\/|\bfile:\/\/|\bdata:text\/html|c:\\\\windows)/i',
    ];

    /**
     * Known scanning and exploitation tools. curl and common libraries are NOT
     * here — the REST API is a legitimate curl client, and blocking it would break
     * the very automation the API exists for.
     *
     * @var array<int,string>
     */
    private const BAD_AGENTS = [
        'sqlmap', 'nikto', 'nmap', 'masscan', 'nessus', 'acunetix', 'netsparker',
        'wpscan', 'dirbuster', 'gobuster', 'fimap', 'joomscan', 'zgrab',
        'evilscanner', 'arachni', 'nuclei',
    ];

    // ------------------------------------------------------------ master state

    public static function enabled(): bool
    {
        return self::setting('firewall_enabled', true);
    }

    /**
     * An IP the firewall must never touch: the env allowlist. This is the escape
     * hatch that guarantees the owner can always reach their own admin.
     */
    public static function allowlisted(string $ip): bool
    {
        return in_array($ip, (array) config('security.firewall.allowlist', []), true);
    }

    // ------------------------------------------------------------ blocklist

    /**
     * The active block for an IP, or null. Fails open on a DB error.
     *
     * @return array<string,mixed>|null
     */
    public static function activeBlock(string $ip): ?array
    {
        try {
            return Database::selectOne(
                'SELECT * FROM `' . self::TABLE_BLOCKS . '`
                 WHERE `ip` = :ip AND (`expires_at` IS NULL OR `expires_at` > NOW())
                 LIMIT 1',
                [':ip' => $ip]
            );
        } catch (Throwable $e) {
            error_log('Firewall block lookup failed (allowing request): ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Adds or refreshes a block. Portable upsert (no VALUES()/alias syntax) so it
     * runs on both MySQL 8 and the MariaDB builds Hostinger may serve.
     */
    public static function block(string $ip, ?string $reason, string $source, ?int $userId, ?string $expiresAt): bool
    {
        try {
            $updated = Database::update(self::TABLE_BLOCKS, [
                'reason'     => $reason,
                'source'     => $source,
                'created_by' => $userId,
                'expires_at' => $expiresAt,
            ], ['ip' => $ip]);

            if ($updated === 0 && self::activeBlock($ip) === null && !self::rowExists($ip)) {
                Database::insert(self::TABLE_BLOCKS, [
                    'ip'         => $ip,
                    'reason'     => $reason,
                    'source'     => $source,
                    'created_by' => $userId,
                    'expires_at' => $expiresAt,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return true;
        } catch (Throwable $e) {
            error_log('Firewall block write failed: ' . $e->getMessage());

            return false;
        }
    }

    public static function unblock(int $id): bool
    {
        try {
            return Database::delete(self::TABLE_BLOCKS, ['id' => $id]) > 0;
        } catch (Throwable $e) {
            error_log('Firewall unblock failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function blocks(): array
    {
        try {
            return Database::select(
                'SELECT b.*, u.name AS created_by_name
                 FROM `' . self::TABLE_BLOCKS . '` b
                 LEFT JOIN `users` u ON u.id = b.created_by
                 ORDER BY b.created_at DESC'
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    // ------------------------------------------------------------ inspection

    /**
     * Examines the request line for attack signatures and scanner agents.
     * Returns a hit descriptor, or null when the request looks clean.
     *
     * Does NOT check the blocklist or flood cap — those are separate, cheaper
     * checks the middleware runs in their own order.
     *
     * @return array{rule:string,status:int,message:string}|null
     */
    public static function inspect(Request $request): ?array
    {
        // Scan the raw request line, urldecoded so an encoded payload (%3Cscript,
        // or a +-for-space) cannot slip past the regex. urldecode (not rawurldecode)
        // is used precisely because it also turns + into a space — otherwise
        // "union+select" in a query string would evade the SQLi pattern.
        $target = strtolower(urldecode($request->path()) . ' ' . urldecode($request->rawQueryString()));

        if (self::setting('firewall_signatures', true)) {
            foreach (self::SIGNATURES as $rule => $pattern) {
                if (preg_match($pattern, $target) === 1) {
                    return [
                        'rule'    => $rule,
                        'status'  => 403,
                        'message' => 'Your request was blocked by the firewall.',
                    ];
                }
            }
        }

        if (self::setting('firewall_agents', true)) {
            $agent = strtolower($request->userAgent());

            foreach (self::BAD_AGENTS as $bad) {
                if ($agent !== '' && str_contains($agent, $bad)) {
                    return [
                        'rule'    => 'bad_agent',
                        'status'  => 403,
                        'message' => 'Your request was blocked by the firewall.',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Records a malicious hit and returns true if it pushed the IP over the strike
     * threshold (meaning the caller should treat the IP as now auto-banned).
     */
    public static function recordStrike(string $ip): bool
    {
        $key    = 'fw-strike:' . $ip;
        $window = (int) config('security.firewall.strike_window', 600);
        $max    = (int) config('security.firewall.strike_max', 5);

        try {
            $strikes = RateLimiter::hit($key, $window);
        } catch (Throwable $e) {
            return false;
        }

        if ($strikes >= $max) {
            self::autoBan($ip, 'Repeated malicious requests');

            return true;
        }

        return false;
    }

    /**
     * Counts an unauthenticated request toward the flood cap. Returns true when the
     * IP has crossed it (and has therefore just been auto-banned).
     */
    public static function registerFloodHit(string $ip): bool
    {
        if (!self::setting('firewall_flood', true)) {
            return false;
        }

        $key    = 'fw-flood:' . $ip;
        $window = (int) config('security.firewall.flood_window', 60);
        $max    = (int) config('security.firewall.flood_max', 240);

        try {
            $hits = RateLimiter::hit($key, $window);
        } catch (Throwable $e) {
            return false;
        }

        if ($hits > $max) {
            self::autoBan($ip, 'Request flood');

            return true;
        }

        return false;
    }

    public static function autoBan(string $ip): void
    {
        // Overloaded signature kept simple: reason is optional.
        $reason = func_num_args() > 1 ? (string) func_get_arg(1) : 'Automatic ban';

        $minutes = (int) config('security.firewall.ban_minutes', 60);
        self::block($ip, $reason, 'auto', null, date('Y-m-d H:i:s', time() + $minutes * 60));
    }

    // ------------------------------------------------------------ event log

    public static function log(Request $request, string $rule, string $action): void
    {
        try {
            Database::insert(self::TABLE_EVENTS, [
                'ip'         => $request->ip(),
                'method'     => $request->method(),
                'path'       => mb_substr($request->path(), 0, 255),
                'rule'       => $rule,
                'user_agent' => mb_substr($request->userAgent(), 0, 255) ?: null,
                'action'     => $action,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            error_log('Firewall event log failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recentEvents(int $limit = 30): array
    {
        try {
            return Database::select(
                'SELECT * FROM `' . self::TABLE_EVENTS . '` ORDER BY `created_at` DESC LIMIT ' . max(1, min(200, $limit))
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function eventCountSince(string $since): int
    {
        try {
            return (int) Database::scalar(
                'SELECT COUNT(*) FROM `' . self::TABLE_EVENTS . '` WHERE `created_at` >= :since',
                [':since' => $since]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Housekeeping for the cron: drop expired bans and trim the event log so it
     * cannot grow without bound on a busy, well-scanned site.
     */
    public static function sweep(): void
    {
        try {
            Database::query('DELETE FROM `' . self::TABLE_BLOCKS . '` WHERE `expires_at` IS NOT NULL AND `expires_at` <= NOW()');
            Database::query('DELETE FROM `' . self::TABLE_EVENTS . '` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        } catch (Throwable $e) {
            error_log('Firewall sweep failed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------ internals

    private static function rowExists(string $ip): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM `' . self::TABLE_BLOCKS . '` WHERE `ip` = :ip',
            [':ip' => $ip]
        ) > 0;
    }

    private static function setting(string $key, bool $default): bool
    {
        try {
            return Setting::bool($key, $default);
        } catch (Throwable $e) {
            // If settings are unreadable, honour the master default rather than
            // silently disabling protection.
            return $default;
        }
    }
}
