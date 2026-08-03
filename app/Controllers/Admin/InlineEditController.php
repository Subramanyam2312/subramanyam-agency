<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Models\PageBlock;

/**
 * Saves edits made directly on the public page.
 *
 * This is the same write PageBlockController performs from the CMS form, reached
 * from a different surface. The rules are therefore identical and are enforced
 * here rather than in the browser:
 *
 *  - only a block that already exists may be written, so a crafted request
 *    cannot invent new page copy or reach another page's blocks;
 *  - the sanitiser is chosen from the block's stored `type`, never from anything
 *    the page sends, so a text field cannot be talked into accepting markup;
 *  - CSRF and the session are enforced by the usual middleware, because this
 *    route lives under /admin like every other write.
 */
final class InlineEditController extends Controller
{
    public function save(Request $request): Response
    {
        $edits = $request->input('edits', []);

        if (!is_array($edits) || $edits === []) {
            return Response::json(['ok' => false, 'message' => 'Nothing to save.'], 422);
        }

        $saved  = 0;
        $failed = [];

        foreach ($edits as $edit) {
            if (!is_array($edit)) {
                continue;
            }

            $pageKey  = (string) ($edit['page'] ?? '');
            $blockKey = (string) ($edit['block'] ?? '');
            $raw      = (string) ($edit['value'] ?? '');

            $block = Database::selectOne(
                'SELECT `id`, `type` FROM `page_blocks` WHERE `page_key` = :page AND `block_key` = :block LIMIT 1',
                [':page' => $pageKey, ':block' => $blockKey]
            );

            // Unknown block: refuse rather than create one from a page request.
            if ($block === null) {
                $failed[] = $pageKey . '.' . $blockKey;

                continue;
            }

            $value = $block['type'] === 'html'
                ? Sanitizer::rich($raw)
                : Sanitizer::plain($raw);

            Database::query(
                'UPDATE `page_blocks`
                    SET `value` = :value, `updated_by` = :user, `updated_at` = NOW()
                  WHERE `id` = :id',
                [':value' => $value, ':user' => Auth::id(), ':id' => (int) $block['id']]
            );

            $saved++;
        }

        PageBlock::flushCache();

        if ($saved > 0) {
            ActivityLogger::log('page_blocks.inline_edited', 'page_blocks', null, [
                'saved'  => $saved,
                'failed' => $failed,
            ]);
        }

        return Response::json([
            'ok'      => $failed === [],
            'saved'   => $saved,
            'failed'  => $failed,
            'message' => $failed === []
                ? ($saved === 1 ? 'Saved.' : $saved . ' changes saved.')
                : 'Some blocks could not be saved.',
        ], $failed === [] ? 200 : 422);
    }
}
