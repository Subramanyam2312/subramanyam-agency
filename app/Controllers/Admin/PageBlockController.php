<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\PageCache;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Sitemap;
use App\Models\Media;
use App\Models\PageBlock;

/**
 * Editing screen for page copy.
 *
 * Not a ResourceController: most blocks are defined by the templates that consume
 * them, and the editor only changes values — the form builds itself from whatever
 * rows exist.
 *
 * The exception is the repeatable card groups declared in config/repeatables.php
 * (approach steps, client cards, credentials, FAQ entries). Those can be added and
 * removed here, because the templates render however many exist rather than a
 * fixed number.
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
            'pageKey'     => $pageKey,
            'grouped'     => $grouped,
            'media'       => $media,
            'repeatables' => (array) config('repeatables.' . $pageKey, []),
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

            /*
             * Saving stores a draft. The live site keeps showing `value` until
             * Publish is pressed, so typing here never changes the public page.
             * A draft identical to what is published is stored as NULL, which
             * keeps "unpublished changes" honest when an edit is undone.
             */
            PageBlock::updateById((int) $block['id'], [
                'draft_value' => $value === (string) ($block['value'] ?? '') ? null : $value,
                'media_id'    => $mediaId,
                'updated_by'  => $userId,
            ]);

            $updated++;
        }

        PageBlock::flushCache();

        ActivityLogger::log('page_blocks.updated', 'page_blocks', null, [
            'page'   => $pageKey,
            'blocks' => $updated,
        ]);

        $pending = PageBlock::draftCount();

        $this->success($pending > 0
            ? 'Saved as a draft. Press Publish when you want visitors to see it.'
            : 'Page copy saved — it matches what is already published.');

        return $this->redirect('/admin/page-content/' . $pageKey);
    }

    /** Makes every pending draft the live copy. */
    public function publish(Request $request): Response
    {
        $published = PageBlock::publishDrafts();

        if ($published === 0) {
            $this->error('There is nothing waiting to be published.');

            return $this->redirect($this->backTo($request));
        }

        // The published copy is baked into cached pages and the sitemap.
        PageCache::purge();
        Sitemap::generate();

        ActivityLogger::log('page_blocks.published', 'page_blocks', null, ['blocks' => $published]);
        $this->success($published === 1
            ? 'Published. The change is live.'
            : 'Published ' . $published . ' changes. They are live now.');

        return $this->redirect($this->backTo($request));
    }

    /** Throws pending drafts away; the live copy is untouched. */
    public function discard(Request $request): Response
    {
        $discarded = PageBlock::discardDrafts();

        if ($discarded === 0) {
            $this->error('There were no unpublished changes to discard.');

            return $this->redirect($this->backTo($request));
        }

        ActivityLogger::log('page_blocks.discarded', 'page_blocks', null, ['blocks' => $discarded]);
        $this->success('Discarded ' . $discarded . ' unpublished change' . ($discarded === 1 ? '' : 's') . '.');

        return $this->redirect($this->backTo($request));
    }

    /**
     * Publishing is reachable from several screens, so it returns to the one it
     * was pressed on rather than always landing on the page list.
     */
    private function backTo(Request $request): string
    {
        $to = (string) $request->input('return_to', '');

        return preg_match('#^/admin[a-z0-9/\-]*$#i', $to) === 1 ? $to : '/admin/page-content';
    }

    /**
     * Adds the next card to a repeatable group by inserting its set of blocks.
     */
    public function addItem(Request $request): Response
    {
        $pageKey = (string) $request->param('page');
        $groupId = (string) $request->param('group');
        $spec    = $this->repeatable($pageKey, $groupId);

        $next = $this->nextIndex($pageKey, $groupId, $spec);
        $max  = (int) config('repeatables.max', 12);

        if ($next > $max) {
            $this->error('That section is already at its limit of ' . $max . '.');

            return $this->redirect('/admin/page-content/' . $pageKey);
        }

        foreach ($spec['fields'] as $suffix => [$label, $type]) {
            Database::query(
                'INSERT INTO `page_blocks`
                    (`page_key`, `block_key`, `label`, `type`, `value`, `group_name`, `sort_order`, `created_at`, `updated_at`)
                 VALUES (:page, :block, :label, :type, \'\', :grp, :sort, NOW(), NOW())',
                [
                    ':page'  => $pageKey,
                    ':block' => $groupId . '_' . $next . '_' . $suffix,
                    ':label' => str_replace(':n', (string) $next, $label),
                    ':type'  => $type,
                    ':grp'   => $spec['group'],
                    ':sort'  => (int) $spec['base'] + ($next * 10) + array_search($suffix, array_keys($spec['fields']), true),
                ]
            );
        }

        PageBlock::flushCache();
        ActivityLogger::log('page_blocks.item_added', 'page_blocks', null, ['page' => $pageKey, 'group' => $groupId]);
        $this->success('Added another ' . $spec['noun'] . '. Fill it in below, then save.');

        return $this->redirect('/admin/page-content/' . $pageKey);
    }

    /**
     * Removes the last card of a repeatable group.
     *
     * Only ever the last one: renumbering the middle of a group would silently
     * rewrite block keys that the page is already rendering.
     */
    public function removeItem(Request $request): Response
    {
        $pageKey = (string) $request->param('page');
        $groupId = (string) $request->param('group');
        $spec    = $this->repeatable($pageKey, $groupId);

        $last = $this->nextIndex($pageKey, $groupId, $spec) - 1;

        if ($last < 1) {
            $this->error('There is nothing left to remove there.');

            return $this->redirect('/admin/page-content/' . $pageKey);
        }

        foreach (array_keys($spec['fields']) as $suffix) {
            Database::query(
                'DELETE FROM `page_blocks` WHERE `page_key` = :page AND `block_key` = :block',
                [':page' => $pageKey, ':block' => $groupId . '_' . $last . '_' . $suffix]
            );
        }

        PageBlock::flushCache();
        ActivityLogger::log('page_blocks.item_removed', 'page_blocks', null, ['page' => $pageKey, 'group' => $groupId]);
        $this->success('Removed the last ' . $spec['noun'] . '.');

        return $this->redirect('/admin/page-content/' . $pageKey);
    }

    /**
     * @return array{label:string,group:string,noun:string,base:int,fields:array<string,array{0:string,1:string}>}
     */
    private function repeatable(string $pageKey, string $groupId): array
    {
        $spec = config('repeatables.' . $pageKey . '.' . $groupId);

        // Unknown page or group: refuse rather than invent blocks from a URL.
        if (!is_array($spec)) {
            throw new HttpException(404);
        }

        return $spec;
    }

    /** The first index with no blocks yet, i.e. the number the next card takes. */
    private function nextIndex(string $pageKey, string $groupId, array $spec): int
    {
        $first = (string) array_key_first($spec['fields']);
        $max   = (int) config('repeatables.max', 12);

        for ($i = 1; $i <= $max + 1; $i++) {
            $exists = Database::selectOne(
                'SELECT `id` FROM `page_blocks` WHERE `page_key` = :page AND `block_key` = :block LIMIT 1',
                [':page' => $pageKey, ':block' => $groupId . '_' . $i . '_' . $first]
            );

            if ($exists === null) {
                return $i;
            }
        }

        return $max + 1;
    }

}
