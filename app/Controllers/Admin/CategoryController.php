<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Models\Category;

final class CategoryController extends ResourceController
{
    protected string $model = Category::class;

    protected string $route = '/admin/categories';

    protected string $views = 'admin/categories';

    protected string $singular = 'Category';

    protected string $plural = 'Categories';

    protected string $order = 'sort_order ASC, name ASC';

    protected bool $affectsSitemap = true;

    protected ?string $slugColumn = 'slug';

    protected string $slugSource = 'name';

    protected array $searchable = ['name', 'slug'];

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Name', 'type' => 'primary', 'sub' => 'slug'],
            ['key' => 'description', 'label' => 'Description'],
            ['key' => 'sort_order', 'label' => 'Order'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'name'             => 'required|max:120',
            'slug'             => 'nullable|max:180',
            'description'      => 'nullable|max:500',
            'sort_order'       => 'nullable|integer',
            'meta_title'       => 'nullable|max:180',
            'meta_description' => 'nullable|max:300',
        ];
    }

    protected function fields(?array $record): array
    {
        return [
            ['name' => 'name', 'label' => 'Name', 'value' => $record['name'] ?? '', 'required' => true],
            ['name' => 'slug', 'label' => 'Slug', 'value' => $record['slug'] ?? '',
             'hint' => 'Leave blank to generate from the name.'],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3,
             'value' => $record['description'] ?? '',
             'hint' => 'Shown on the category archive page.'],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number',
             'value' => $record['sort_order'] ?? 0],
            ['type' => 'section', 'label' => 'Search engine listing'],
            ['name' => 'meta_title', 'label' => 'Meta title', 'value' => $record['meta_title'] ?? ''],
            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2,
             'value' => $record['meta_description'] ?? ''],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        return [
            'name'             => trim((string) $request->input('name')),
            'slug'             => trim((string) $request->input('slug', '')),
            'description'      => $this->blankToNull((string) $request->input('description', '')),
            'sort_order'       => (int) $request->input('sort_order', 0),
            'meta_title'       => $this->blankToNull((string) $request->input('meta_title', '')),
            'meta_description' => $this->blankToNull((string) $request->input('meta_description', '')),
        ];
    }

    /**
     * Deleting is allowed even when posts reference it — the foreign key nulls
     * them out rather than cascading — but the editor should know first.
     */
    protected function beforeDelete(array $record): ?string
    {
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM `posts` WHERE `category_id` = :id AND `deleted_at` IS NULL',
            [':id' => $record['id']]
        );

        if ($count > 0) {
            return "That category is used by {$count} post(s). Move them to another category first.";
        }

        return null;
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
