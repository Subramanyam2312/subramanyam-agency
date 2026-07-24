<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Api;
use App\Core\Auth;
use App\Core\MediaLibrary;
use App\Core\RemoteImage;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Media;

final class MediaController extends ApiController
{
    public function index(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_READ)) !== null) {
            return $denied;
        }

        $result = Media::browse(
            (string) $request->query('search', ''),
            max(1, $request->integer('page', 1)),
            max(1, min(50, $request->integer('per_page', 24)))
        );

        return Api::data(
            array_map([Api::class, 'media'], $result['data']),
            200,
            [
                'page'      => $result['current_page'],
                'per_page'  => $result['per_page'],
                'total'     => $result['total'],
                'last_page' => $result['last_page'],
            ]
        );
    }

    /**
     * Accepts a multipart file upload, a base64 blob or an HTTPS URL.
     *
     * Multipart and base64 are preferred: a URL means this server makes an outbound
     * request the caller chose, and while that path is guarded, the safest fetch is
     * the one that never happens.
     */
    public function store(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_WRITE)) !== null) {
            return $denied;
        }

        $file = $request->file('file');

        if ($file !== null) {
            $result = MediaLibrary::store($file, Auth::id());

            if (!$result['ok']) {
                return Api::validationFailed(['file' => (string) $result['message']]);
            }

            return $this->created((int) $result['id']);
        }

        $base64 = $request->input('base64');
        $url    = $request->input('url');

        if ($base64 !== null && $base64 !== '') {
            $fetched = RemoteImage::fromBase64(
                (string) $base64,
                (string) $request->input('filename', 'api-upload')
            );
        } elseif ($url !== null && $url !== '') {
            $fetched = RemoteImage::fromUrl((string) $url);
        } else {
            return Api::validationFailed([
                'file' => 'Send a multipart "file" field, or JSON with "base64" or "url".',
            ]);
        }

        if (!$fetched['ok']) {
            return Api::validationFailed(['file' => (string) $fetched['message']]);
        }

        $stored = MediaLibrary::storeFromPath((string) $fetched['path'], (string) $fetched['name'], Auth::id());

        if (!$stored['ok']) {
            @unlink((string) $fetched['path']);

            return Api::validationFailed(['file' => (string) $stored['message']]);
        }

        return $this->created((int) $stored['id']);
    }

    /**
     * Alt text is the one media field worth exposing: an agent publishing a post
     * with an image should be able to describe it.
     */
    public function update(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_WRITE)) !== null) {
            return $denied;
        }

        $id    = $request->paramInt('id');
        $media = Media::find($id);

        if ($media === null) {
            return Api::notFound('No media item with that id.');
        }

        $data = [];

        foreach (['alt_text', 'caption'] as $field) {
            if ($request->has($field)) {
                $value        = trim((string) $request->input($field));
                $data[$field] = $value === '' ? null : mb_substr($value, 0, 255);
            }
        }

        if ($data !== []) {
            Media::updateById($id, $data);
        }

        $this->log('api.media_updated', 'media', $id);

        return Api::data(Api::media((array) Media::find($id)));
    }

    private function created(int $id): Response
    {
        $media = Media::find($id);

        $this->log('api.media_uploaded', 'media', $id);

        return Api::data(Api::media((array) $media), 201)
            ->header('Location', url('/api/v1/media/' . $id));
    }
}
