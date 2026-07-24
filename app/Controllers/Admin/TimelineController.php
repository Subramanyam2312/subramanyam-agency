<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\TimelineEntry;

final class TimelineController extends ResourceController
{
    protected string $model = TimelineEntry::class;

    protected string $route = '/admin/timeline';

    protected string $views = 'admin/timeline';

    protected string $singular = 'Timeline entry';

    protected string $plural = 'Timeline';

    protected string $order = 'sort_order ASC, id ASC';

    protected bool $sortable = true;

    protected array $searchable = ['year', 'title', 'description'];

    protected function columns(): array
    {
        return [
            ['key' => 'year', 'label' => 'Year'],
            ['key' => 'title', 'label' => 'Title', 'type' => 'primary'],
            ['key' => 'sort_order', 'label' => 'Order'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'year'        => 'required|max:9',
            'title'       => 'required|max:180',
            'description' => 'nullable',
            'sort_order'  => 'nullable|integer',
        ];
    }

    protected function fields(?array $record): array
    {
        return [
            ['name' => 'year', 'label' => 'Year', 'value' => $record['year'] ?? '', 'required' => true,
             'hint' => 'Free text, so ranges like 2019–21 and labels like "Today" both work.'],
            ['name' => 'title', 'label' => 'Title', 'value' => $record['title'] ?? '', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3,
             'value' => $record['description'] ?? ''],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_active', 'label' => 'Show on the site', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        return [
            'year'        => trim((string) $request->input('year')),
            'title'       => trim((string) $request->input('title')),
            'description' => trim((string) $request->input('description', '')) ?: null,
            'sort_order'  => (int) $request->input('sort_order', 0),
            'is_active'   => (int) $request->input('is_active', 0),
        ];
    }
}
