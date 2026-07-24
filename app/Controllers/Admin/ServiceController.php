<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Models\Media;
use App\Models\Service;

final class ServiceController extends ResourceController
{
    protected string $model = Service::class;

    protected string $route = '/admin/services';

    protected string $views = 'admin/services';

    protected string $singular = 'Service';

    protected string $plural = 'Services';

    protected string $order = 'sort_order ASC, title ASC';

    protected bool $sortable = true;

    protected ?string $slugColumn = 'slug';

    protected array $searchable = ['title', 'slug', 'short_description'];

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Service', 'type' => 'primary', 'sub' => 'slug'],
            ['key' => 'short_description', 'label' => 'Summary'],
            ['key' => 'sort_order', 'label' => 'Order'],
            ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'bool'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'title'             => 'required|max:150',
            'slug'              => 'nullable|max:180',
            'short_description' => 'nullable|max:400',
            'hero_headline'     => 'nullable|max:200',
            'hero_subheadline'  => 'nullable|max:400',
            'icon'              => 'nullable|max:60',
            'sort_order'        => 'nullable|integer',
            'meta_title'        => 'nullable|max:180',
            'meta_description'  => 'nullable|max:300',
        ];
    }

    protected function fields(?array $record): array
    {
        $imageId = $record['image_media_id'] ?? '';
        $faqs    = $record === null ? [] : Service::faqs((int) $record['id']);

        return [
            ['name' => 'title', 'label' => 'Service name', 'value' => $record['title'] ?? '', 'required' => true],
            ['name' => 'slug', 'label' => 'Slug', 'value' => $record['slug'] ?? '',
             'hint' => 'Becomes /services/your-slug. Leave blank to generate from the name.'],
            ['name' => 'icon', 'label' => 'Icon key', 'value' => $record['icon'] ?? '',
             'hint' => 'Name of an icon in the inline SVG sprite, for example: search, target, chart.'],
            ['name' => 'short_description', 'label' => 'Card summary', 'type' => 'textarea', 'rows' => 2,
             'value' => $record['short_description'] ?? '',
             'hint' => 'Shown on the services grid and the homepage.'],
            ['name' => 'image_media_id', 'label' => 'Service image', 'type' => 'media', 'value' => $imageId,
             'media' => $imageId === '' ? null : Media::find((int) $imageId)],

            ['type' => 'section', 'label' => 'Detail page'],
            ['name' => 'hero_headline', 'label' => 'Hero headline', 'value' => $record['hero_headline'] ?? ''],
            ['name' => 'hero_subheadline', 'label' => 'Hero subheadline', 'type' => 'textarea', 'rows' => 2,
             'value' => $record['hero_subheadline'] ?? ''],
            ['name' => 'problem_statement', 'label' => 'Problem statement', 'type' => 'textarea', 'rows' => 3,
             'value' => $record['problem_statement'] ?? '',
             'hint' => 'The situation this service exists to fix.'],
            ['name' => 'content', 'label' => 'Body copy', 'type' => 'richtext', 'value' => $record['content'] ?? ''],

            ['type' => 'section', 'label' => 'What is included'],
            ['name' => 'includes', 'label' => 'Inclusions', 'type' => 'repeater',
             'value' => $this->toRepeaterRows($record['includes'] ?? []),
             'fields' => ['item' => 'Item']],
            ['name' => 'process', 'label' => 'Process steps', 'type' => 'repeater',
             'value' => $record['process'] ?? [],
             'fields' => ['title' => 'Step', 'description' => 'What happens']],
            ['name' => 'deliverables', 'label' => 'Deliverables', 'type' => 'repeater',
             'value' => $this->toRepeaterRows($record['deliverables'] ?? []),
             'fields' => ['item' => 'Item']],

            ['type' => 'section', 'label' => 'Service FAQs'],
            ['name' => 'faqs', 'label' => 'Questions', 'type' => 'repeater',
             'value' => $faqs,
             'fields' => ['question' => 'Question', 'answer' => 'Answer'],
             'hint' => 'Emitted as FAQPage structured data on this service page.'],

            ['type' => 'section', 'label' => 'Display and SEO'],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'checkbox',
             'value' => (string) ($record['is_featured'] ?? 0)],
            ['name' => 'is_active', 'label' => 'Show on the site', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1)],
            ['name' => 'meta_title', 'label' => 'Meta title', 'value' => $record['meta_title'] ?? ''],
            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2,
             'value' => $record['meta_description'] ?? ''],
            ['name' => 'noindex', 'label' => 'Hide from search engines', 'type' => 'checkbox',
             'value' => (string) ($record['noindex'] ?? 0)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        return [
            'title'             => trim((string) $request->input('title')),
            'slug'              => trim((string) $request->input('slug', '')),
            'icon'              => $this->blankToNull((string) $request->input('icon', '')),
            'short_description' => $this->blankToNull((string) $request->input('short_description', '')),
            'hero_headline'     => $this->blankToNull((string) $request->input('hero_headline', '')),
            'hero_subheadline'  => $this->blankToNull((string) $request->input('hero_subheadline', '')),
            'problem_statement' => Sanitizer::plain((string) $request->input('problem_statement', '')) ?: null,
            'content'           => Sanitizer::rich((string) $request->input('content', '')),
            'includes'          => $this->flattenRepeater($request->input('includes', [])),
            'process'           => $this->pairRepeater($request->input('process', []), 'title', 'description'),
            'deliverables'      => $this->flattenRepeater($request->input('deliverables', [])),
            'image_media_id'    => $this->blankToNull((string) $request->input('image_media_id', '')),
            'sort_order'        => (int) $request->input('sort_order', 0),
            'is_featured'       => (int) $request->input('is_featured', 0),
            'is_active'         => (int) $request->input('is_active', 0),
            'meta_title'        => $this->blankToNull((string) $request->input('meta_title', '')),
            'meta_description'  => $this->blankToNull((string) $request->input('meta_description', '')),
            'noindex'           => (int) $request->input('noindex', 0),
        ];
    }

    protected function afterSave(int $id, Request $request, bool $isNew): void
    {
        $rows = $request->input('faqs', []);

        Service::syncFaqs($id, is_array($rows) ? $rows : []);
    }

    protected function beforeDelete(array $record): ?string
    {
        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM `case_studies` WHERE `service_id` = :id AND `deleted_at` IS NULL',
            [':id' => $record['id']]
        );

        if ($count > 0) {
            return "That service is referenced by {$count} case stud(y/ies). Reassign them first.";
        }

        return null;
    }

    /**
     * The repeater widget always works in rows of named fields, so a flat list of
     * strings is presented as rows of a single 'item' field.
     *
     * @param array<int,mixed> $values
     * @return array<int,array<string,string>>
     */
    private function toRepeaterRows(mixed $values): array
    {
        $values = is_array($values) ? $values : json_column($values);

        return array_map(static fn ($value): array => ['item' => (string) $value], array_values($values));
    }

    /**
     * @return array<int,string>
     */
    private function flattenRepeater(mixed $rows): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $flat = [];

        foreach ($rows as $row) {
            $value = trim((string) (is_array($row) ? ($row['item'] ?? '') : $row));

            if ($value !== '') {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function pairRepeater(mixed $rows, string $firstKey, string $secondKey): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $pairs = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $first  = trim((string) ($row[$firstKey] ?? ''));
            $second = trim((string) ($row[$secondKey] ?? ''));

            if ($first !== '' || $second !== '') {
                $pairs[] = [$firstKey => $first, $secondKey => $second];
            }
        }

        return $pairs;
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
