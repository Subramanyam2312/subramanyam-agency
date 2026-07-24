<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Models\Media;
use App\Models\Testimonial;

final class TestimonialController extends ResourceController
{
    protected string $model = Testimonial::class;

    protected string $route = '/admin/testimonials';

    protected string $views = 'admin/testimonials';

    protected string $singular = 'Testimonial';

    protected string $plural = 'Testimonials';

    protected string $order = 'sort_order ASC, id DESC';

    protected bool $sortable = true;

    protected array $searchable = ['quote', 'author_name', 'company'];

    protected function columns(): array
    {
        return [
            ['key' => 'author_name', 'label' => 'Author', 'type' => 'primary', 'sub' => 'company'],
            ['key' => 'quote', 'label' => 'Quote'],
            ['key' => 'rating', 'label' => 'Rating'],
            ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'bool'],
            ['key' => 'is_active', 'label' => 'Active', 'type' => 'bool'],
        ];
    }

    protected function rules(?int $id): array
    {
        return [
            'quote'       => 'required',
            'author_name' => 'required|max:120',
            'author_role' => 'nullable|max:120',
            'company'     => 'nullable|max:120',
            'rating'      => 'nullable|integer|min:1|max:5',
            'media_id'    => 'nullable|integer|exists:media,id',
            'sort_order'  => 'nullable|integer',
        ];
    }

    protected function fields(?array $record): array
    {
        $mediaId = $record['media_id'] ?? '';

        return [
            ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'rows' => 4,
             'value' => $record['quote'] ?? '', 'required' => true,
             'hint' => 'Without surrounding quotation marks — the template adds those.'],
            ['name' => 'author_name', 'label' => 'Author name', 'value' => $record['author_name'] ?? '', 'required' => true],
            ['name' => 'author_role', 'label' => 'Role', 'value' => $record['author_role'] ?? '',
             'hint' => 'For example: Head of Growth'],
            ['name' => 'company', 'label' => 'Company', 'value' => $record['company'] ?? ''],
            ['name' => 'media_id', 'label' => 'Photo or company logo', 'type' => 'media',
             'value' => $mediaId, 'media' => $mediaId === '' ? null : Media::find((int) $mediaId)],
            ['name' => 'rating', 'label' => 'Rating', 'type' => 'number', 'value' => $record['rating'] ?? '',
             'attrs' => ' min="1" max="5"', 'hint' => 'Optional, 1 to 5. Leave blank to hide the stars.'],
            ['name' => 'sort_order', 'label' => 'Sort order', 'type' => 'number', 'value' => $record['sort_order'] ?? 0],
            ['name' => 'is_featured', 'label' => 'Feature on the homepage', 'type' => 'checkbox',
             'value' => (string) ($record['is_featured'] ?? 0)],
            ['name' => 'is_active', 'label' => 'Show on the site', 'type' => 'checkbox',
             'value' => (string) ($record['is_active'] ?? 1)],
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        $rating = trim((string) $request->input('rating', ''));

        return [
            'quote'       => trim((string) $request->input('quote')),
            'author_name' => trim((string) $request->input('author_name')),
            'author_role' => $this->blankToNull((string) $request->input('author_role', '')),
            'company'     => $this->blankToNull((string) $request->input('company', '')),
            'media_id'    => $this->blankToNull((string) $request->input('media_id', '')),
            'rating'      => $rating === '' ? null : (int) $rating,
            'sort_order'  => (int) $request->input('sort_order', 0),
            'is_featured' => (int) $request->input('is_featured', 0),
            'is_active'   => (int) $request->input('is_active', 0),
        ];
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
