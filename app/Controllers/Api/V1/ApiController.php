<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ActivityLogger;
use App\Core\Api;
use App\Core\MediaLibrary;
use App\Core\RemoteImage;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Media;

/**
 * Shared behaviour for the v1 endpoints: ability checks, audit logging, and the
 * image-intake path that accepts an existing media id, a URL or base64.
 */
abstract class ApiController
{
    /**
     * Returns a 403 response when the token lacks the ability, or null to proceed.
     *
     * Written as a value rather than an exception so each endpoint's first line
     * shows exactly what it requires.
     */
    protected function requires(Request $request, string $ability): ?Response
    {
        $token = $request->apiToken();

        if ($token === null) {
            return Api::error('unauthenticated', 'Missing token.', 401);
        }

        if (!ApiToken::can($token, $ability)) {
            return Api::forbidden("This token does not have the \"{$ability}\" ability.");
        }

        return null;
    }

    /**
     * @param array<string,mixed> $meta
     */
    protected function log(string $action, string $entityType, ?int $entityId, array $meta = []): void
    {
        ActivityLogger::log($action, $entityType, $entityId, $meta);
    }

    /**
     * Resolves whatever image reference the caller sent into a media id.
     *
     * Three accepted shapes, in order of preference:
     *   featured_media_id: 12                          — already uploaded
     *   featured_image: {"base64": "...", "filename": "..."}
     *   featured_image: {"url": "https://..."}         — fetched, SSRF-guarded
     *
     * @return int|null|Response
     */
    protected function resolveFeaturedImage(Request $request): int|null|Response
    {
        if ($request->has('featured_media_id')) {
            $id = $request->input('featured_media_id');

            if ($id === null || $id === '') {
                return null;
            }

            if (Media::find((int) $id) === null) {
                return Api::validationFailed(['featured_media_id' => 'No media item with that id.']);
            }

            return (int) $id;
        }

        $image = $request->input('featured_image');

        if ($image === null || $image === '') {
            return null;
        }

        // A bare string is treated as a URL, which is the shape most callers reach for.
        if (is_string($image)) {
            $image = ['url' => $image];
        }

        if (!is_array($image)) {
            return Api::validationFailed(['featured_image' => 'Send a URL string, or an object with url or base64.']);
        }

        if (isset($image['base64'])) {
            $fetched = RemoteImage::fromBase64(
                (string) $image['base64'],
                (string) ($image['filename'] ?? 'api-upload')
            );
        } elseif (isset($image['url'])) {
            $fetched = RemoteImage::fromUrl((string) $image['url']);
        } else {
            return Api::validationFailed(['featured_image' => 'Provide either url or base64.']);
        }

        if (!$fetched['ok']) {
            return Api::validationFailed(['featured_image' => (string) $fetched['message']]);
        }

        $stored = MediaLibrary::storeFromPath(
            (string) $fetched['path'],
            (string) $fetched['name'],
            \App\Core\Auth::id()
        );

        if (!$stored['ok']) {
            @unlink((string) $fetched['path']);

            return Api::validationFailed(['featured_image' => (string) $stored['message']]);
        }

        return (int) $stored['id'];
    }
}
