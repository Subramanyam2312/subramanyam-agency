<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\MediaLibrary;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Media;

final class MediaController extends Controller
{
    public function index(Request $request): Response
    {
        $result = Media::browse(
            trim((string) $request->query('search', '')),
            max(1, $request->integer('page', 1))
        );

        return $this->view('admin/media/index', [
            'items'      => $result['data'],
            'pagination' => $result,
            'search'     => (string) $request->query('search', ''),
            'maxBytes'   => (int) config('security.uploads.max_bytes'),
        ]);
    }

    /**
     * Handles one or many files from the same form field.
     */
    public function store(Request $request): Response
    {
        $files = $_FILES['files'] ?? null;

        if (!is_array($files) || !isset($files['name'])) {
            $this->error('No files were received.');

            return $this->redirect('/admin/media');
        }

        $uploaded = 0;
        $failures = [];

        foreach ((array) $files['name'] as $index => $name) {
            $file = [
                'name'     => $name,
                'type'     => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error'    => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$index] ?? 0,
            ];

            if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result = MediaLibrary::store($file, Auth::id());

            if ($result['ok']) {
                $uploaded++;
            } else {
                $failures[] = $name . ': ' . $result['message'];
            }
        }

        if ($uploaded > 0) {
            $this->success($uploaded . ' file' . ($uploaded === 1 ? '' : 's') . ' uploaded.');
        }

        if ($failures !== []) {
            // Report every rejection rather than only the first — uploading ten and
            // being told about one is how files silently go missing.
            $this->error(implode(' · ', $failures));
        }

        return $this->redirect('/admin/media');
    }

    public function update(Request $request): Response
    {
        $id    = $request->paramInt('id');
        $media = Media::find($id);

        if ($media === null) {
            throw new HttpException(404, 'That file is not in the library.');
        }

        $validator = Validator::make($request->all(), [
            'alt_text' => 'nullable|max:255',
            'caption'  => 'nullable|max:255',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/media', $validator->errors());
        }

        Media::updateById($id, [
            'alt_text' => trim((string) $request->input('alt_text', '')) ?: null,
            'caption'  => trim((string) $request->input('caption', '')) ?: null,
        ]);

        ActivityLogger::log('media.updated', 'media', $id);
        $this->success('Image details saved.');

        return $this->redirect('/admin/media');
    }

    public function destroy(Request $request): Response
    {
        $id    = $request->paramInt('id');
        $media = Media::find($id);

        if ($media === null) {
            throw new HttpException(404, 'That file is not in the library.');
        }

        // Refuse rather than leave a hole where an image used to be.
        $usage = Media::usage($id);

        if ($usage !== []) {
            $this->error('Still in use by — ' . implode('; ', array_slice($usage, 0, 4))
                . (count($usage) > 4 ? ' and more' : '') . '. Detach it there first.');

            return $this->redirect('/admin/media');
        }

        MediaLibrary::delete($id);
        $this->success('File deleted.');

        return $this->redirect('/admin/media');
    }

    /**
     * Backs the media picker modal used by every image field.
     */
    public function picker(Request $request): Response
    {
        $result = Media::browse(
            trim((string) $request->query('search', '')),
            max(1, $request->integer('page', 1))
        );

        $items = array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'path'  => '/' . ltrim((string) $row['path'], '/'),
            'name'  => (string) $row['original_name'],
            'alt'   => (string) ($row['alt_text'] ?? ''),
            'mime'  => (string) $row['mime'],
        ], $result['data']);

        return Response::json([
            'items'     => $items,
            'page'      => $result['current_page'],
            'last_page' => $result['last_page'],
        ]);
    }
}
