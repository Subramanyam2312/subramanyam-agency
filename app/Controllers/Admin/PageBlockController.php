<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\Media;
use App\Models\PageBlock;

/**
 * Editing screen for page copy.
 *
 * Not a ResourceController: blocks are never created or deleted through the UI —
 * they are defined by the templates that consume them. The editor only changes
 * values, and the form builds itself from whatever rows exist.
 */
final class PageBlockController extends Controller
{
    public function index(Request $request): Response
    {
        $pages = [];

        foreach (PageBlock::pageKeys() as $pageKey) {
            $pages[$pageKey] = PageBlock::count(['page_key' => $pageKey]);
        }

        return $this->view('admin/page-blocks/index', ['pages' => $pages]);
    }

    public function edit(Request $request): Response
    {
        $pageKey = (string) $request->param('page');

        $grouped = PageBlock::forPageGrouped($pageKey);

        if ($grouped === []) {
            throw new HttpException(404, 'No editable copy is defined for that page.');
        }

        // Preload attached media so the image blocks can show a thumbnail without
        // a query per block.
        $media = [];

        foreach ($grouped as $blocks) {
            foreach ($blocks as $block) {
                if (!empty($block['media_id'])) {
                    $media[(int) $block['media_id']] = Media::find((int) $block['media_id']);
                }
            }
        }

        return $this->view('admin/page-blocks/form', [
            'pageKey' => $pageKey,
            'grouped' => $grouped,
            'media'   => $media,
        ]);
    }

    public function update(Request $request): Response
    {
        $pageKey = (string) $request->param('page');
        $blocks  = PageBlock::all(['page_key' => $pageKey]);

        if ($blocks === []) {
            throw new HttpException(404);
        }

        $values    = $request->input('blocks', []);
        $mediaIds  = $request->input('block_media', []);
        $userId    = Auth::id();
        $updated   = 0;

        foreach ($blocks as $block) {
            $key = (string) $block['block_key'];

            if (!is_array($values) || !array_key_exists($key, $values)) {
                continue;
            }

            $raw = (string) $values[$key];

            // Only the html type may carry markup, and it is sanitised on the way in.
            $value = $block['type'] === 'html' ? Sanitizer::rich($raw) : Sanitizer::plain($raw);

            $mediaId = null;

            if ($block['type'] === 'image' && is_array($mediaIds) && ($mediaIds[$key] ?? '') !== '') {
                $mediaId = (int) $mediaIds[$key];
            }

            PageBlock::updateById((int) $block['id'], [
                'value'      => $value,
                'media_id'   => $mediaId,
                'updated_by' => $userId,
            ]);

            $updated++;
        }

        PageBlock::flushCache();

        ActivityLogger::log('page_blocks.updated', 'page_blocks', null, [
            'page'   => $pageKey,
            'blocks' => $updated,
        ]);

        $this->success('Page copy saved.');

        return $this->redirect('/admin/page-content/' . $pageKey);
    }
}
