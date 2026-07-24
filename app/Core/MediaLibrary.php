<?php

declare(strict_types=1);

namespace App\Core;

use finfo;
use RuntimeException;

/**
 * Upload validation, storage and deletion for the media library.
 *
 * Validation is by finfo content sniffing, never by the filename extension —
 * "logo.png" that is actually a PHP script is the oldest upload attack there is.
 * Stored names are random hex, so a caller can never influence the path, and the
 * uploads directory additionally has execution disabled in .htaccess.
 */
final class MediaLibrary
{
    /**
     * @param array<string,mixed> $file A single entry from $_FILES.
     * @return array{ok:bool, message?:string, id?:int}
     */
    public static function store(array $file, ?int $userId): array
    {
        $error = self::validate($file);

        if ($error !== null) {
            return ['ok' => false, 'message' => $error];
        }

        $mime      = self::detectMime($file['tmp_name']);
        $allowed   = (array) config('security.uploads.allowed_mimes');
        $extension = $allowed[$mime];

        // Random name: the uploader cannot choose the path, the extension, or
        // collide with an existing file.
        $baseName = bin2hex(random_bytes(16));
        $filename = $baseName . '.' . $extension;

        $relativeDirectory = 'uploads/' . date('Y/m');
        $absoluteDirectory = PUBLIC_PATH . '/' . $relativeDirectory;

        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            return ['ok' => false, 'message' => 'Could not create the upload directory.'];
        }

        $absolutePath = $absoluteDirectory . '/' . $filename;

        if ($mime === 'image/svg+xml') {
            // SVG is executable XML. Never move it verbatim.
            $clean = self::sanitiseSvg((string) file_get_contents($file['tmp_name']));

            if ($clean === null) {
                return ['ok' => false, 'message' => 'That SVG could not be made safe to serve.'];
            }

            file_put_contents($absolutePath, $clean);
        } elseif (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            return ['ok' => false, 'message' => 'Could not save the uploaded file.'];
        }

        @chmod($absolutePath, 0644);

        $width      = null;
        $height     = null;
        $variants   = [];

        if ($mime !== 'image/svg+xml') {
            ImageProcessor::normaliseOriginal($absolutePath, $mime);

            $dimensions = ImageProcessor::dimensions($absolutePath);

            if ($dimensions !== null) {
                [$width, $height] = $dimensions;
            }

            $generated = ImageProcessor::generateVariants(
                $absolutePath,
                $absoluteDirectory,
                $baseName,
                (array) config('security.uploads.widths'),
                (int) config('security.uploads.webp_quality', 82)
            );

            foreach ($generated as $variantWidth => $variantName) {
                $variants[$variantWidth] = $relativeDirectory . '/' . $variantName;
            }
        }

        $id = Database::insert('media', [
            'filename'      => $filename,
            'original_name' => substr((string) $file['name'], 0, 255),
            'path'          => $relativeDirectory . '/' . $filename,
            'mime'          => $mime,
            'size'          => (int) filesize($absolutePath),
            'width'         => $width,
            'height'        => $height,
            'alt_text'      => null,
            'variants'      => $variants === [] ? null : json_encode($variants),
            'uploaded_by'   => $userId,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        ActivityLogger::log('media.uploaded', 'media', $id, ['mime' => $mime]);

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Soft-deletes the row and removes the files from disk.
     *
     * Files go immediately because orphaned bytes on shared hosting cost real quota,
     * while the row is kept soft-deleted so anything still referencing it can report
     * a missing asset rather than break on a null join.
     */
    public static function delete(int $id): bool
    {
        $media = Database::selectOne('SELECT * FROM `media` WHERE `id` = :id', [':id' => $id]);

        if ($media === null) {
            return false;
        }

        $paths = array_merge(
            [$media['path']],
            array_values(json_column($media['variants']))
        );

        foreach ($paths as $path) {
            $absolute = PUBLIC_PATH . '/' . ltrim((string) $path, '/');

            // Confine deletion to the uploads tree even if a path were ever tampered with.
            $real    = realpath($absolute);
            $uploads = realpath(PUBLIC_PATH . '/uploads');

            if ($real !== false && $uploads !== false && str_starts_with($real, $uploads) && is_file($real)) {
                @unlink($real);
            }
        }

        Database::update('media', ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $id]);

        ActivityLogger::log('media.deleted', 'media', $id);

        return true;
    }

    /**
     * @param array<string,mixed> $file
     */
    private static function validate(array $file): ?string
    {
        $code = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($code !== UPLOAD_ERR_OK) {
            return match ($code) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is larger than the server allows.',
                UPLOAD_ERR_PARTIAL                        => 'The upload did not finish. Try again.',
                UPLOAD_ERR_NO_FILE                        => 'No file was selected.',
                default                                   => 'The upload failed.',
            };
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return 'That file was not received as an upload.';
        }

        $maxBytes = (int) config('security.uploads.max_bytes');

        if ((int) $file['size'] > $maxBytes) {
            return 'That file is larger than ' . round($maxBytes / 1048576, 1) . ' MB.';
        }

        $mime    = self::detectMime($file['tmp_name']);
        $allowed = (array) config('security.uploads.allowed_mimes');

        if (!isset($allowed[$mime])) {
            return 'That file type is not allowed. Accepted: JPG, PNG, WebP, GIF, SVG.';
        }

        return null;
    }

    private static function detectMime(string $path): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);

        return $mime === false ? 'application/octet-stream' : $mime;
    }

    /**
     * Strips everything executable out of an SVG.
     *
     * Returns null if the document will not parse — an SVG we cannot fully
     * understand is one we should not serve.
     */
    private static function sanitiseSvg(string $svg): ?string
    {
        // Entity declarations enable XXE and billion-laughs; refuse them outright.
        if (preg_match('/<!ENTITY/i', $svg) === 1) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        $document = new \DOMDocument();
        $loaded   = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOENT);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->documentElement === null) {
            return null;
        }

        if (strtolower($document->documentElement->nodeName) !== 'svg') {
            return null;
        }

        $forbiddenTags = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'set', 'animate'];

        $xpath = new \DOMXPath($document);

        foreach ($forbiddenTags as $tag) {
            $nodes = $xpath->query('//*[local-name()="' . $tag . '"]');

            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $elements = $xpath->query('//*');

        if ($elements !== false) {
            foreach (iterator_to_array($elements) as $element) {
                if (!$element instanceof \DOMElement) {
                    continue;
                }

                foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                    $name  = strtolower($attribute->nodeName);
                    $value = trim($attribute->nodeValue ?? '');

                    // Every on* handler, and any URL scheme that can execute.
                    if (str_starts_with($name, 'on')
                        || preg_match('/^\s*(javascript|data|vbscript)\s*:/i', $value) === 1) {
                        $element->removeAttribute($attribute->nodeName);
                        continue;
                    }

                    // href/xlink:href pointing off-site can phone home or pull in script.
                    if (in_array($name, ['href', 'xlink:href'], true)
                        && $value !== ''
                        && !str_starts_with($value, '#')) {
                        $element->removeAttribute($attribute->nodeName);
                    }
                }
            }
        }

        $output = $document->saveXML();

        return $output === false ? null : $output;
    }
}
