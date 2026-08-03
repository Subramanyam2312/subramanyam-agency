<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\Setting;
use Throwable;

/**
 * Resolves the site's type pairing from Settings -> Appearance.
 *
 * Two modes:
 *
 *  - self-hosted (the default): one of the curated pairings in config/fonts.php.
 *    Nothing leaves the server and font-src 'self' is untouched.
 *
 *  - google: any two Google families by name. This is the only path that reaches
 *    a third party, so it is opt-in, and SecurityHeaders widens the policy only
 *    while it is switched on.
 *
 * Family names in google mode are typed by an administrator and then printed
 * into a <style> block, so they are validated to a conservative character set
 * rather than escaped: anything that is not a plausible font name is dropped.
 * That closes the CSS-injection route into the page's own stylesheet.
 */
final class Fonts
{
    /** Google's own naming allows letters, digits, spaces and hyphens. */
    private const FAMILY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,48}$/';

    public static function usesGoogle(): bool
    {
        try {
            return Setting::get('fonts_source', 'self') === 'google'
                && self::googleFamily('font_google_display') !== null
                && self::googleFamily('font_google_body') !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The display and body stacks to apply, or null when the brand default is in
     * use and the stylesheet already says the right thing.
     *
     * @return array{display:string,body:string}|null
     */
    public static function stacks(): ?array
    {
        if (self::usesGoogle()) {
            $display = self::googleFamily('font_google_display');
            $body    = self::googleFamily('font_google_body');

            return [
                'display' => "'" . $display . "', ui-serif, Georgia, serif",
                'body'    => "'" . $body . "', ui-sans-serif, system-ui, sans-serif",
            ];
        }

        $pairings = (array) config('fonts.pairings', []);
        $key      = self::pairing();

        if ($key === (string) config('fonts.default', 'instrument') || !isset($pairings[$key])) {
            return null;
        }

        return [
            'display' => (string) $pairings[$key]['display'],
            'body'    => (string) $pairings[$key]['body'],
        ];
    }

    /** The selected self-hosted pairing key, falling back to the default. */
    public static function pairing(): string
    {
        try {
            $key = (string) Setting::get('font_pairing', '');
        } catch (Throwable) {
            $key = '';
        }

        $pairings = (array) config('fonts.pairings', []);

        return isset($pairings[$key]) ? $key : (string) config('fonts.default', 'instrument');
    }

    /** The Google stylesheet URL for the two chosen families. */
    public static function googleUrl(): string
    {
        $display = self::googleFamily('font_google_display');
        $body    = self::googleFamily('font_google_body');

        if ($display === null || $body === null) {
            return '';
        }

        $family = static fn (string $name): string
            => 'family=' . rawurlencode($name) . ':wght@400;500;600;700';

        return 'https://fonts.googleapis.com/css2?' . $family($display) . '&' . $family($body) . '&display=swap';
    }

    /**
     * A validated Google family name, or null if it is missing or implausible.
     */
    private static function googleFamily(string $settingKey): ?string
    {
        try {
            $name = trim((string) Setting::get($settingKey, ''));
        } catch (Throwable) {
            return null;
        }

        if ($name === '' || preg_match(self::FAMILY_PATTERN, $name) !== 1) {
            return null;
        }

        return $name;
    }
}
