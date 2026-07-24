<?php

declare(strict_types=1);

namespace App\Core;

/**
 * JSON envelope helpers.
 *
 * Every API response — success or failure — goes through here, so a client can
 * rely on exactly two shapes and never has to guess whether an error arrived as
 * a string, an object, or an HTML error page.
 *
 *   success  {"data": ..., "meta": {...}}
 *   failure  {"error": {"code": "...", "message": "...", "details": {...}}}
 */
final class Api
{
    /**
     * @param array<string,mixed>|array<int,mixed> $data
     * @param array<string,mixed>                  $meta
     */
    public static function data(array $data, int $status = 200, array $meta = []): Response
    {
        $body = ['data' => $data];

        if ($meta !== []) {
            $body['meta'] = $meta;
        }

        return Response::json($body, $status);
    }

    /**
     * @param array<string,string> $details Field-level validation messages.
     */
    public static function error(string $code, string $message, int $status, array $details = []): Response
    {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return Response::json(['error' => $error], $status);
    }

    /**
     * @param array<string,string> $errors
     */
    public static function validationFailed(array $errors): Response
    {
        return self::error(
            'validation_failed',
            'The submitted data was not valid.',
            422,
            $errors
        );
    }

    public static function notFound(string $message = 'Resource not found.'): Response
    {
        return self::error('not_found', $message, 404);
    }

    public static function forbidden(string $message = 'This token is not permitted to do that.'): Response
    {
        return self::error('forbidden', $message, 403);
    }

    /**
     * Shapes a post row for output.
     *
     * Explicit field list rather than returning the row: a `SELECT *` reaching the
     * API is how internal columns leak the day someone adds one.
     *
     * @param array<string,mixed> $post
     * @param array<int,string>   $tags
     * @return array<string,mixed>
     */
    public static function post(array $post, array $tags = []): array
    {
        return [
            'id'            => (int) $post['id'],
            'title'         => $post['title'],
            'slug'          => $post['slug'],
            'excerpt'       => $post['excerpt'],
            'content'       => $post['content'],
            'status'        => $post['status'],
            'published_at'  => $post['published_at'],
            'reading_time'  => (int) $post['reading_time'],
            'is_featured'   => (bool) $post['is_featured'],
            'category_id'   => $post['category_id'] === null ? null : (int) $post['category_id'],
            'category'      => $post['category_name'] ?? null,
            'author'        => $post['author_name'] ?? null,
            'tags'          => $tags,
            'featured_media_id' => $post['featured_media_id'] === null ? null : (int) $post['featured_media_id'],
            'featured_image'    => $post['featured_image'] ?? null,
            'seo'           => [
                'meta_title'       => $post['meta_title'],
                'meta_description' => $post['meta_description'],
                'canonical_url'    => $post['canonical_url'],
                'noindex'          => (bool) $post['noindex'],
            ],
            'url'           => $post['status'] === 'published' ? url('/blog/' . $post['slug']) : null,
            'created_at'    => $post['created_at'],
            'updated_at'    => $post['updated_at'],
        ];
    }

    /**
     * @param array<string,mixed> $media
     * @return array<string,mixed>
     */
    public static function media(array $media): array
    {
        return [
            'id'       => (int) $media['id'],
            'url'      => url('/' . ltrim((string) $media['path'], '/')),
            'path'     => '/' . ltrim((string) $media['path'], '/'),
            'mime'     => $media['mime'],
            'width'    => $media['width'] === null ? null : (int) $media['width'],
            'height'   => $media['height'] === null ? null : (int) $media['height'],
            'size'     => (int) $media['size'],
            'alt_text' => $media['alt_text'],
            'variants' => is_array($media['variants'] ?? null)
                ? $media['variants']
                : json_column($media['variants'] ?? null),
            'created_at' => $media['created_at'],
        ];
    }
}
