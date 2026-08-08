<?php

declare(strict_types=1);

namespace App\Core;

use GdImage;
use RuntimeException;

/**
 * Raster image handling on plain GD.
 *
 * Intervention Image would add a dependency (and its own GD/Imagick abstraction) to
 * do what amounts to "decode, orient, resize, encode as WebP". That is ~150 lines
 * here, so it stays first-party.
 */
final class ImageProcessor
{
    /**
     * Reads dimensions without decoding the whole file into memory.
     *
     * @return array{0:int,1:int}|null
     */
    public static function dimensions(string $path): ?array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    /**
     * Generates WebP copies at each requested width.
     *
     * Widths larger than the source are skipped — upscaling produces a bigger file
     * that looks worse, and a srcset entry that lies about its resolution.
     *
     * @param array<int,int> $widths
     * @return array<int,string> width => filename
     */
    public static function generateVariants(string $sourcePath, string $targetDirectory, string $baseName, array $widths, int $quality = 82): array
    {
        $image = self::open($sourcePath);

        if ($image === null) {
            return [];
        }

        $image = self::applyExifOrientation($image, $sourcePath);

        /*
         * GD cannot encode a palette image to WebP. imagewebp() warns
         * "Palette image not supported by webp", RETURNS TRUE, and writes a
         * zero-byte file — so trusting the return value records an empty
         * variant in srcset and the image breaks on the public site.
         *
         * Palette PNGs are ordinary: any "8-bit PNG" export produces one.
         * resize() already draws onto a truecolor canvas, which is why the
         * width variants survived and only the full-size one was empty.
         */
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $sourceWidth  = imagesx($image);
        $sourceHeight = imagesy($image);
        $variants     = [];

        foreach ($widths as $width) {
            if ($width >= $sourceWidth) {
                continue;
            }

            $height = (int) round($sourceHeight * ($width / $sourceWidth));
            $resized = self::resize($image, $width, $height);

            $filename = $baseName . '-' . $width . '.webp';

            if (self::writeWebp($resized, $targetDirectory . '/' . $filename, $quality)) {
                $variants[$width] = $filename;
            }

            imagedestroy($resized);
        }

        // Always provide a full-size WebP as well: for a large JPEG this is usually
        // the single biggest byte saving on the page.
        $fullName = $baseName . '-full.webp';

        if (self::writeWebp($image, $targetDirectory . '/' . $fullName, $quality)) {
            $variants[$sourceWidth] = $fullName;
        }

        imagedestroy($image);

        ksort($variants);

        return $variants;
    }

    /**
     * Rewrites the original in place with EXIF rotation applied, so the stored file
     * matches what every renderer will show. Phones routinely record portrait shots
     * as landscape-plus-orientation-flag, and browsers disagree about honouring it.
     */
    public static function normaliseOriginal(string $path, string $mime): void
    {
        if ($mime !== 'image/jpeg') {
            return;
        }

        $image = self::open($path);

        if ($image === null) {
            return;
        }

        $rotated = self::applyExifOrientation($image, $path);

        imagejpeg($rotated, $path, 90);
        imagedestroy($rotated);
    }

    private static function open(string $path): ?GdImage
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_GIF  => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * Writes a WebP and confirms something actually landed on disk.
     *
     * imagewebp() cannot be trusted on its own: for inputs it does not support
     * it returns TRUE while leaving a zero-byte file behind. A variant recorded
     * from that lands in srcset, the browser picks it, and the image silently
     * fails to render — which is exactly how this surfaced. Verifying the size
     * costs one stat() and removes the empty file so nothing can serve it.
     */
    private static function writeWebp(GdImage $image, string $path, int $quality): bool
    {
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        if (!@imagewebp($image, $path, $quality)) {
            @unlink($path);

            return false;
        }

        if (!is_file($path) || filesize($path) === 0) {
            @unlink($path);

            return false;
        }

        return true;
    }

    private static function resize(GdImage $source, int $width, int $height): GdImage
    {
        $target = imagecreatetruecolor($width, $height);

        if ($target === false) {
            throw new RuntimeException('Could not allocate image buffer.');
        }

        // Preserve transparency; without this a PNG logo resizes onto black.
        imagealphablending($target, false);
        imagesavealpha($target, true);

        imagecopyresampled(
            $target,
            $source,
            0, 0, 0, 0,
            $width, $height,
            imagesx($source), imagesy($source)
        );

        return $target;
    }

    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);

        if ($exif === false || !isset($exif['Orientation'])) {
            return $image;
        }

        $rotated = match ((int) $exif['Orientation']) {
            3       => imagerotate($image, 180, 0),
            6       => imagerotate($image, -90, 0),
            8       => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated === null || $rotated === false) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
