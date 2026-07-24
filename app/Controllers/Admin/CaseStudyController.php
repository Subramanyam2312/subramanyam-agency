<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Sanitizer;
use App\Models\CaseStudy;
use App\Models\Media;
use App\Models\Service;

final class CaseStudyController extends ResourceController
{
    protected string $model = CaseStudy::class;

    protected string $route = '/admin/case-studies';

    protected string $views = 'admin/case-studies';

    protected string $singular = 'Case study';

    protected string $plural = 'Case studies';

    protected string $order = 'sort_order ASC, published_at DESC';

    protected bool $sortable = true;

    protected bool $affectsSitemap = true;

    protected ?string $slugColumn = 'slug';

    protected array $searchable = ['title', 'client_name', 'industry'];

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'primary', 'sub' => 'client_name'],
            [
                'key'    => 'status',
                'label'  => 'Status',
                'type'   => 'badge',
                'labels' => CaseStudy::statuses(),
                'tones'  => [CaseStudy::STATUS_PUBLISHED => 'positive', CaseStudy::STATUS_DRAFT => 'muted'],
            ],
            ['key' => 'industry', 'label' => 'Industry'],
            ['key' => 'published_at', 'label' => 'Published', 'type' => 'date'],
            ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'bool'],
        ];
    }

    protected function filters(): array
    {
        return ['status' => ['label' => 'Status', 'options' => CaseStudy::statuses()]];
    }

    protected function rules(?int $id): array
    {
        return [
            'title'       => 'required|max:200',
            'slug'        => 'nullable|max:200',
            'client_name' => 'nullable|max:150',
            'industry'    => 'nullable|max:120',
            'service_id'  => 'nullable|integer|exists:services,id',
            'status'      => 'required|in:draft,published',
            'sort_order'  => 'nullable|integer',
        ];
    }

    protected function fields(?array $record): array
    {
        $coverId = $record['cover_media_id'] ?? '';

        return [
            ['name' => 'title', 'label' => 'Title', 'value' => $record['title'] ?? '', 'required' => true],
            ['name' => 'slug', 'label' => 'Slug', 'value' => $record['slug'] ?? '',
             'hint' => 'Leave blank to generate from the title.'],
            ['name' => 'client_name', 'label' => 'Client', 'value' => $record['client_name'] ?? ''],
            ['name' => 'industry', 'label' => 'Industry', 'value' => $record['industry'] ?? ''],
            ['name' => 'service_id', 'label' => 'Related service', 'type' => 'select',
             'options' => Service::options(false), 'value' => $record['service_id'] ?? ''],
            ['name' => 'cover_media_id', 'label' => 'Cover image', 'type' => 'media', 'value' => $coverId,
             'media' => $coverId === '' ? null : Media::find((int) $coverId)],

            ['type' => 'section', 'label' => 'The story'],
            ['name' => 'challenge', 'label' => 'Challenge', 'type' => 'textarea', 'rows' => 4,
             'value' => $record['challenge'] ?? ''],
            ['name' => 'solution', 'label' => 'Solution', 'type' => 'textarea', 'rows' => 4,
             'value' => $record['solution'] ?? ''],
            ['name' => 'results', 'label' => 'Results', 'type' => 'textarea', 'rows' => 4,
             'value' => $record['results'] ?? ''],
            ['name' => 'metrics', 'label' => 'Result metrics', 'type' => 'repeater',
             'value' => $record['metrics'] ?? [],
             'fields' => ['label' => 'Label', 'value' => 'Value'],
             'hint' => 'The headline tiles, for example "Qualified enquiries" / "+214%".'],

            ['type' => 'section', 'label' => 'Publishing'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => CaseStudy::statuses(),
             'value' => $record['status'] ?? 'draft', 'required' => true],
            ['name' => 'published_at', 'label' => 'Publish date', 'type' => 'datetime-local',
             'value' => isset($record['published_at']) && $record['published_at']
                 ? date('Y-m-d\TH:i', strtotime((string) $record['published_at']))
                 : ''],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'checkbox',
             'value' => (string) ($record['is_featured'] ?? 0)],

            ['type' => 'section', 'label' => 'Search engine listing'],
            ['name' => 'meta_title', 'label' => 'Meta title', 'value' => $record['meta_title'] ?? ''],
            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 2,
             'value' => $record['meta_description'] ?? ''],
            ['name' => 'noindex', 'label' => 'Hide from search engines', 'type' => 'checkbox',
             'value' => (string) ($record['noindex'] ?? 0)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        $status      = (string) $request->input('status', CaseStudy::STATUS_DRAFT);
        $publishedAt = trim((string) $request->input('published_at', ''));

        if ($status === CaseStudy::STATUS_PUBLISHED && $publishedAt === '') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        return [
            'title'            => trim((string) $request->input('title')),
            'slug'             => trim((string) $request->input('slug', '')),
            'client_name'      => $this->blankToNull((string) $request->input('client_name', '')),
            'industry'         => $this->blankToNull((string) $request->input('industry', '')),
            'challenge'        => Sanitizer::plain((string) $request->input('challenge', '')) ?: null,
            'solution'         => Sanitizer::plain((string) $request->input('solution', '')) ?: null,
            'results'          => Sanitizer::plain((string) $request->input('results', '')) ?: null,
            'metrics'          => $this->cleanRepeater($request->input('metrics', []), ['label', 'value']),
            'cover_media_id'   => $this->blankToNull((string) $request->input('cover_media_id', '')),
            'service_id'       => $this->blankToNull((string) $request->input('service_id', '')),
            'status'           => $status,
            'published_at'     => $publishedAt === '' ? null : date('Y-m-d H:i:s', (int) strtotime($publishedAt)),
            'sort_order'       => (int) $request->input('sort_order', 0),
            'is_featured'      => (int) $request->input('is_featured', 0),
            'meta_title'       => $this->blankToNull((string) $request->input('meta_title', '')),
            'meta_description' => $this->blankToNull((string) $request->input('meta_description', '')),
            'noindex'          => (int) $request->input('noindex', 0),
        ];
    }

    /**
     * Drops repeater rows the editor left blank, and keeps only the expected keys
     * so nothing extra can be smuggled into the JSON column.
     *
     * @param array<int,string> $keys
     * @return array<int,array<string,string>>
     */
    private function cleanRepeater(mixed $rows, array $keys): array
    {
        if (!is_array($rows)) {
            return [];
        }

        $clean = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $entry = [];

            foreach ($keys as $key) {
                $entry[$key] = trim((string) ($row[$key] ?? ''));
            }

            if (implode('', $entry) !== '') {
                $clean[] = $entry;
            }
        }

        return $clean;
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
