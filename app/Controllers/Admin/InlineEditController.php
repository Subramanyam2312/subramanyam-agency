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
 * Saves edits typed directly on a page previewed from the CMS.
 *
 * This is the same write the page-copy form performs, reached from a different
 * surface, so the rules are identical and enforced here rather than in the
 * browser:
 *
 *  - it writes `draft_value`, never the published `value`, so typing on a page
 *    can never change what a visitor sees — publishing stays a separate step;
 *  - only a block that already exists may be written, so a crafted request
 *    cannot invent page copy or reach another page's blocks;
 *  - the sanitiser is chosen from the block's stored `type`, not from anything
 *    the page sends, so markup aimed at a text field is stripped;
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
                'SELECT `id`, `type`, `value` FROM `page_blocks`
                  WHERE `page_key` = :page AND `block_key` = :block LIMIT 1',
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

            // Typing a block back to its published wording clears the draft, so the
            // pending-changes count never claims work that is not there.
            Database::query(
                'UPDATE `page_blocks`
                    SET `draft_value` = :value, `updated_by` = :user, `updated_at` = NOW()
                  WHERE `id` = :id',
                [
                    ':value' => $value === (string) ($block['value'] ?? '') ? null : $value,
                    ':user'  => Auth::id(),
                    ':id'    => (int) $block['id'],
                ]
            );

            $saved++;
        }

        PageBlock::flushCache();

        if ($saved > 0) {
            ActivityLogger::log('page_blocks.inline_drafted', 'page_blocks', null, [
                'saved'  => $saved,
                'failed' => $failed,
            ]);
        }

        $pending = PageBlock::draftCount();

        return Response::json([
            'ok'      => $failed === [],
            'saved'   => $saved,
            'pending' => $pending,
            'failed'  => $failed,
            'message' => $failed !== []
                ? 'Some blocks could not be saved.'
                : ($pending > 0
                    ? 'Saved as a draft — press Publish when you are ready.'
                    : 'Saved. This matches what is already published.'),
        ], $failed === [] ? 200 : 422);
    }
}
