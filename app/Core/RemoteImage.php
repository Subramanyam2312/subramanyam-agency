<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Turns a caller-supplied image URL or base64 blob into a local temp file that can
 * be handed to MediaLibrary.
 *
 * Fetching a URL that an API client chose means the server makes a request on the
 * caller's behalf — server-side request forgery. On shared hosting the blast radius
 * is smaller than on a cloud box with a metadata endpoint, but the same trick still
 * reaches anything bound to localhost and anything on the host's private network.
 *
 * So: HTTPS only, every resolved address checked against private and reserved
 * ranges before the request is made, redirects refused outright (a 302 to
 * 127.0.0.1 is the standard bypass), a hard size cap, and a short timeout.
 *
 * Residual risk: DNS rebinding between the check and the fetch. Closing that needs
 * connect-to-pinned-IP support, which is not portable across the curl builds
 * Hostinger ships. Base64 and multipart upload avoid the question entirely and are
 * the documented preference in API.md.
 */
final class RemoteImage
{
    private const TIMEOUT_SECONDS = 8;

    /**
     * @return array{ok:bool, message?:string, path?:string, name?:string}
     */
    public static function fromUrl(string $url): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'message' => 'This server cannot fetch remote images. Send base64 instead.'];
        }

        $parts = parse_url($url);

        if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') === '') {
            return ['ok' => false, 'message' => 'Image URLs must be absolute and use HTTPS.'];
        }

        $blocked = self::validateHost((string) $parts['host']);

        if ($blocked !== null) {
            return ['ok' => false, 'message' => $blocked];
        }

        $maxBytes = (int) config('security.uploads.max_bytes');
        $handle   = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            // No redirects: following one re-opens every check made above.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => config('app.name') . ' media fetcher',
            // Abort mid-download rather than buffering a hostile multi-gigabyte body.
            CURLOPT_NOPROGRESS     => false,
            CURLOPT_PROGRESSFUNCTION => static function ($resource, $downloadSize, $downloaded) use ($maxBytes): int {
                return ($downloadSize > $maxBytes || $downloaded > $maxBytes) ? 1 : 0;
            },
        ]);

        $body   = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($handle);

        curl_close($handle);

        if ($body === false || $body === '') {
            return ['ok' => false, 'message' => 'Could not download that image' . ($error !== '' ? ': ' . $error : '.')];
        }

        if ($status !== 200) {
            return ['ok' => false, 'message' => "That URL returned HTTP {$status}."];
        }

        if (strlen($body) > $maxBytes) {
            return ['ok' => false, 'message' => 'That image is larger than the upload limit.'];
        }

        $name = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'remote-image';

        return self::toTempFile($body, $name);
    }

    /**
     * Accepts a raw base64 string or a full data: URI.
     *
     * @return array{ok:bool, message?:string, path?:string, name?:string}
     */
    public static function fromBase64(string $encoded, string $filename = 'upload'): array
    {
        if (preg_match('/^data:([\w\/+.-]+);base64,(.*)$/s', $encoded, $matches) === 1) {
            $encoded = $matches[2];
        }

        // Reject before decoding: base64 inflates by ~4/3, so checking the encoded
        // length first stops a huge payload from being expanded in memory at all.
        $maxBytes = (int) config('security.uploads.max_bytes');

        if (strlen($encoded) > (int) ceil($maxBytes * 4 / 3) + 1024) {
            return ['ok' => false, 'message' => 'That image is larger than the upload limit.'];
        }

        $binary = base64_decode(strtr(trim($encoded), ' ', '+'), true);

        if ($binary === false || $binary === '') {
            return ['ok' => false, 'message' => 'The base64 image data could not be decoded.'];
        }

        if (strlen($binary) > $maxBytes) {
            return ['ok' => false, 'message' => 'That image is larger than the upload limit.'];
        }

        return self::toTempFile($binary, $filename);
    }

    /**
     * Rejects anything resolving into a range that should never be reachable from
     * a user-supplied URL.
     */
    private static function validateHost(string $host): ?string
    {
        $addresses = [];

        // A literal IP in the URL never touches DNS, so check it directly.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $addresses[] = $host;
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);

            if ($records === false || $records === []) {
                return 'That hostname could not be resolved.';
            }

            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $addresses[] = $record['ip'];
                }

                if (isset($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }
            }
        }

        if ($addresses === []) {
            return 'That hostname could not be resolved.';
        }

        foreach ($addresses as $address) {
            // FILTER_FLAG_NO_PRIV_RANGE and NO_RES_RANGE cover RFC1918, loopback,
            // link-local (including 169.254.169.254) and the IPv6 equivalents.
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($public === false) {
                return 'That URL resolves to a private or reserved address and will not be fetched.';
            }
        }

        return null;
    }

    /**
     * @return array{ok:bool, message?:string, path?:string, name?:string}
     */
    private static function toTempFile(string $binary, string $name): array
    {
        $path = tempnam(sys_get_temp_dir(), 'apimedia');

        if ($path === false || file_put_contents($path, $binary) === false) {
            return ['ok' => false, 'message' => 'Could not buffer the image on the server.'];
        }

        return ['ok' => true, 'path' => $path, 'name' => $name];
    }
}
