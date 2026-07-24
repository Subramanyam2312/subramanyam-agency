<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Models\ClientLogo;
use App\Models\Media;

final class ClientLogoController extends ResourceController
{
    protected string $model = ClientLogo::class;

    protected string $route = '/admin/client-logos';

    protected string $views = 'admin/client-logos';

    protected string $singular = 'Client logo';

    protected string $plural = 'Client logos';

    protected string $order = 'sort_order ASC, name ASC';

    protected bool $sortable = true;

    protected array $searchable = ['name'];

    /**
     * Joined to media so the list can show the actual logo rather than an id.
     */
    protected function listQuery(array $filters, int $page): array
    {
        $rows = ClientLogo::withMedia();

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => stripos((string) $row['name'], $search) !== false
            ));
        }

        // The set is a logo strip — a couple of dozen rows at most — so filtering in
        // PHP avoids duplicating the join into a second counted query.
        $total = count($rows);

        return [
            'data'         => $rows,
            'total'        => $total,
            'per_page'     => max(1, $total),
            'current_page' => 1,
            'last_page'    => 1,
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'media_path', 'label' => 'Logo', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Client', 'type' => 'primary'],
            ['key' => 'link_url', 'label' => 'Link'],
            ['key' => 'sort_order', 'label' => 'Order'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name'       => 'required|max:150',
            // Required: the column is NOT NULL with a RESTRICT foreign key, so a
            // logo without an image cannot exist.
            'media_id'   => 'required|integer|exists:media,id',
            'link_url'   => 'nullable|url|max:255',
            'sort_order' => 'nullable|integer',
        ];
    }

    protected function fields(?array $record): array
    {
        $mediaId = $record['media_id'] ?? '';

        return [
            ['name' => 'name', 'label' => 'Client name', 'value' => $record['name'] ?? '', 'required' => true],
            ['name' => 'media_id', 'label' => 'Logo image', 'type' => 'media', 'value' => $mediaId,
             'media' => $mediaId === '' ? null : Media::find((int) $mediaId),
             'hint' => 'SVG or transparent PNG works best in the marquee.'],
            ['name' => 'link_url', 'label' => 'Website', 'type' => 'url', 'value' => $record['link_url'] ?? ''],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_active', 'label' => 'Show in the logo strip', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        return [
            'name'       => trim((string) $request->input('name')),
            'media_id'   => (int) $request->input('media_id'),
            'link_url'   => trim((string) $request->input('link_url', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active'  => (int) $request->input('is_active', 0),
        ];
    }
}
